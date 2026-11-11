<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Yoowii\PrintProduction\Infrastructure\Realisaprint\RealisaprintClient;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use Doctrine\ORM\EntityManagerInterface;

final readonly class RealisaprintCatalogSynchronizer
{
    public function __construct(private RealisaprintClient $client, private EntityManagerInterface $entityManager)
    {
    }

    /** @return int number of products seen */
    public function synchronizeProducts(\DateTimeImmutable $at): int
    {
        if (!$this->client->isEnabled()) {
            throw new \DomainException('Realisaprint is disabled.');
        }
        $response = $this->client->post('products', []);
        $products = $response['products'] ?? null;
        if (!is_array($products)) {
            throw new \DomainException('Realisaprint did not return a product catalogue.');
        }
        $repository = $this->entityManager->getRepository(RealisaprintCatalogProduct::class);
        $seen = [];
        foreach ($products as $id => $name) {
            if (!is_scalar($name) || '' === trim((string) $id)) {
                continue;
            }
            $id = (string) $id;
            $seen[$id] = true;
            $product = $repository->findOneBy(['providerProductId' => $id]);
            if (!$product instanceof RealisaprintCatalogProduct) {
                $product = new RealisaprintCatalogProduct($id, (string) $name, $at);
                $this->entityManager->persist($product);
            } else {
                $product->refresh((string) $name, $at);
            }
        }
        $prescript = $this->client->post('products_for_prescript', []);
        $prescriptProducts = $prescript['products'] ?? null;
        if (!is_array($prescriptProducts)) {
            throw new \DomainException('Realisaprint did not return its Prescript catalogue.');
        }
        foreach ($products as $id => $_name) {
            $product = $repository->findOneBy(['providerProductId' => (string) $id]);
            if (!$product instanceof RealisaprintCatalogProduct) {
                continue;
            }
            $entry = $prescriptProducts[(string) $id] ?? null;
            $stocks = is_array($entry) && is_array($entry['stocks'] ?? null) ? $entry['stocks'] : [];
            $available = [];
            foreach ($stocks as $stockId => $stockName) {
                if (is_scalar($stockName) && '' !== trim((string) $stockId)) {
                    $available[(string) $stockId] = (string) $stockName;
                }
            }
            $product->refreshPrescriptStocks($available);
        }
        foreach ($repository->findAll() as $product) {
            if (!isset($seen[$product->providerProductId()])) {
                $product->archive();
            }
        }
        $this->entityManager->flush();

        return count($seen);
    }

    public function synchronizeConfiguration(RealisaprintCatalogProduct $product, \DateTimeImmutable $at): void
    {
        if (!$this->client->isEnabled()) {
            throw new \DomainException('Realisaprint is disabled.');
        }
        $configuration = $this->client->post('configurations', ['product' => $product->providerProductId()]);
        $status = $configuration['_http_status'] ?? 200;
        if (array_key_exists('error', $configuration) || array_key_exists('_malformed_response', $configuration) || !is_int($status) || $status >= 400) {
            throw new \DomainException($this->configurationError($configuration));
        }
        $product->refreshConfiguration($configuration, $at);
        $this->entityManager->flush();
    }

    /** @param array<string, mixed> $configuration */
    private function configurationError(array $configuration): string
    {
        $error = $configuration['error'] ?? null;
        if (is_scalar($error) && '' !== trim((string) $error)) {
            return 'Realisaprint a refusé la configuration : ' . $this->redact((string) $error);
        }

        return 'Realisaprint n’a pas retourné une configuration exploitable.';
    }

    private function redact(string $message): string
    {
        return preg_replace('/(?:api[_-]?key|password|authorization)\s*[:=]\s*\S+/i', '[redacted]', $message) ?? 'Erreur Realisaprint.';
    }
}
