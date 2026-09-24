<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

use App\Yoowii\PrintProduction\Domain\Model\PrintAsset;
use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use App\Yoowii\PrintProduction\Domain\Model\PrintJobSupplierFileTransfer;
use App\Yoowii\PrintProduction\Domain\Model\PrintJobSupplierSubmission;
use App\Yoowii\PrintProduction\Domain\PrintAssetType;
use App\Yoowii\PrintProduction\Infrastructure\Realisaprint\RealisaprintFtpTransport;
use Doctrine\ORM\EntityManagerInterface;

final readonly class UploadPrintJobArtworkToRealisaprint
{
    public function __construct(private EntityManagerInterface $entityManager, private PrintAssetStorage $storage, private RealisaprintFtpTransport $ftp) {}
    public function __invoke(PrintJob $job): PrintJobSupplierFileTransfer
    {
        $submission = $this->entityManager->getRepository(PrintJobSupplierSubmission::class)->findOneBy(['printJob' => $job]);
        if (!$submission instanceof PrintJobSupplierSubmission || 'submitted' !== $submission->status()) { throw new \DomainException('A confirmed Realisaprint order is required before artwork upload.'); }
        $artwork = $this->entityManager->getRepository(PrintAsset::class)->findOneBy(['printJob' => $job, 'type' => PrintAssetType::CustomerArtwork, 'supersededAt' => null]);
        if (!$artwork instanceof PrintAsset) { throw new \DomainException('No active customer artwork is available.'); }
        $response = $submission->responsePayload();
        $files = is_array($response) && is_array($response['create_order'] ?? null) ? ($response['create_order']['files'] ?? null) : null;
        if (!is_array($files) || 1 !== count($files)) { throw new \DomainException('This Realisaprint order requires manual artwork placement: the API expects more than one file or returned no file instruction.'); }
        $instruction = reset($files);
        if (!is_array($instruction) || !isset($instruction['folder'], $instruction['ext']) || !is_string($instruction['folder']) || !is_string($instruction['ext'])) { throw new \DomainException('The Realisaprint file instruction is malformed.'); }
        $extension = strtolower(pathinfo($artwork->originalName(), \PATHINFO_EXTENSION));
        $accepted = array_map('trim', explode(',', strtolower($instruction['ext'])));
        if (!in_array($extension, $accepted, true)) { throw new \DomainException('The customer artwork extension is not accepted by Realisaprint for this configuration.'); }
        $name = preg_replace('/[^A-Za-z0-9_-]+/', '_', pathinfo($artwork->originalName(), \PATHINFO_FILENAME));
        $remotePath = $job->reference() . '/' . trim($instruction['folder'], '/') . '/' . trim((string) $name, '_') . '.' . $extension;
        $transfer = $this->entityManager->getRepository(PrintJobSupplierFileTransfer::class)->findOneBy(['submission' => $submission, 'printAsset' => $artwork]);
        if (!$transfer instanceof PrintJobSupplierFileTransfer) { $transfer = new PrintJobSupplierFileTransfer($submission, $artwork, $remotePath, new \DateTimeImmutable(), new \DateTimeImmutable()); $this->entityManager->persist($transfer); }
        if ('uploaded' === $transfer->status()) { return $transfer; }
        $at = new \DateTimeImmutable();
        try {
            if ($this->ftp->isEnabled()) {
                $stream = $this->storage->open($artwork->storageKey());
                try { $this->ftp->upload($remotePath, $stream); } finally { fclose($stream); }
            }
            $transfer->markUploaded($at, !$this->ftp->isEnabled());
        } catch (\Throwable $exception) { $transfer->markFailed($exception->getMessage(), $at); }
        return $transfer;
    }
}
