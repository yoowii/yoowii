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
        $contents = stream_get_contents($stream);
        if (!is_string($contents) || strlen($contents) !== $size) {
            throw new \RuntimeException('The uploaded file is incomplete.');
        }
        $checksum = hash('sha256', $contents);
        $key = sprintf('%s/%s-%s', $job->reference(), $type->value, bin2hex(random_bytes(16)));
        $temporary = fopen('php://temp', 'w+b');
        if (false === $temporary) {
            throw new \RuntimeException('Unable to buffer the uploaded file.');
        }
        fwrite($temporary, $contents);
        rewind($temporary);
        $this->storage->store($key, $temporary);
        fclose($temporary);

        $asset = new PrintAsset($job, $type, basename($originalName), $key, $mimeType, $size, $checksum, new \DateTimeImmutable());
        $this->entityManager->persist($asset);
        if (PrintAssetType::CustomerArtwork === $type && PrintJobStatus::AwaitingFiles === $job->status()) {
            $job->changeStatus(PrintJobStatus::FilesReceived, new \DateTimeImmutable());
        } elseif (PrintAssetType::Bat === $type) {
            $job->changeStatus(PrintJobStatus::BatReady, new \DateTimeImmutable());
        }
        $this->entityManager->flush();

        return $asset;
    }
}
