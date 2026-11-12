<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Messenger;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Proves MySQL row-lock exclusion with two separate connections in CI.
 * The handler's use of the lock is asserted by its isolated unit tests.
 */
final class PreflightLockIntegrationTest extends KernelTestCase
{
    public function testConcurrentConnectionCannotClaimLockedRowBeforeCommit(): void
    {
        self::bootKernel();

        /** @var Connection $primary */
        $primary = static::getContainer()->get('doctrine.dbal.default_connection');
        $params = $primary->getParams();
        self::assertSame('test', static::getContainer()->getParameter('kernel.environment'), 'Never run lock probe outside test environment');
        self::assertIsString($params['dbname'] ?? null, 'The test connection must name an isolated database.');

        $secondary = DriverManager::getConnection($params);
        $table = 'yoowii_ci_preflight_lock_' . bin2hex(random_bytes(5));
        $primary->executeStatement('CREATE TABLE ' . $table . ' (id INT PRIMARY KEY, status VARCHAR(16) NOT NULL) ENGINE=InnoDB');
        $primary->executeStatement("INSERT INTO $table (id, status) VALUES (1, 'pending')");

        try {
            $primary->beginTransaction();
            $primary->executeQuery('SELECT status FROM ' . $table . ' WHERE id = 1 FOR UPDATE')->fetchOne();
            $secondary->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
            $secondary->beginTransaction();

            $blocked = false;

            try {
                $secondary->executeQuery('SELECT status FROM ' . $table . ' WHERE id = 1 FOR UPDATE')->fetchOne();
            } catch (\Doctrine\DBAL\Exception) {
                $blocked = true;
            }

            self::assertTrue($blocked, 'Second consumer must not acquire a held row lock');

            $secondary->rollBack();
            $primary->executeStatement("UPDATE $table SET status = 'passed' WHERE id = 1");
            $primary->commit();

            $secondary->beginTransaction();
            $status = $secondary->executeQuery('SELECT status FROM ' . $table . ' WHERE id = 1 FOR UPDATE')->fetchOne();
            self::assertSame('passed', $status, 'Consumer must see committed final status after the lock');
            $secondary->commit();
        } finally {
            if ($primary->isTransactionActive()) {
                $primary->rollBack();
            }
            if ($secondary->isTransactionActive()) {
                $secondary->rollBack();
            }
            $secondary->close();
            $primary->executeStatement('DROP TABLE IF EXISTS ' . $table);
        }
    }
}
