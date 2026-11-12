<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use App\Yoowii\PrintProduction\Domain\Model\PrintJobSupplierSubmission;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Database-backed lease for the one irreversible create_order call of a job.
 * The transaction is deliberately finished before the HTTP call.
 */
final readonly class RealisaprintSubmissionReservation
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function reserve(PrintJob $job, \DateTimeImmutable $now): RealisaprintSubmissionReservationResult
    {
        $jobId = $job->id();
        if (null === $jobId) {
            throw new \LogicException('A supplier submission requires a persisted print job.');
        }
        $connection = $this->entityManager->getConnection();
        $attemptToken = null;
        $connection->transactional(function () use ($connection, $jobId, $job, $now, &$attemptToken): void {
            try {
                $connection->executeStatement(
                    'INSERT INTO yoowii_print_job_supplier_submission (print_job_id, status, attempt_count, idempotency_key, created_at, updated_at) VALUES (:job, :status, 0, :key, :created, :updated)',
                    ['job' => $jobId, 'status' => 'pending', 'key' => 'realisaprint:' . $job->reference(), 'created' => $now->format('Y-m-d H:i:s'), 'updated' => $now->format('Y-m-d H:i:s')],
                );
            } catch (UniqueConstraintViolationException) {
                // Another worker created the one-per-job row. The FOR UPDATE
                // below serializes its state inspection and possible lease.
            }
            $row = $connection->fetchAssociative('SELECT status, attempt_started_at FROM yoowii_print_job_supplier_submission WHERE print_job_id = :job FOR UPDATE', ['job' => $jobId]);
            if (!is_array($row)) {
                throw new \LogicException('Could not reserve the supplier submission.');
            }
            if ('submitting' === $row['status'] && $this->expired($row['attempt_started_at'] ?? null, $now)) {
                // A crashed worker may have emitted create_order. It is uncertain, never retryable.
                $connection->executeStatement('UPDATE yoowii_print_job_supplier_submission SET status = :status, last_error = :error, updated_at = :updated WHERE print_job_id = :job', [
                    'status' => 'pending_confirmation', 'error' => 'The previous supplier submission lease expired; manual supplier confirmation is required.', 'updated' => $now->format('Y-m-d H:i:s'), 'job' => $jobId,
                ]);

                return;
            }
            if (!in_array($row['status'], ['pending', 'failed'], true)) {
                return;
            }
            $attemptToken = bin2hex(random_bytes(16));
            $connection->executeStatement('UPDATE yoowii_print_job_supplier_submission SET status = :status, attempt_count = attempt_count + 1, attempt_id = :attempt, attempt_started_at = :started, last_error = NULL, updated_at = :updated WHERE print_job_id = :job', [
                'status' => 'submitting', 'attempt' => $attemptToken, 'started' => $now->format('Y-m-d H:i:s'), 'updated' => $now->format('Y-m-d H:i:s'), 'job' => $jobId,
            ]);
        });

        $submission = $this->entityManager->getRepository(PrintJobSupplierSubmission::class)->findOneBy(['printJob' => $job]);
        if (!$submission instanceof PrintJobSupplierSubmission) {
            throw new \LogicException('Could not load the reserved supplier submission.');
        }
        $this->entityManager->refresh($submission);

        return new RealisaprintSubmissionReservationResult($submission, $attemptToken);
    }

    private function expired(mixed $value, \DateTimeImmutable $now): bool
    {
        if (!$value instanceof \DateTimeInterface) {
            $value = is_string($value) ? new \DateTimeImmutable($value) : null;
        }

        return !$value instanceof \DateTimeInterface || $value < $now->modify('-15 minutes');
    }
}
