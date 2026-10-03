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
        $product->refreshConfiguration($configuration, $at);
        $this->entityManager->flush();
    }
}
