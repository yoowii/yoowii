<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Messenger;

use App\Tests\Yoowii\Messenger\Fixture\AlwaysFailMessage;
use App\Tests\Yoowii\Messenger\Fixture\ForcedStopProbeMessage;
use App\Tests\Yoowii\Messenger\Fixture\GracefulStopProbeMessage;
use App\Tests\Yoowii\Messenger\Fixture\SafeProbeMessage;
use App\Yoowii\Sourcing\Application\Message\SynchronizeRealisaprintCatalogConfigurations;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Process\Process;

final class MessengerRetryIntegrationTest extends KernelTestCase
{
    public function testRetriesThenMovesFailedMessageToDedicatedQueue(): void
    {
        self::bootKernel();

        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $legacyCountBefore = $this->countMessages($connection, 'default');
        $connection->executeStatement(
            "DELETE FROM messenger_messages WHERE queue_name IN ('yoowii_ci_async', 'yoowii_ci_failed')",
        );

        try {
            /** @var MessageBusInterface $bus */
            $bus = static::getContainer()->get(MessageBusInterface::class);
            $bus->dispatch(new AlwaysFailMessage('isolated-ci-failure'));

            self::assertSame(1, $this->countMessages($connection, 'yoowii_ci_async'));
            self::assertSame(0, $this->countMessages($connection, 'yoowii_ci_failed'));

            // Each delivery throws. Three automatic retries plus the initial
            // attempt exhaust the configured retry strategy.
            $process = new Process([
                PHP_BINARY,
                'bin/console',
                'messenger:consume',
                'async',
                '--env=test',
                '--no-debug',
                '--limit=4',
                '--time-limit=45',
                '--no-interaction',
            ], dirname(__DIR__, 3));
            $process->setTimeout(90);
            $process->run();

            self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            self::assertSame(0, $this->countMessages($connection, 'yoowii_ci_async'));
            self::assertSame(1, $this->countMessages($connection, 'yoowii_ci_failed'));
            self::assertSame($legacyCountBefore, $this->countMessages($connection, 'default'));
        } finally {
            // CI-only queues: cleanup never touches default or any real VPS.
            $connection->executeStatement(
                "DELETE FROM messenger_messages WHERE queue_name IN ('yoowii_ci_async', 'yoowii_ci_failed')",
            );
        }
    }


    /**
     * CI-only real Messenger transport/consumer test, no supplier or external calls.
     *
     * The handler deliberately has no side effects, so a successful ACK is
     * demonstrated by the dedicated async queue becoming empty with no failed
     * message. It does not prove application handler idempotency or SIGTERM.
     */
    public function testSafeProbeIsConsumedWithoutTouchingOtherQueues(): void
    {
        self::bootKernel();

        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $legacyBefore = $this->countMessages($connection, 'default');
        $supplierBefore = $this->countMessages($connection, 'yoowii_ci_supplier_async');
        $connection->executeStatement(
            "DELETE FROM messenger_messages WHERE queue_name IN ('yoowii_ci_async', 'yoowii_ci_failed')",
        );

        try {
            /** @var MessageBusInterface $bus */
            $bus = static::getContainer()->get(MessageBusInterface::class);
            $bus->dispatch(new SafeProbeMessage('ci-only-no-op'));

            self::assertSame(1, $this->countMessages($connection, 'yoowii_ci_async'));
            $process = new Process([
                PHP_BINARY,
                'bin/console',
                'messenger:consume',
                'async',
                '--env=test',
                '--no-debug',
                '--limit=1',
                '--time-limit=20',
                '--no-interaction',
            ], dirname(__DIR__, 3));
            $process->setTimeout(40);
            $process->run();

            self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            self::assertSame(0, $this->countMessages($connection, 'yoowii_ci_async'));
            self::assertSame(0, $this->countMessages($connection, 'yoowii_ci_failed'));
            self::assertSame($legacyBefore, $this->countMessages($connection, 'default'));
            self::assertSame($supplierBefore, $this->countMessages($connection, 'yoowii_ci_supplier_async'));
        } finally {
            $connection->executeStatement(
                "DELETE FROM messenger_messages WHERE queue_name IN ('yoowii_ci_async', 'yoowii_ci_failed')",
            );
        }
    }

    /**
     * A real CI Messenger worker receives SIGTERM while a side-effect-free
     * handler is in flight. Ensure it finishes, ACKs and exits gracefully.
     * This never tests Docker stop, supplier messages or a live VPS.
     */
    public function testWorkerFinishesInFlightMessageAfterSigterm(): void
    {
        self::bootKernel();

        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $legacyBefore = $this->countMessages($connection, 'default');
        $supplierBefore = $this->countMessages($connection, 'yoowii_ci_supplier_async');
        $connection->executeStatement(
            "DELETE FROM messenger_messages WHERE queue_name IN ('yoowii_ci_async', 'yoowii_ci_failed')",
        );

        $runId = bin2hex(random_bytes(8));
        $base = sys_get_temp_dir() . '/yoowii-ci-graceful-' . $runId;
        $started = $base . '.started';
        $finished = $base . '.finished';
        $process = null;

        try {
            /** @var MessageBusInterface $bus */
            $bus = static::getContainer()->get(MessageBusInterface::class);
            $bus->dispatch(new GracefulStopProbeMessage($runId));
            self::assertSame(1, $this->countMessages($connection, 'yoowii_ci_async'));

            $process = new Process([
                PHP_BINARY,
                'bin/console',
                'messenger:consume',
                'async',
                '--env=test',
                '--no-debug',
                '--time-limit=25',
                '--no-interaction',
            ], dirname(__DIR__, 3));
            $process->setTimeout(45);
            $process->start();

            // Wait for the handler's started marker, NOT merely the worker
            // process start. Use an explicit deadline to prevent a hung CI.
            $deadline = microtime(true) + 20;
            while (!file_exists($started) && $process->isRunning() && microtime(true) < $deadline) {
                usleep(50000);
            }
            self::assertFileExists($started, 'Worker did not enter the in-flight handler');
            self::assertFileDoesNotExist($finished, 'Probe finished before signal was sent');

            // SIGTERM is the signal sent by docker stop. Messenger should
            // finish the current handler before acknowledging and exiting.
            $process->signal(15);
            $process->wait();

            self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            self::assertFileExists($finished, 'In-flight handler was interrupted');
            self::assertSame(0, $this->countMessages($connection, 'yoowii_ci_async'));
            self::assertSame(0, $this->countMessages($connection, 'yoowii_ci_failed'));
            self::assertSame($legacyBefore, $this->countMessages($connection, 'default'));
            self::assertSame($supplierBefore, $this->countMessages($connection, 'yoowii_ci_supplier_async'));
        } finally {
            if ($process instanceof Process && $process->isRunning()) {
                $process->stop(1);
            }
            @unlink($started);
            @unlink($finished);
            $connection->executeStatement(
                "DELETE FROM messenger_messages WHERE queue_name IN ('yoowii_ci_async', 'yoowii_ci_failed')",
            );
        }
    }

    /**
     * CI-only forced stop: killing a worker before ACK retains its Doctrine
     * message. We manually clear delivered_at *in sylius_test only* to model
     * the broker redelivery lease expiry, then restart the actual consumer.
     * This explicitly does NOT assert exactly-once business side effects.
     */
    public function testInterruptedMessageCanBeRedeliveredInIsolatedDatabase(): void
    {
        self::bootKernel();

        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $legacyBefore = $this->countMessages($connection, 'default');
        $supplierBefore = $this->countMessages($connection, 'yoowii_ci_supplier_async');
        $connection->executeStatement(
            "DELETE FROM messenger_messages WHERE queue_name IN ('yoowii_ci_async', 'yoowii_ci_failed')",
        );

        $runId = bin2hex(random_bytes(8));
        $base = sys_get_temp_dir() . '/yoowii-ci-forced-' . $runId;
        $started = $base . '.started';
        $finished = $base . '.finished';
        $attempts = $base . '.attempts';
        $process = null;

        try {
            /** @var MessageBusInterface $bus */
            $bus = static::getContainer()->get(MessageBusInterface::class);
            $bus->dispatch(new ForcedStopProbeMessage($runId));
            self::assertSame(1, $this->countMessages($connection, 'yoowii_ci_async'));

            $process = new Process([
                PHP_BINARY, 'bin/console', 'messenger:consume', 'async',
                '--env=test', '--no-debug', '--time-limit=25', '--no-interaction',
            ], dirname(__DIR__, 3));
            $process->setTimeout(45);
            $process->start();

            $deadline = microtime(true) + 20;
            while (!file_exists($started) && $process->isRunning() && microtime(true) < $deadline) {
                usleep(50000);
            }
            self::assertFileExists($started, 'First delivery never started');
            self::assertFileDoesNotExist($finished, 'Probe finished before SIGKILL');

            $process->signal(9);
            $process->wait();
            self::assertNotSame(0, $process->getExitCode());
            self::assertFileDoesNotExist($finished, 'Interrupted handler unexpectedly completed');
            self::assertSame(1, $this->countMessages($connection, 'yoowii_ci_async'));
            self::assertSame(0, $this->countMessages($connection, 'yoowii_ci_failed'));

            // The real Doctrine transport uses a configurable redelivery
            // timeout, not immediate requeue on SIGKILL. Simulate its lease
            // expiry by resetting only the CI queue claim in this test DB.
            $updated = $connection->executeStatement(
                "UPDATE messenger_messages SET delivered_at = NULL WHERE queue_name = ? AND delivered_at IS NOT NULL",
                ['yoowii_ci_async'],
            );
            self::assertSame(1, $updated, 'Expected one claimed CI message');

            $restart = new Process([
                PHP_BINARY, 'bin/console', 'messenger:consume', 'async',
                '--env=test', '--no-debug', '--limit=1', '--time-limit=20', '--no-interaction',
            ], dirname(__DIR__, 3));
            $restart->setTimeout(40);
            $restart->run();
            self::assertSame(0, $restart->getExitCode(), $restart->getErrorOutput());
            self::assertFileExists($finished, 'Redelivered handler did not finish');
            self::assertSame('2', file_get_contents($attempts), 'Expected two delivery attempts');
            self::assertSame(0, $this->countMessages($connection, 'yoowii_ci_async'));
            self::assertSame(0, $this->countMessages($connection, 'yoowii_ci_failed'));
            self::assertSame($legacyBefore, $this->countMessages($connection, 'default'));
            self::assertSame($supplierBefore, $this->countMessages($connection, 'yoowii_ci_supplier_async'));
        } finally {
            if ($process instanceof Process && $process->isRunning()) {
                $process->stop(1);
            }
            @unlink($started);
            @unlink($finished);
            @unlink($attempts);
            $connection->executeStatement(
                "DELETE FROM messenger_messages WHERE queue_name IN ('yoowii_ci_async', 'yoowii_ci_failed')",
            );
        }
    }

    public function testSupplierSyncIsQueuedSeparatelyAndNeverConsumedByArtworkWorker(): void
    {
        self::bootKernel();

        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $legacyCountBefore = $this->countMessages($connection, 'default');
        $connection->executeStatement(
            "DELETE FROM messenger_messages WHERE queue_name = 'yoowii_ci_supplier_async'",
        );

        try {
            /** @var MessageBusInterface $bus */
            $bus = static::getContainer()->get(MessageBusInterface::class);
            $bus->dispatch(new SynchronizeRealisaprintCatalogConfigurations(-1));

            self::assertSame(1, $this->countMessages($connection, 'yoowii_ci_supplier_async'));
            self::assertSame(0, $this->countMessages($connection, 'yoowii_ci_async'));
            self::assertSame($legacyCountBefore, $this->countMessages($connection, 'default'));
        } finally {
            $connection->executeStatement(
                "DELETE FROM messenger_messages WHERE queue_name = 'yoowii_ci_supplier_async'",
            );
        }
    }

    private function countMessages(Connection $connection, string $queue): int
    {
        $count = $connection->fetchOne(
            'SELECT COUNT(*) FROM messenger_messages WHERE queue_name = ?',
            [$queue],
        );
        self::assertTrue(is_int($count) || (is_string($count) && ctype_digit($count)));

        return (int) $count;
    }
}
