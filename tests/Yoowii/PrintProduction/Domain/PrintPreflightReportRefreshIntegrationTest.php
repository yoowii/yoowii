<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\PrintProduction\Domain;

use App\Yoowii\PrintProduction\Domain\Model\PrintPreflightReport;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PrintPreflightReportRefreshIntegrationTest extends KernelTestCase
{
    public function testManagedReportCanBeRefreshedUnderThePreflightLock(): void
    {
        self::bootKernel();

        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $connection = $entityManager->getConnection();
        self::assertSame('test', static::getContainer()->getParameter('kernel.environment'));
        $databaseName = $connection->fetchOne('SELECT DATABASE()');
        self::assertIsString($databaseName);
        self::assertMatchesRegularExpression('/(?:^test_|_test$|_test_)/D', $databaseName);

        $createdSchema = false;

        try {
            if (!$connection->createSchemaManager()->tablesExist(['yoowii_print_preflight_report'])) {
                $connection->executeStatement('CREATE TABLE yoowii_print_asset (id INT AUTO_INCREMENT NOT NULL, print_job_id INT NOT NULL, type VARCHAR(32) NOT NULL, original_name VARCHAR(255) NOT NULL, storage_key VARCHAR(512) NOT NULL, mime_type VARCHAR(127) NOT NULL, size BIGINT NOT NULL, checksum VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, superseded_at DATETIME DEFAULT NULL, PRIMARY KEY(id)) ENGINE = InnoDB');
                $connection->executeStatement('CREATE TABLE yoowii_print_preflight_report (id INT AUTO_INCREMENT NOT NULL, print_asset_id INT NOT NULL, status VARCHAR(16) NOT NULL, report JSON NOT NULL, analysed_at DATETIME DEFAULT NULL, asset_checksum VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id), UNIQUE KEY uniq_print_preflight_asset (print_asset_id)) ENGINE = InnoDB');
                $createdSchema = true;
            }

            $connection->executeStatement("INSERT INTO yoowii_print_asset (print_job_id, type, original_name, storage_key, mime_type, size, checksum, created_at) VALUES (1, 'customer_artwork', 'test.pdf', 'ci-only/refresh-test.pdf', 'application/pdf', 1, ?, NOW())", [str_repeat('a', 64)]);
            $assetId = $connection->lastInsertId();
            $connection->executeStatement("INSERT INTO yoowii_print_preflight_report (print_asset_id, status, report, asset_checksum, created_at) VALUES (?, 'pending', '{}', ?, NOW())", [$assetId, str_repeat('a', 64)]);
            $reportId = (int) $connection->lastInsertId();

            $entityManager->clear();
            $report = $entityManager->find(PrintPreflightReport::class, $reportId);
            self::assertInstanceOf(PrintPreflightReport::class, $report);
            $connection->beginTransaction();
            $entityManager->refresh($report, LockMode::PESSIMISTIC_WRITE);
            $connection->commit();
            self::assertSame(str_repeat('a', 64), $report->assetChecksum());
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $entityManager->clear();
            if ($createdSchema) {
                $connection->executeStatement('DROP TABLE yoowii_print_preflight_report');
                $connection->executeStatement('DROP TABLE yoowii_print_asset');
            }
        }
    }
}
