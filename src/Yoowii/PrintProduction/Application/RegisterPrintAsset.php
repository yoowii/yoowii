<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

use App\Yoowii\PrintProduction\Domain\Model\PrintAsset;
use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use App\Yoowii\PrintProduction\Domain\PrintAssetType;
use App\Yoowii\PrintProduction\Domain\PrintJobStatus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class RegisterPrintAsset
{
    private const ALLOWED_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/tiff'];

    public function __construct(private PrintAssetStorage $storage, private EntityManagerInterface $entityManager)
    {
    }

    /** @param resource $stream */
    public function __invoke(PrintJob $job, PrintAssetType $type, string $originalName, string $mimeType, int $size, mixed $stream): PrintAsset
    {
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true) || $size < 1 || $size > 100 * 1024 * 1024) {
            throw new \DomainException('Only PDF, JPEG, PNG and TIFF files up to 100 MB are accepted.');
        }
        if (PrintAssetType::CustomerArtwork === $type && !$job->canAcceptCustomerArtwork()) {
            throw new \DomainException('The artwork is locked because the BAT is already available.');
        }
        if (PrintAssetType::Bat === $type && !$job->canRegisterBat()) {
            throw new \DomainException('A BAT can only be registered before client approval.');
        }

        $key = sprintf('%s/%s-%s', $job->reference(), $type->value, bin2hex(random_bytes(16)));
        $temporary = fopen('php://temp', 'w+b');
        if (false === $temporary) {
            throw new \RuntimeException('Unable to buffer the uploaded file.');
        }

        $hash = hash_init('sha256');
        $read = 0;

        try {
            while (!feof($stream)) {
                $chunk = fread($stream, 1024 * 1024);
                if (false === $chunk) {
                    throw new \RuntimeException('Unable to read the uploaded file.');
                }
                $read += strlen($chunk);
                hash_update($hash, $chunk);
                if (strlen($chunk) !== fwrite($temporary, $chunk)) {
                    throw new \RuntimeException('Unable to buffer the uploaded file.');
                }
            }
            if ($read !== $size) {
                throw new \RuntimeException('The uploaded file is incomplete.');
            }
            rewind($temporary);
            $this->storage->store($key, $temporary);
        } finally {
            fclose($temporary);
        }

        $now = new \DateTimeImmutable();
        $activeAssets = $this->entityManager->getRepository(PrintAsset::class)->findBy([
            'printJob' => $job,
            'type' => $type,
            'supersededAt' => null,
        ]);
        foreach ($activeAssets as $activeAsset) {
            $activeAsset->supersede($now);
        }

        $asset = new PrintAsset($job, $type, basename($originalName), $key, $mimeType, $size, hash_final($hash), $now);
        $this->entityManager->persist($asset);
        if (PrintAssetType::CustomerArtwork === $type) {
            $job->recordCustomerArtwork($now);
        } elseif (PrintAssetType::Bat === $type) {
            $job->changeStatus(PrintJobStatus::BatReady, $now);
        }
        $this->entityManager->flush();

        return $asset;
    }
}
