<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use App\Yoowii\PrintProduction\Domain\Model\PrintJobSupplierSubmission;
use App\Yoowii\PrintProduction\Domain\Model\PrintAsset;
use App\Yoowii\PrintProduction\Domain\PrintAssetType;
use App\Yoowii\PrintProduction\Domain\PrintJobStatus;
use App\Yoowii\PrintProduction\Infrastructure\Realisaprint\RealisaprintClient;
use Doctrine\ORM\EntityManagerInterface;

final readonly class SubmitPrintJobToRealisaprint
{
    public function __construct(private EntityManagerInterface $entityManager, private RealisaprintClient $client, private AssertArtworkPreflightIsReady $assertPreflight)
    {
    }

    public function __invoke(PrintJob $job): PrintJobSupplierSubmission
    {
        if ('realisaprint' !== $job->supplierCode()) {
            throw new \DomainException('This print job is not assigned to Realisaprint.');
        }
        if (PrintJobStatus::BatApproved !== $job->status()) {
            throw new \DomainException('A supplier order can only be transmitted after BAT approval.');
        }
        ($this->assertPreflight)($job);

        $now = new \DateTimeImmutable();
        $submission = $this->entityManager->getRepository(PrintJobSupplierSubmission::class)->findOneBy(['printJob' => $job]);
        if (!$submission instanceof PrintJobSupplierSubmission) {
            $submission = new PrintJobSupplierSubmission($job, 'realisaprint:' . $job->reference(), $now, $now);
            $this->entityManager->persist($submission);
        }
        if ('submitted' === $submission->status()) {
            return $submission;
        }

        $payload = $this->payload($job, $submission);
        try {
            $response = $this->client->post('create_order', $payload);
            if (($response['simulation'] ?? false) === true) {
                $submission->recordSimulation($payload, $response, $now);
            } else {
                $supplierOrderId = $this->supplierOrderId($response);
                if (null === $supplierOrderId) {
                    $submission->recordFailure($payload, 'Realisaprint did not return a supplier order identifier.', $now);
                } else {
                    $submission->recordSuccess($payload, $response, $supplierOrderId, $now);
                    $job->registerSupplierOrder($supplierOrderId, $now);
                }
            }
        } catch (\Throwable $exception) {
            $submission->recordFailure($payload, $exception->getMessage(), $now);
        }

        return $submission;
    }

    /** @return array<string, scalar> */
    private function payload(PrintJob $job, PrintJobSupplierSubmission $submission): array
    {
        $order = $job->orderItem()->getOrder();
        $address = $order?->getShippingAddress();
        if (null === $address) {
            throw new \DomainException('A shipping address is required before supplier transmission.');
        }
        $artwork = $this->entityManager->getRepository(PrintAsset::class)->findOneBy([
            'printJob' => $job,
            'type' => PrintAssetType::CustomerArtwork,
            'supersededAt' => null,
        ]);
        if (!$artwork instanceof PrintAsset) {
            throw new \DomainException('An active customer artwork is required before supplier transmission.');
        }

        return [
            'reference' => $job->reference(),
            'idempotency_key' => $submission->idempotencyKey(),
            'product_code' => $job->supplierProductCode(),
            'quantity' => $job->orderItem()->getQuantity(),
            'configuration' => json_encode($job->productionSnapshot()['pricing']['configuration'] ?? [], \JSON_THROW_ON_ERROR),
            // The file itself is transferred by FTP in lot 10.2. Keep its immutable manifest now.
            'artwork_name' => $artwork->originalName(),
            'artwork_checksum' => $artwork->checksum(),
            'shipping_first_name' => $address->getFirstName(),
            'shipping_last_name' => $address->getLastName(),
            'shipping_company' => $address->getCompany() ?? '',
            'shipping_street' => $address->getStreet(),
            'shipping_postcode' => $address->getPostcode(),
            'shipping_city' => $address->getCity(),
            'shipping_country_code' => $address->getCountryCode(),
        ];
    }

    /** @param array<string, mixed> $response */
    private function supplierOrderId(array $response): ?string
    {
        foreach (['id_order', 'order_id', 'id'] as $key) {
            $value = $response[$key] ?? null;
            if (is_scalar($value) && '' !== trim((string) $value)) {
                return (string) $value;
            }
        }

        return null;
    }
}
