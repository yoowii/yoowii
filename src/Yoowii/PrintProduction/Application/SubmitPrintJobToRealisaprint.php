<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

use App\Entity\Order\Order;
use App\Yoowii\PrintProduction\Domain\Model\PrintAsset;
use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use App\Yoowii\PrintProduction\Domain\Model\PrintJobSupplierSubmission;
use App\Yoowii\PrintProduction\Domain\PrintAssetType;
use App\Yoowii\PrintProduction\Domain\PrintJobStatus;
use App\Yoowii\PrintProduction\Infrastructure\Realisaprint\RealisaprintClient;
use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\Domain\SupplierCapability;
use App\Yoowii\Sourcing\Domain\SupplierIntegrationMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class SubmitPrintJobToRealisaprint
{
    public function __construct(private EntityManagerInterface $entityManager, private RealisaprintClient $client, private AssertArtworkPreflightIsReady $assertPreflight, private RealisaprintConfigurationMapper $configurationMapper)
    {
    }

    public function __invoke(PrintJob $job): PrintJobSupplierSubmission
    {
        if ('realisaprint' !== $job->supplierCode()) {
            throw new \DomainException('This print job is not assigned to Realisaprint.');
        }
        $supplier = $this->entityManager->getRepository(PrintSupplier::class)->findOneBy(['code' => $job->supplierCode()]);
        if (!$supplier instanceof PrintSupplier || !$supplier->isActive()) {
            throw new \DomainException('The selected supplier is unavailable.');
        }
        if (!in_array($supplier->integrationMode(), [SupplierIntegrationMode::Api, SupplierIntegrationMode::Hybrid], true) || !$supplier->supports(SupplierCapability::OrderSubmission)) {
            throw new \DomainException('The selected supplier is not configured for API order submission.');
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

        $configuration = $this->configurationMapper->map($job, $now);
        $configurationPayload = [
            'product' => $configuration['product'],
            'stock' => $configuration['stock'],
            'variables' => $configuration['variables'],
        ];
        $orderPayload = $this->orderPayload($job);

        try {
            if (!$this->client->isEnabled()) {
                $submission->recordSimulation(['save_configuration' => $configurationPayload, 'create_order' => $orderPayload], [
                    'simulation' => true,
                    'operations' => ['save_configuration', 'create_order'],
                ], $now);
            } else {
                $configurationResponse = $this->client->post('save_configuration', $configurationPayload);
                $configurationCode = $configurationResponse['code'] ?? null;
                if (!is_scalar($configurationCode) || '' === trim((string) $configurationCode)) {
                    $submission->recordFailure(['save_configuration' => $configurationPayload], $this->error($configurationResponse, 'Realisaprint did not return a configuration code.'), $now);

                    return $submission;
                }
                $response = $this->client->post('create_order', ['code' => (string) $configurationCode] + $orderPayload);
                $supplierOrderId = $this->supplierOrderId($response);
                if (null === $supplierOrderId) {
                    $submission->recordFailure(['save_configuration' => $configurationPayload, 'create_order' => $orderPayload], $this->error($response, 'Realisaprint did not return a supplier order identifier.'), $now);
                } else {
                    $submission->recordSuccess(['save_configuration' => $configurationPayload, 'create_order' => ['code' => (string) $configurationCode] + $orderPayload], ['save_configuration' => $configurationResponse, 'create_order' => $response], $supplierOrderId, $now);
                    $job->registerSupplierOrder($supplierOrderId, $now);
                }
            }
        } catch (\Throwable $exception) {
            $submission->recordFailure(['save_configuration' => $configurationPayload, 'create_order' => $orderPayload], $exception->getMessage(), $now);
        }

        return $submission;
    }

    /** @return array<string, scalar> */
    private function orderPayload(PrintJob $job): array
    {
        $order = $job->orderItem()->getOrder();
        if (!$order instanceof Order || null === ($address = $order->getShippingAddress())) {
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
            'quantity' => $job->orderItem()->getQuantity(),
            'control_file' => false,
            'company' => $address->getCompany() ?? '',
            'name' => $address->getLastName() ?? '',
            'surname' => $address->getFirstName() ?? '',
            'phone' => $address->getPhoneNumber() ?? '',
            'email' => $order->getCustomer()?->getEmail() ?? '',
            'address' => $address->getStreet() ?? '',
            'zip' => $address->getPostcode() ?? '',
            'city' => $address->getCity() ?? '',
            'country' => $address->getCountryCode() ?? '',
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

    /** @param array<string, mixed> $response */
    private function error(array $response, string $fallback): string
    {
        $error = $response['error'] ?? null;

        return is_string($error) && '' !== trim($error) ? $error : $fallback;
    }
}
