<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\UI\Http\Admin\Controller;

use App\Yoowii\PrintProduction\Application\PrintAssetStorage;
use App\Yoowii\PrintProduction\Application\RecordPrintJobActivity;
use App\Yoowii\PrintProduction\Application\RegisterPrintAsset;
use App\Yoowii\PrintProduction\Domain\Model\PrintAsset;
use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use App\Yoowii\PrintProduction\Domain\Model\PrintJobActivity;
use App\Yoowii\PrintProduction\Domain\PrintAssetType;
use App\Yoowii\PrintProduction\Domain\PrintJobStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/jobs', name: 'yoowii_admin_print_production_')]
final class PrintJobController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $status = PrintJobStatus::tryFrom((string) $request->query->get('status'));
        $supplier = trim((string) $request->query->get('supplier'));
        $search = trim((string) $request->query->get('q'));
        $query = $entityManager->createQueryBuilder()
            ->select('job', 'item', 'salesOrder')
            ->from(PrintJob::class, 'job')
            ->join('job.orderItem', 'item')
            ->join('item.order', 'salesOrder')
            ->orderBy('job.updatedAt', 'DESC');

        if ($status instanceof PrintJobStatus) {
            $query->andWhere('job.status = :status')->setParameter('status', $status);
        }
        if ('' !== $supplier) {
            $query->andWhere('job.supplierCode = :supplier')->setParameter('supplier', $supplier);
        }
        if ('' !== $search) {
            $query->andWhere('job.reference LIKE :search OR salesOrder.number LIKE :search OR item.productName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $supplierRows = $entityManager->createQueryBuilder()
            ->select('DISTINCT job.supplierCode')
            ->from(PrintJob::class, 'job')
            ->orderBy('job.supplierCode', 'ASC')
            ->getQuery()
            ->getScalarResult();
        $suppliers = [];
        foreach ($supplierRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $supplierCode = $row['supplierCode'] ?? null;
            if (is_string($supplierCode)) {
                $suppliers[] = $supplierCode;
            }
        }

        return $this->render('admin/print_production/index.html.twig', [
            'jobs' => $query->getQuery()->getResult(),
            'statuses' => PrintJobStatus::cases(),
            'suppliers' => $suppliers,
            'filters' => ['status' => $status?->value, 'supplier' => $supplier, 'q' => $search],
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager): Response
    {
        $job = $this->job($entityManager, $id);
        $assets = $entityManager->getRepository(PrintAsset::class)->findBy(['printJob' => $job], ['createdAt' => 'DESC']);

        return $this->render('admin/print_production/show.html.twig', [
            'job' => $job,
            'assets' => $assets,
            'activities' => $entityManager->getRepository(PrintJobActivity::class)->findBy(['printJob' => $job], ['createdAt' => 'DESC']),
            'transitions' => $job->availableStatusTransitions(),
        ]);
    }

    #[Route('/{id}/assets/{assetId}', name: 'download_asset', requirements: ['id' => '\\d+', 'assetId' => '\\d+'], methods: ['GET'])]
    public function downloadAsset(int $id, int $assetId, EntityManagerInterface $entityManager, PrintAssetStorage $storage): StreamedResponse
    {
        $job = $this->job($entityManager, $id);
        $asset = $entityManager->find(PrintAsset::class, $assetId);
        if (!$asset instanceof PrintAsset || $asset->printJob() !== $job) {
            throw $this->createNotFoundException();
        }

        $response = new StreamedResponse(static function () use ($asset, $storage): void {
            $stream = $storage->open($asset->storageKey());

            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        });
        $response->headers->set('Content-Type', $asset->mimeType());
        $response->headers->set('Content-Length', (string) $asset->size());
        $response->headers->set('Content-Disposition', sprintf('inline; filename="%s"', addcslashes($asset->originalName(), '"\\')));
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    #[Route('/{id}/bat', name: 'upload_bat', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function uploadBat(int $id, Request $request, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrf, RegisterPrintAsset $register, RecordPrintJobActivity $activity): Response
    {
        $job = $this->job($entityManager, $id);
        $this->assertCsrf($csrf, $request, 'print_job_bat_' . $id);
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            $this->addFlash('danger', 'Sélectionne un BAT PDF, JPEG, PNG ou TIFF valide.');

            return $this->redirectToRoute('yoowii_admin_print_production_show', ['id' => $id]);
        }
        $stream = fopen($file->getPathname(), 'rb');
        if (false === $stream) {
            throw new \RuntimeException('Unable to read BAT upload.');
        }

        try {
            $asset = $register($job, PrintAssetType::Bat, $file->getClientOriginalName(), (string) $file->getMimeType(), (int) $file->getSize(), $stream);
            $activity($job, 'bat_uploaded', $this->actor(), ['asset_id' => $asset->id(), 'file_name' => $asset->originalName()]);
            $entityManager->flush();
            $this->addFlash('success', 'Le BAT est disponible pour validation client.');
        } catch (\DomainException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        } finally {
            fclose($stream);
        }

        return $this->redirectToRoute('yoowii_admin_print_production_show', ['id' => $id]);
    }

    #[Route('/{id}/status', name: 'change_status', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function changeStatus(int $id, Request $request, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrf, RecordPrintJobActivity $activity): Response
    {
        $job = $this->job($entityManager, $id);
        $this->assertCsrf($csrf, $request, 'print_job_status_' . $id);
        $status = PrintJobStatus::tryFrom((string) $request->request->get('status'));
        if (!$status instanceof PrintJobStatus) {
            $this->addFlash('danger', 'Le statut demandé est invalide.');

            return $this->redirectToRoute('yoowii_admin_print_production_show', ['id' => $id]);
        }
        $previous = $job->status();

        try {
            $job->changeStatus($status, new \DateTimeImmutable());
            $activity($job, 'status_changed', $this->actor(), ['from' => $previous->value, 'to' => $status->value]);
            $entityManager->flush();
            $this->addFlash('success', 'Le statut de production a été mis à jour.');
        } catch (\DomainException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToRoute('yoowii_admin_print_production_show', ['id' => $id]);
    }

    #[Route('/{id}/supplier-order', name: 'register_supplier_order', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function registerSupplierOrder(int $id, Request $request, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrf, RecordPrintJobActivity $activity): Response
    {
        $job = $this->job($entityManager, $id);
        $this->assertCsrf($csrf, $request, 'print_job_supplier_order_' . $id);
        $reference = trim((string) $request->request->get('supplier_order_reference'));

        try {
            $job->registerSupplierOrder($reference, new \DateTimeImmutable());
            $activity($job, 'supplier_order_registered', $this->actor(), ['supplier_order_reference' => $reference]);
            $entityManager->flush();
            $this->addFlash('success', 'La commande fournisseur est enregistrée : le dossier est en production.');
        } catch (\DomainException|\InvalidArgumentException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToRoute('yoowii_admin_print_production_show', ['id' => $id]);
    }

    #[Route('/{id}/shipment', name: 'register_shipment', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function registerShipment(int $id, Request $request, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrf, RecordPrintJobActivity $activity): Response
    {
        $job = $this->job($entityManager, $id);
        $this->assertCsrf($csrf, $request, 'print_job_shipment_' . $id);
        $trackingNumber = trim((string) $request->request->get('tracking_number'));
        $trackingUrl = trim((string) $request->request->get('tracking_url')) ?: null;
        if (null !== $trackingUrl && false === filter_var($trackingUrl, \FILTER_VALIDATE_URL)) {
            $this->addFlash('danger', 'Le lien de suivi doit être une URL valide.');

            return $this->redirectToRoute('yoowii_admin_print_production_show', ['id' => $id]);
        }

        try {
            $job->markShipped($trackingNumber, $trackingUrl, new \DateTimeImmutable());
            $activity($job, 'shipment_registered', $this->actor(), ['tracking_number' => $trackingNumber, 'tracking_url' => $trackingUrl]);
            $entityManager->flush();
            $this->addFlash('success', 'L’expédition est enregistrée et le suivi client est disponible.');
        } catch (\DomainException|\InvalidArgumentException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToRoute('yoowii_admin_print_production_show', ['id' => $id]);
    }

    private function job(EntityManagerInterface $entityManager, int $id): PrintJob
    {
        $job = $entityManager->find(PrintJob::class, $id);
        if (!$job instanceof PrintJob) {
            throw $this->createNotFoundException();
        }

        return $job;
    }

    private function assertCsrf(CsrfTokenManagerInterface $csrf, Request $request, string $tokenId): void
    {
        if (!$csrf->isTokenValid(new CsrfToken($tokenId, (string) $request->request->get('_token')))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }

    private function actor(): ?string
    {
        $user = $this->getUser();

        return $user instanceof UserInterface ? $user->getUserIdentifier() : null;
    }
}
