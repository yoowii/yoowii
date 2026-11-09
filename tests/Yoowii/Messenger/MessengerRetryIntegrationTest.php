<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Messenger;

use App\Tests\Yoowii\Messenger\Fixture\AlwaysFailMessage;
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
