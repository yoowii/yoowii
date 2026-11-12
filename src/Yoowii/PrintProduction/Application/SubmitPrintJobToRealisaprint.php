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
    public function __construct(private EntityManagerInterface $entityManager, private RealisaprintClient $client, private AssertArtworkPreflightIsReady $assertPreflight, private RealisaprintConfigurationMapper $configurationMapper, private RealisaprintSubmissionReservation $reservation)
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
        $now = new \DateTimeImmutable();
        $existing = $this->entityManager->getRepository(PrintJobSupplierSubmission::class)->findOneBy(['printJob' => $job]);
        // `submitting` must go through the locked reservation: it may be an
        // expired lease that has to be quarantined, not returned blindly.
        if ($existing instanceof PrintJobSupplierSubmission && in_array($existing->status(), ['submitted', 'simulated', 'pending_confirmation'], true)) {
            return $existing;
        }
        if (PrintJobStatus::BatApproved !== $job->status()) {
            throw new \DomainException('A supplier order can only be transmitted after BAT approval.');
        }
        ($this->assertPreflight)($job);

        $prescript = $this->prescriptConfiguration($job);
        $configurationPayload = null;
        if (null === $prescript) {
            $configuration = $this->configurationMapper->map($job, $now);
            $configurationPayload = [
                'product' => $configuration['product'],
                'stock' => $configuration['stock'],
                'variables' => $configuration['variables'],
            ];
        }
        $orderPayload = $this->orderPayload($job, $prescript['quantity'] ?? null);

        $payload = array_filter(['save_configuration' => $configurationPayload, 'create_order' => $orderPayload]);

        $reservation = $this->reservation->reserve($job, $now);
        $submission = $reservation->submission();
        if (!$reservation->acquired()) {
            return $submission;
        }

        $attemptToken = $reservation->attemptToken();
        if (null === $attemptToken) {
            throw new \LogicException('An acquired supplier reservation must have an attempt token.');
        }

        $outcome = 'simulated';
        $reason = null;
        $responsePayload = null;
        $supplierOrderId = null;
        $requestPayload = $payload;

        try {
            if (!$this->client->isEnabled()) {
                $responsePayload = [
                    'simulation' => true,
                    'operations' => null === $prescript ? ['save_configuration', 'create_order'] : ['create_order'],
                ];
            } else {
                $configurationResponse = null;
                $configurationCode = $prescript['code'] ?? null;
                if (null === $prescript) {
                    $configurationResponse = $this->client->post('save_configuration', $configurationPayload);
                    $configurationCode = $configurationResponse['code'] ?? null;
                }
                if (!is_scalar($configurationCode) || '' === trim((string) $configurationCode)) {
                    $outcome = 'failed';
                    $reason = null === $prescript ? $this->error($configurationResponse, 'Realisaprint did not return a configuration code.') : 'Le code Préscript est invalide.';
                } else {
                    $orderRequest = ['code' => (string) $configurationCode] + $orderPayload;
                    $requestPayload = array_filter(['save_configuration' => $configurationPayload, 'create_order' => $orderRequest]);
                    $response = $this->client->post('create_order', $orderRequest);
                    $supplierOrderId = $this->supplierOrderId($response);
                    if (null === $supplierOrderId) {
                        $outcome = 'pending_confirmation';
                        $reason = $this->error($response, 'Realisaprint did not return a supplier order identifier.');
                    } else {
                        $outcome = 'submitted';
                        $responsePayload = array_filter(['save_configuration' => $configurationResponse, 'create_order' => $response]);
                    }
                }
            }
        } catch (\Throwable $exception) {
            // The supplier may have received the request. Never retry blindly.
            $outcome = 'pending_confirmation';
            $reason = $exception->getMessage();
        }

        // Lock, verify the attempt owner and commit the submission and PrintJob
        // atomically. A late worker cannot overwrite pending_confirmation.
        $this->reservation->finalize(
            $job,
            $submission,
            $attemptToken,
            static function (PrintJobSupplierSubmission $currentSubmission, PrintJob $currentJob) use ($outcome, $requestPayload, $responsePayload, $supplierOrderId, $reason): void {
                $finishedAt = new \DateTimeImmutable();
                if ('simulated' === $outcome) {
                    $currentSubmission->recordSimulation($requestPayload, $responsePayload ?? [], $finishedAt);
                } elseif ('failed' === $outcome) {
                    $currentSubmission->recordFailure($requestPayload, $reason ?? 'Supplier configuration rejected.', $finishedAt);
                } elseif ('submitted' === $outcome && null !== $supplierOrderId) {
                    $currentSubmission->recordSuccess($requestPayload, $responsePayload ?? [], $supplierOrderId, $finishedAt);
                    $currentJob->registerSupplierOrder($supplierOrderId, $finishedAt);
                } else {
                    $currentSubmission->awaitConfirmation($requestPayload, $reason ?? 'Supplier outcome requires confirmation.', $finishedAt);
                }
            },
        );

        return $submission;
    }

    /** @return array<string, scalar> */
    private function orderPayload(PrintJob $job, ?int $providerQuantity = null): array
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
            'quantity' => $providerQuantity ?? $job->orderItem()->getQuantity(),
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

    /** @return array{code: string, quantity: int}|null */
    private function prescriptConfiguration(PrintJob $job): ?array
    {
        $pricing = $job->productionSnapshot()['pricing'] ?? null;
        $configuration = is_array($pricing) ? ($pricing['configuration'] ?? null) : null;
        $sourcing = is_array($configuration) ? ($configuration['sourcing'] ?? null) : null;
        if (!is_array($sourcing) || 'prescript' !== ($sourcing['configurator'] ?? null)) {
            return null;
        }
        $code = $sourcing['provider_configuration_code'] ?? null;
        $quantity = $sourcing['provider_quantity'] ?? null;
        if (!is_string($code) || '' === trim($code) || !is_int($quantity) || $quantity < 1) {
            throw new \DomainException('The production snapshot does not contain a valid Prescript configuration.');
        }

        return ['code' => $code, 'quantity' => $quantity];
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
