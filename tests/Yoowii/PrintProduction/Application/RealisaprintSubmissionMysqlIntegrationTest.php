<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\PrintProduction\Application;

use App\Yoowii\PrintProduction\Application\RealisaprintSubmissionReservation;
use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use App\Yoowii\PrintProduction\Domain\Model\PrintJobSupplierSubmission;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RealisaprintSubmissionMysqlIntegrationTest extends KernelTestCase
{
    public function testExpiredLeaseRejectsLateSupplierResponse(): void
    {
        $this->exercise('expired');
    }

    public function testValidLeaseCommitsSubmissionAndPrintJobTogether(): void
    {
        $this->exercise('success');
    }

    public function testSqlFailureRollsBackSubmissionAndPrintJob(): void
    {
        $this->exercise('rollback');
    }

    private function exercise(string $scenario): void
    {
        self::bootKernel();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $db = $em->getConnection();
        self::assertSame('yoowii_issue28_test', $db->fetchOne('SELECT DATABASE()'));
        $otherDb = DriverManager::getConnection($db->getParams());
        $other = new EntityManager($otherDb, $em->getConfiguration());
        self::assertNotSame($db->fetchOne('SELECT CONNECTION_ID()'), $otherDb->fetchOne('SELECT CONNECTION_ID()'));
        $suffix = bin2hex(random_bytes(8));
        $productId = $variantId = $orderId = $itemId = $jobId = null;

        try {
            $db->executeStatement("INSERT INTO sylius_product (code, created_at, enabled, variant_selection_method) VALUES (?, NOW(), 1, 'choice')", [$suffix]);
            $productId = $db->lastInsertId();
            $db->executeStatement('INSERT INTO sylius_product_variant (product_id, code, created_at, position, enabled, on_hold, on_hand, tracked, shipping_required) VALUES (?, ?, NOW(), 0, 1, 0, 0, 0, 0)', [$productId, $suffix]);
            $variantId = $db->lastInsertId();
            $db->executeStatement("INSERT INTO sylius_order (state, items_total, adjustments_total, total, created_at, currency_code, locale_code, checkout_state, payment_state, shipping_state, abandoned_email) VALUES ('cart', 0, 0, 0, NOW(), 'EUR', 'fr_FR', 'cart', 'cart', 'cart', 0)");
            $orderId = $db->lastInsertId();
            $db->executeStatement('INSERT INTO sylius_order_item (order_id, variant_id, quantity, unit_price, units_total, adjustments_total, total, is_immutable) VALUES (?, ?, 1, 0, 0, 0, 0, 0)', [$orderId, $variantId]);
            $itemId = $db->lastInsertId();
            $db->executeStatement("INSERT INTO yoowii_print_job (order_item_id, reference, supplier_code, supplier_product_code, production_snapshot, status, created_at, updated_at) VALUES (?, ?, 'realisaprint', 'test', '{}', 'bat_approved', NOW(), NOW())", [$itemId, 'TEST-' . $suffix]);
            $jobId = $db->lastInsertId();
            $job = $em->find(PrintJob::class, $jobId);
            $jobB = $other->find(PrintJob::class, $jobId);
            self::assertInstanceOf(PrintJob::class, $job);
            self::assertInstanceOf(PrintJob::class, $jobB);
            $serviceA = new RealisaprintSubmissionReservation($em);
            $serviceB = new RealisaprintSubmissionReservation($other);
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $a = $serviceA->reserve($job, $now);
            self::assertTrue($a->acquired());
            $token = $a->attemptToken();
            self::assertIsString($token);
            $calls = 1; // Worker A is paused at the fake create_order response barrier.
            $b = $serviceB->reserve($jobB, 'expired' === $scenario ? $now->modify('+16 minutes') : $now);
            if ($b->acquired()) {
                ++$calls;
            }
            self::assertFalse($b->acquired());
            self::assertSame(1, $calls);
            $record = static function (PrintJobSupplierSubmission $submission, PrintJob $currentJob) use ($now, $scenario, $db): void {
                $submission->recordSuccess([], ['id_order' => '123'], '123', $now);
                $currentJob->registerSupplierOrder('123', $now);
                if ('rollback' === $scenario) {
                    $db->executeStatement('INSERT INTO yoowii_print_job_supplier_submission (id) VALUES (NULL)');
                }
            };

            try {
                $result = $serviceA->finalize($job, $a->submission(), $token, $record);
                self::assertNotSame('rollback', $scenario, 'Expected SQL failure');
                self::assertSame('success' === $scenario, $result);
            } catch (\Doctrine\DBAL\Exception $exception) {
                if ('rollback' !== $scenario) {
                    throw $exception;
                }
                self::assertNotSame('', $exception->getMessage());
            }
            $row = $otherDb->fetchAssociative('SELECT s.status, j.status AS job_status, j.supplier_order_reference FROM yoowii_print_job_supplier_submission s JOIN yoowii_print_job j ON j.id = s.print_job_id WHERE j.id = ?', [$jobId]);
            self::assertIsArray($row);
            self::assertSame('expired' === $scenario ? 'pending_confirmation' : ('success' === $scenario ? 'submitted' : 'submitting'), $row['status']);
            self::assertSame('success' === $scenario ? 'in_production' : 'bat_approved', $row['job_status']);
            self::assertSame('success' === $scenario ? '123' : null, $row['supplier_order_reference']);
            if ('expired' === $scenario) {
                $em->flush();
                self::assertSame('pending_confirmation', $otherDb->fetchOne('SELECT status FROM yoowii_print_job_supplier_submission WHERE print_job_id = ?', [$jobId]));
            }
        } finally {
            $other->close();
            if (null !== $jobId) {
                $db->executeStatement('DELETE FROM yoowii_print_job WHERE id = ?', [$jobId]);
            }
            if (null !== $itemId) {
                $db->executeStatement('DELETE FROM sylius_order_item WHERE id = ?', [$itemId]);
            }
            if (null !== $orderId) {
                $db->executeStatement('DELETE FROM sylius_order WHERE id = ?', [$orderId]);
            }
            if (null !== $productId) {
                $db->executeStatement('DELETE FROM sylius_product WHERE id = ?', [$productId]);
            }
        }
    }
}
