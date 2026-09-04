<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'yoowii_print_job_supplier_submission')]
#[ORM\UniqueConstraint(name: 'uniq_print_supplier_submission_job', columns: ['print_job_id'])]
class PrintJobSupplierSubmission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $status = 'pending';

    #[ORM\Column(name: 'attempt_count', type: Types::INTEGER, options: ['default' => 0])]
    private int $attemptCount = 0;

    #[ORM\Column(name: 'supplier_order_id', type: Types::STRING, length: 128, nullable: true)]
    private ?string $supplierOrderId = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'request_payload', type: Types::JSON, nullable: true)]
    private ?array $requestPayload = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'response_payload', type: Types::JSON, nullable: true)]
    private ?array $responsePayload = null;

    #[ORM\Column(name: 'last_error', type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    public function __construct(
        #[ORM\OneToOne]
        #[ORM\JoinColumn(name: 'print_job_id', nullable: false, onDelete: 'CASCADE')]
        private readonly PrintJob $printJob,
        #[ORM\Column(name: 'idempotency_key', type: Types::STRING, length: 128, unique: true)]
        private readonly string $idempotencyKey,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly \DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public function id(): ?int { return $this->id; }
    public function status(): string { return $this->status; }
    public function attemptCount(): int { return $this->attemptCount; }
    public function supplierOrderId(): ?string { return $this->supplierOrderId; }
    public function idempotencyKey(): string { return $this->idempotencyKey; }
    /** @return array<string, mixed>|null */ public function requestPayload(): ?array { return $this->requestPayload; }
    /** @return array<string, mixed>|null */ public function responsePayload(): ?array { return $this->responsePayload; }
    public function lastError(): ?string { return $this->lastError; }

    /** @param array<string, mixed> $payload @param array<string, mixed> $response */
    public function recordSimulation(array $payload, array $response, \DateTimeImmutable $at): void
    {
        $this->record('simulated', $payload, $response, null, null, $at);
    }

    /** @param array<string, mixed> $payload @param array<string, mixed> $response */
    public function recordSuccess(array $payload, array $response, string $supplierOrderId, \DateTimeImmutable $at): void
    {
        $this->record('submitted', $payload, $response, $supplierOrderId, null, $at);
    }

    /** @param array<string, mixed> $payload */
    public function recordFailure(array $payload, string $error, \DateTimeImmutable $at): void
    {
        $this->record('failed', $payload, null, null, mb_substr($error, 0, 4000), $at);
    }

    /** @param array<string, mixed> $payload @param array<string, mixed>|null $response */
    private function record(string $status, array $payload, ?array $response, ?string $supplierOrderId, ?string $error, \DateTimeImmutable $at): void
    {
        ++$this->attemptCount;
        $this->status = $status;
        $this->requestPayload = $payload;
        $this->responsePayload = $response;
        $this->supplierOrderId = $supplierOrderId;
        $this->lastError = $error;
        $this->updatedAt = $at;
    }
}
