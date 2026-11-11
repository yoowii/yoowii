<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Domain\Model;

use App\Entity\Order\OrderItem;
use App\Yoowii\PrintProduction\Domain\PrintJobStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'yoowii_print_job')]
#[ORM\UniqueConstraint(name: 'uniq_print_job_order_item', columns: ['order_item_id'])]
#[ORM\UniqueConstraint(name: 'uniq_print_job_reference', columns: ['reference'])]
class PrintJob
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\Column(type: Types::STRING, length: 32, enumType: PrintJobStatus::class)]
    private PrintJobStatus $status = PrintJobStatus::AwaitingFiles;

    #[ORM\Column(name: 'supplier_order_reference', type: Types::STRING, length: 128, nullable: true)]
    private ?string $supplierOrderReference = null;

    #[ORM\Column(name: 'tracking_number', type: Types::STRING, length: 128, nullable: true)]
    private ?string $trackingNumber = null;

    #[ORM\Column(name: 'tracking_url', type: Types::STRING, length: 2048, nullable: true)]
    private ?string $trackingUrl = null;

    #[ORM\Column(name: 'status_reason', type: Types::TEXT, nullable: true)]
    private ?string $statusReason = null;

    #[ORM\Column(name: 'due_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'access_version', type: Types::INTEGER, options: ['default' => 1])]
    private int $accessVersion = 1;

    #[ORM\Column(name: 'guest_access_enabled', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $guestAccessEnabled = true;

    /** @param array<string, mixed> $productionSnapshot */
    public function __construct(
        #[ORM\OneToOne]
        #[ORM\JoinColumn(name: 'order_item_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly OrderItem $orderItem,
        #[ORM\Column(type: Types::STRING, length: 40)]
        private readonly string $reference,
        #[ORM\Column(name: 'supplier_code', type: Types::STRING, length: 64)]
        private readonly string $supplierCode,
        #[ORM\Column(name: 'supplier_product_code', type: Types::STRING, length: 128)]
        private readonly string $supplierProductCode,
        #[ORM\Column(name: 'production_snapshot', type: Types::JSON)]
        private readonly array $productionSnapshot,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly \DateTimeImmutable $createdAt,
    ) {
        if ('' === trim($reference) || '' === trim($supplierCode) || '' === trim($supplierProductCode)) {
            throw new \InvalidArgumentException('Print job reference and supplier identifiers are required.');
        }

        $this->updatedAt = $createdAt;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function orderItem(): OrderItem
    {
        return $this->orderItem;
    }

    public function reference(): string
    {
        return $this->reference;
    }

    public function status(): PrintJobStatus
    {
        return $this->status;
    }

    public function supplierCode(): string
    {
        return $this->supplierCode;
    }

    public function supplierProductCode(): string
    {
        return $this->supplierProductCode;
    }

    /** @return array<string, mixed> */
    public function productionSnapshot(): array
    {
        return $this->productionSnapshot;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function accessVersion(): int
    {
        return $this->accessVersion;
    }

    public function revokeGuestLinks(\DateTimeImmutable $at): void
    {
        ++$this->accessVersion;
        $this->guestAccessEnabled = false;
        $this->updatedAt = $at;
    }

    public function guestAccessEnabled(): bool
    {
        return $this->guestAccessEnabled;
    }

    public function renewGuestLinks(\DateTimeImmutable $at): void
    {
        ++$this->accessVersion;
        $this->guestAccessEnabled = true;
        $this->updatedAt = $at;
    }

    public function supplierOrderReference(): ?string
    {
        return $this->supplierOrderReference;
    }

    public function trackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function trackingUrl(): ?string
    {
        return $this->trackingUrl;
    }

    public function statusReason(): ?string
    {
        return $this->statusReason;
    }

    public function dueAt(): ?\DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function isLate(\DateTimeImmutable $at): bool
    {
        return null !== $this->dueAt &&
            $this->dueAt < $at &&
            !in_array($this->status, [PrintJobStatus::Delivered, PrintJobStatus::Cancelled], true);
    }

    public function scheduleDueAt(?\DateTimeImmutable $dueAt, \DateTimeImmutable $at): void
    {
        $this->dueAt = $dueAt;
        $this->updatedAt = $at;
    }

    public function changeStatus(PrintJobStatus $status, \DateTimeImmutable $at, ?string $reason = null): void
    {
        if ($status === $this->status) {
            return;
        }
        if (!in_array($status, $this->allowedNextStatuses(), true)) {
            throw new \DomainException(sprintf('The transition from "%s" to "%s" is not allowed.', $this->status->value, $status->value));
        }
        $reason = null === $reason ? null : trim($reason);
        if (in_array($status, [PrintJobStatus::Blocked, PrintJobStatus::Cancelled], true) && (null === $reason || '' === $reason)) {
            throw new \DomainException('A reason is required when blocking or cancelling a print job.');
        }
        $this->status = $status;
        $this->statusReason = in_array($status, [PrintJobStatus::Blocked, PrintJobStatus::Cancelled], true) ? $reason : null;
        $this->updatedAt = $at;
    }

    public function markBatApproved(\DateTimeImmutable $at): void
    {
        if (PrintJobStatus::BatReady !== $this->status) {
            throw new \DomainException('Only a ready BAT can be approved.');
        }
        $this->changeStatus(PrintJobStatus::BatApproved, $at);
    }

    public function rejectBat(string $reason, \DateTimeImmutable $at): void
    {
        if (PrintJobStatus::BatReady !== $this->status) {
            throw new \DomainException('Only a ready BAT can be rejected.');
        }
        if ('' === trim($reason)) {
            throw new \InvalidArgumentException('A BAT rejection reason is required.');
        }

        $this->changeStatus(PrintJobStatus::BatPending, $at);
    }

    public function canAcceptCustomerArtwork(): bool
    {
        return in_array($this->status, [PrintJobStatus::AwaitingFiles, PrintJobStatus::FilesReceived, PrintJobStatus::BatPending], true);
    }

    public function recordCustomerArtwork(\DateTimeImmutable $at): void
    {
        if (!$this->canAcceptCustomerArtwork()) {
            throw new \DomainException('Customer artwork can no longer be replaced after the BAT is ready.');
        }

        if (PrintJobStatus::AwaitingFiles === $this->status) {
            $this->changeStatus(PrintJobStatus::FilesReceived, $at);
        } else {
            $this->updatedAt = $at;
        }
    }

    public function canRegisterBat(): bool
    {
        return in_array($this->status, [PrintJobStatus::FilesReceived, PrintJobStatus::BatPending, PrintJobStatus::BatReady], true);
    }

    /** @return list<PrintJobStatus> */
    public function availableStatusTransitions(): array
    {
        return $this->allowedNextStatuses();
    }

    public function registerSupplierOrder(string $reference, \DateTimeImmutable $at): void
    {
        if ('' === trim($reference)) {
            throw new \InvalidArgumentException('Supplier order reference is required.');
        }
        $this->supplierOrderReference = $reference;
        $this->changeStatus(PrintJobStatus::InProduction, $at);
    }

    public function markShipped(string $trackingNumber, ?string $trackingUrl, \DateTimeImmutable $at): void
    {
        if ('' === trim($trackingNumber)) {
            throw new \InvalidArgumentException('Tracking number is required.');
        }
        $this->trackingNumber = $trackingNumber;
        $this->trackingUrl = $trackingUrl;
        $this->changeStatus(PrintJobStatus::Shipped, $at);
    }

    /** @return list<PrintJobStatus> */
    private function allowedNextStatuses(): array
    {
        return match ($this->status) {
            PrintJobStatus::AwaitingFiles => [PrintJobStatus::FilesReceived, PrintJobStatus::Blocked, PrintJobStatus::Cancelled],
            PrintJobStatus::FilesReceived => [PrintJobStatus::BatPending, PrintJobStatus::BatReady, PrintJobStatus::Blocked, PrintJobStatus::Cancelled],
            PrintJobStatus::BatPending => [PrintJobStatus::BatReady, PrintJobStatus::Blocked, PrintJobStatus::Cancelled],
            PrintJobStatus::BatReady => [PrintJobStatus::BatPending, PrintJobStatus::BatApproved, PrintJobStatus::Blocked, PrintJobStatus::Cancelled],
            PrintJobStatus::BatApproved => [PrintJobStatus::InProduction, PrintJobStatus::Blocked, PrintJobStatus::Cancelled],
            PrintJobStatus::InProduction => [PrintJobStatus::Shipped, PrintJobStatus::Blocked, PrintJobStatus::Cancelled],
            PrintJobStatus::Shipped => [PrintJobStatus::Delivered, PrintJobStatus::Blocked],
            PrintJobStatus::Blocked => [
                PrintJobStatus::AwaitingFiles,
                PrintJobStatus::FilesReceived,
                PrintJobStatus::BatPending,
                PrintJobStatus::BatReady,
                PrintJobStatus::BatApproved,
                PrintJobStatus::InProduction,
                PrintJobStatus::Shipped,
                PrintJobStatus::Cancelled,
            ],
            PrintJobStatus::Delivered, PrintJobStatus::Cancelled => [],
        };
    }
}
