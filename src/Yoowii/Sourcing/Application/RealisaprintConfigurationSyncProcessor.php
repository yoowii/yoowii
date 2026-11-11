<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintConfigurationSyncRun;
use App\Yoowii\Sourcing\Domain\RealisaprintConfigurationSyncMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class RealisaprintConfigurationSyncProcessor
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RealisaprintCatalogSynchronizer $synchronizer,
        private RealisaprintConfigurationSelection $selection,
    ) {
    }

    public function process(RealisaprintConfigurationSyncRun $run): void
    {
        if (!$run->isActive()) {
            return;
        }
        $run->start($this->now());
        $this->entityManager->flush();

        $failed = false;
        /** @var list<RealisaprintCatalogProduct> $products */
        $products = $this->entityManager->getRepository(RealisaprintCatalogProduct::class)->findBy(['archived' => false], ['name' => 'ASC']);
        foreach ($products as $product) {
            $id = $product->id();
            if (null === $id || $run->hasReportedProduct($id)) {
                continue;
            }
            if (RealisaprintConfigurationSyncMode::Missing === $run->mode() && !$this->selection->needsRefresh($product)) {
                $run->addReportEntry($this->entry($product, 'skipped'));
                $this->entityManager->flush();

                continue;
            }

            try {
                $this->synchronizer->synchronizeConfiguration($product, $this->now());
                $run->addReportEntry($this->entry($product, 'synchronized'));
            } catch (\Throwable $exception) {
                $failed = true;
                $run->addReportEntry($this->entry($product, 'failed', $this->redact($exception->getMessage())));
            }
            $this->entityManager->flush();
        }
        $run->finish($this->now(), $failed);
        $this->entityManager->flush();
    }

    /** @return array{product_id: int, provider_product_id: string, name: string, outcome: string, detail?: string} */
    private function entry(RealisaprintCatalogProduct $product, string $outcome, ?string $detail = null): array
    {
        $id = $product->id();
        if (null === $id) {
            throw new \LogicException('Le produit catalogue doit être enregistré avant synchronisation.');
        }
        $entry = ['product_id' => $id, 'provider_product_id' => $product->providerProductId(), 'name' => $product->name(), 'outcome' => $outcome];
        if (null !== $detail) {
            $entry['detail'] = $detail;
        }

        return $entry;
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private function redact(string $message): string
    {
        return preg_replace('/(?:api[_-]?key|password|authorization)\s*[:=]\s*\S+/i', '[redacted]', $message) ?? 'Erreur Realisaprint.';
    }
}
