<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Domain\Model\PrescriptCartOperation;
use Doctrine\ORM\EntityManagerInterface;

/** Short MySQL lease; no supplier call is made while its transaction is open. */
final readonly class PrescriptCartOperationReservation
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @return string|null The owning attempt token, or null when another worker owns/is blocking it. */
    public function reserve(PrescriptCartOperation $operation, \DateTimeImmutable $now): ?string
    {
        $id = $operation->id();
        if (null === $id) {
            throw new \LogicException('A cart operation must be persisted before it can be reserved.');
        }
        $token = null;
        $connection = $this->entityManager->getConnection();
        $connection->transactional(function () use ($connection, $id, $now, &$token): void {
            $row = $connection->fetchAssociative('SELECT status, configuration_code, attempt_started_at FROM yoowii_prescript_cart_operation WHERE id = :id FOR UPDATE', ['id' => $id]);
            if (!is_array($row) || in_array($row['status'], ['completed', 'pending_confirmation'], true)) {
                return;
            }
            $expired = $this->expired($row['attempt_started_at'] ?? null, $now);
            if ('submitting' === $row['status']) {
                if (!$expired) {
                    return;
                }
                // save_configuration may have reached the supplier without its
                // code being committed locally: it cannot be retried safely.
                $connection->executeStatement('UPDATE yoowii_prescript_cart_operation SET status = :status, updated_at = :updated WHERE id = :id', [
                    'status' => 'pending_confirmation', 'updated' => $now->format('Y-m-d H:i:s'), 'id' => $id,
                ]);

                return;
            }
            if ('configuration_saved' === $row['status'] && !$expired) {
                return;
            }
            $token = bin2hex(random_bytes(16));
            $connection->executeStatement('UPDATE yoowii_prescript_cart_operation SET status = :status, attempt_id = :attempt, attempt_started_at = :started, updated_at = :updated WHERE id = :id', [
                'status' => 'submitting', 'attempt' => $token, 'started' => $now->format('Y-m-d H:i:s'), 'updated' => $now->format('Y-m-d H:i:s'), 'id' => $id,
            ]);
        });
        $this->entityManager->refresh($operation);

        return $token;
    }

    public function owns(PrescriptCartOperation $operation, string $token): bool
    {
        $id = $operation->id();
        if (null === $id) {
            return false;
        }

        $ownedToken = $this->entityManager->getConnection()->fetchOne(
            'SELECT attempt_id FROM yoowii_prescript_cart_operation WHERE id = :id AND status IN (:submitting, :configurationSaved)',
            ['id' => $id, 'submitting' => 'submitting', 'configurationSaved' => 'configuration_saved'],
        );

        return is_string($ownedToken) && hash_equals($token, $ownedToken);
    }

    /** Persists the supplier code only while this exact lease is still held. */
    public function recordConfiguration(PrescriptCartOperation $operation, string $token, string $code, \DateTimeImmutable $now): bool
    {
        $id = $operation->id();
        if (null === $id) {
            return false;
        }
        $updated = $this->entityManager->getConnection()->executeStatement(
            'UPDATE yoowii_prescript_cart_operation SET configuration_code = :code, status = :status, updated_at = :updated WHERE id = :id AND status = :expected AND attempt_id = :attempt',
            ['code' => $code, 'status' => 'configuration_saved', 'updated' => $now->format('Y-m-d H:i:s'), 'id' => $id, 'expected' => 'submitting', 'attempt' => $token],
        );
        if (1 !== $updated) {
            return false;
        }
        $this->entityManager->refresh($operation);

        return true;
    }

    /**
     * Executes the local cart mutation under the operation row lock. The
     * callback must not call an external service.
     */
    public function finalize(PrescriptCartOperation $operation, string $token, callable $callback): bool
    {
        $id = $operation->id();
        if (null === $id) {
            return false;
        }
        $completed = false;
        $connection = $this->entityManager->getConnection();
        $connection->transactional(function () use ($connection, $id, $token, $callback, &$completed): void {
            $row = $connection->fetchAssociative('SELECT status, attempt_id FROM yoowii_prescript_cart_operation WHERE id = :id FOR UPDATE', ['id' => $id]);
            if (!is_array($row) || !in_array($row['status'], ['submitting', 'configuration_saved'], true) || !is_string($row['attempt_id']) || !hash_equals($token, $row['attempt_id'])) {
                return;
            }
            $callback();
            $this->entityManager->flush();
            $completed = true;
        });

        return $completed;
    }

    private function expired(mixed $value, \DateTimeImmutable $now): bool
    {
        if (!$value instanceof \DateTimeInterface) {
            $value = is_string($value) ? new \DateTimeImmutable($value) : null;
        }

        return !$value instanceof \DateTimeInterface || $value < $now->modify('-15 minutes');
    }
}
