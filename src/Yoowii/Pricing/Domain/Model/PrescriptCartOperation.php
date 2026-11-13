<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Domain\Model;

use App\Entity\Order\Order;
use App\Entity\Order\OrderItem;
use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Durable proof that a Prescript configuration was actually inserted in its cart. */
#[ORM\Entity]
#[ORM\Table(name: 'yoowii_prescript_cart_operation')]
#[ORM\UniqueConstraint(name: 'uniq_prescript_cart_operation_key', columns: ['idempotency_key_hash'])]
#[ORM\Index(name: 'idx_prescript_cart_operation_cart_status', columns: ['cart_id', 'status'])]
#[ORM\Index(name: 'idx_prescript_cart_operation_lease', columns: ['status', 'attempt_started_at'])]
final class PrescriptCartOperation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null; // @phpstan-ignore property.unusedType (Assigned by Doctrine after persistence.)

    #[ORM\Column(name: 'configuration_code', type: Types::STRING, length: 128, nullable: true)]
    private ?string $configurationCode = null;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $status = 'started';

    #[ORM\Column(name: 'attempt_id', type: Types::STRING, length: 64, nullable: true)]
    private ?string $attemptId = null; // @phpstan-ignore property.unusedType (Assigned by the DBAL reservation.)

    #[ORM\Column(name: 'attempt_started_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $attemptStartedAt = null; // @phpstan-ignore property.unusedType (Assigned by the DBAL reservation.)

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'cart_id', nullable: false, onDelete: 'CASCADE')]
    private Order $cart;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'product_id', nullable: false, onDelete: 'RESTRICT')]
    private Product $product;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'variant_id', nullable: false, onDelete: 'RESTRICT')]
    private ProductVariant $variant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'order_item_id', nullable: true, onDelete: 'SET NULL')]
    private ?OrderItem $orderItem = null;

    public function __construct(
        #[ORM\Column(name: 'idempotency_key_hash', type: Types::STRING, length: 64)]
        private string $idempotencyKeyHash,
        #[ORM\Column(name: 'payload_fingerprint', type: Types::STRING, length: 64)]
        private string $payloadFingerprint,
        #[ORM\Column(name: 'mapping_version', type: Types::STRING, length: 128)]
        private string $mappingVersion,
        Order $cart,
        Product $product,
        ProductVariant $variant,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $updatedAt,
    ) {
        $this->cart = $cart;
        $this->product = $product;
        $this->variant = $variant;
    }

    public function cart(): Order
    {
        return $this->cart;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function product(): Product
    {
        return $this->product;
    }

    public function variant(): ProductVariant
    {
        return $this->variant;
    }

    public function idempotencyKeyHash(): string
    {
        return $this->idempotencyKeyHash;
    }

    public function mappingVersion(): string
    {
        return $this->mappingVersion;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function payloadFingerprint(): string
    {
        return $this->payloadFingerprint;
    }

    public function configurationCode(): ?string
    {
        return $this->configurationCode;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function attemptId(): ?string
    {
        return $this->attemptId;
    }

    public function attemptStartedAt(): ?\DateTimeImmutable
    {
        return $this->attemptStartedAt;
    }

    public function orderItem(): ?OrderItem
    {
        return $this->orderItem;
    }

    public function rememberConfiguration(string $code, \DateTimeImmutable $at): void
    {
        $this->configurationCode = $code;
        // Keep the active lease: another worker must not create a second line
        // while this request is still completing local work.
        $this->status = 'configuration_saved';
        $this->updatedAt = $at;
    }

    public function complete(OrderItem $item, \DateTimeImmutable $at): void
    {
        $this->orderItem = $item;
        $this->status = 'completed';
        $this->updatedAt = $at;
    }
}
