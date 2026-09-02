<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\UI\Http\Admin\Controller;

use App\Yoowii\PrintProduction\Application\AddPrintJobNote;
use App\Yoowii\PrintProduction\Application\PrintAssetStorage;
use App\Yoowii\PrintProduction\Application\QueuePrintJobNotification;
use App\Yoowii\PrintProduction\Application\RecordPrintJobActivity;
use App\Yoowii\PrintProduction\Application\RegisterPrintAsset;
use App\Yoowii\PrintProduction\Domain\Model\PrintAsset;
use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use App\Yoowii\PrintProduction\Domain\Model\PrintJobActivity;
use App\Yoowii\PrintProduction\Domain\Model\PrintJobNote;
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
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/jobs', name: 'yoowii_admin_print_production_')]
final class PrintJobController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $status = PrintJobStatus::tryFrom((string) $request->query->get('status'));
        $supplier = trim((string) $request->query->get('supplier'));
        $search = trim((string) $request->query->get('q'));
        $customer = trim((string) $request->query->get('customer'));
        $attention = (string) $request->query->get('attention');
        $from = $this->dateFilter((string) $request->query->get('from'), false);
        $to = $this->dateFilter((string) $request->query->get('to'), true);
        $query = $entityManager->createQueryBuilder()
            ->select('job', 'item', 'salesOrder', 'customerAccount')
            ->from(PrintJob::class, 'job')
            ->join('job.orderItem', 'item')
            ->join('item.order', 'salesOrder')
            ->leftJoin('salesOrder.customer', 'customerAccount')
            ->orderBy('job.updatedAt', 'DESC');

        if ($status instanceof PrintJobStatus) {
            $query->andWhere('job.status = :status')->setParameter('status', $status->value);
        }
        if ('' !== $supplier) {
            $query->andWhere('job.supplierCode = :supplier')->setParameter('supplier', $supplier);
        }
        if ('' !== $search) {
            $query->andWhere('job.reference LIKE :search OR salesOrder.number LIKE :search OR item.productName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        if ('' !== $customer) {
            $query->andWhere('customerAccount.email LIKE :customer')->setParameter('customer', '%' . $customer . '%');
        }
        if (null !== $from) {
            $query->andWhere('job.updatedAt >= :from')->setParameter('from', $from);
        }
        if (null !== $to) {
            $query->andWhere('job.updatedAt <= :to')->setParameter('to', $to);
        }
        if ('late' === $attention) {
            $query
                ->andWhere('job.dueAt IS NOT NULL AND job.dueAt < :now')
                ->andWhere('job.status NOT IN (:terminalStatuses)')
                ->setParameter('now', new \DateTimeImmutable())
                ->setParameter('terminalStatuses', [PrintJobStatus::Delivered->value, PrintJobStatus::Cancelled->value]);
        } elseif ('blocked' === $attention) {
            $query->andWhere('job.status = :attentionStatus')->setParameter('attentionStatus', PrintJobStatus::Blocked->value);
        } elseif ('awaiting_files' === $attention) {
            $query->andWhere('job.status = :attentionStatus')->setParameter('attentionStatus', PrintJobStatus::AwaitingFiles->value);
        } else {
            $attention = '';
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

        $now = new \DateTimeImmutable();
        $blockedCount = (int) $entityManager->createQueryBuilder()
            ->select('COUNT(job.id)')->from(PrintJob::class, 'job')
            ->where('job.status = :status')->setParameter('status', PrintJobStatus::Blocked->value)
            ->getQuery()->getSingleScalarResult();
        $awaitingFilesCount = (int) $entityManager->createQueryBuilder()
            ->select('COUNT(job.id)')->from(PrintJob::class, 'job')
            ->where('job.status = :status')->setParameter('status', PrintJobStatus::AwaitingFiles->value)
            ->getQuery()->getSingleScalarResult();
        $lateCount = (int) $entityManager->createQueryBuilder()
            ->select('COUNT(job.id)')->from(PrintJob::class, 'job')
            ->where('job.dueAt IS NOT NULL AND job.dueAt < :now')
            ->andWhere('job.status NOT IN (:terminalStatuses)')
            ->setParameter('now', $now)
            ->setParameter('terminalStatuses', [PrintJobStatus::Delivered->value, PrintJobStatus::Cancelled->value])
            ->getQuery()->getSingleScalarResult();

        return $this->render('admin/print_production/index.html.twig', [
            'jobs' => $query->getQuery()->getResult(),
            'statuses' => PrintJobStatus::cases(),
            'suppliers' => $suppliers,
            'filters' => ['status' => $status?->value, 'supplier' => $supplier, 'q' => $search, 'customer' => $customer, 'attention' => $attention, 'from' => $request->query->get('from', ''), 'to' => $request->query->get('to', '')],
            'indicators' => ['late' => $lateCount, 'blocked' => $blockedCount, 'awaiting_files' => $awaitingFilesCount],
            'now' => $now,
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
            'notes' => $entityManager->getRepository(PrintJobNote::class)->findBy(['printJob' => $job], ['createdAt' => 'DESC']),
            'transitions' => $job->availableStatusTransitions(),
            'now' => new \DateTimeImmutable(),
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
    #[IsGranted('ROLE_PRINT_PRODUCTION')]
    public function uploadBat(int $id, Request $request, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrf, RegisterPrintAsset $register, RecordPrintJobActivity $activity, QueuePrintJobNotification $notifications): Response
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
            $notifications->customerStatusChanged($job);
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
    #[IsGranted('ROLE_PRINT_PRODUCTION')]
    public function changeStatus(int $id, Request $request, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrf, RecordPrintJobActivity $activity, QueuePrintJobNotification $notifications): Response
    {
        $job = $this->job($entityManager, $id);
        $this->assertCsrf($csrf, $request, 'print_job_status_' . $id);
        $status = PrintJobStatus::tryFrom((string) $request->request->get('status'));
        if (!$status instanceof PrintJobStatus) {
            $this->addFlash('danger', 'Le statut demandé est invalide.');

            return $this->redirectToRoute('yoowii_admin_print_production_show', ['id' => $id]);
        }
        $previous = $job->status();

        $reason = trim((string) $request->request->get('reason')) ?: null;

        try {
            $job->changeStatus($status, new \DateTimeImmutable(), $reason);
            $activity($job, 'status_changed', $this->actor(), ['from' => $previous->value, 'to' => $status->value, 'reason' => $reason]);
            $notifications->customerStatusChanged($job);
            $entityManager->flush();
            $this->addFlash('success', 'Le statut de production a été mis à jour.');
        } catch (\DomainException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToRoute('yoowii_admin_print_production_show', ['id' => $id]);
    }

    #[Route('/{id}/notes', name: 'add_note', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_PRINT_PRODUCTION')]
    public function addNote(int $id, Request $request, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrf, AddPrintJobNote $addNote, RecordPrintJobActivity $activity): Response
    {
        $job = $this->job($entityManager, $id);
        $this->assertCsrf($csrf, $request, 'print_job_note_' . $id);
        $message = trim((string) $request->request->get('message'));

        try {
            $addNote($job, $message, $this->actor());
            $activity($job, 'internal_note_added', $this->actor());
            $entityManager->flush();
            $this->addFlash('success', 'La note interne a été ajoutée.');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToRoute('yoowii_admin_print_production_show', ['id' => $id]);
    }

    #[Route('/{id}/due-date', name: 'set_due_date', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_PRINT_PRODUCTION')]
    public function setDueDate(int $id, Request $request, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrf, RecordPrintJobActivity $activity): Response
    {
        $job = $this->job($entityManager, $id);
        $this->assertCsrf($csrf, $request, 'print_job_due_date_' . $id);
        $value = trim((string) $request->request->get('due_at'));

        try {
            $dueAt = '' === $value ? null : new \DateTimeImmutable($value);
            $job->scheduleDueAt($dueAt, new \DateTimeImmutable());
            $activity($job, 'due_date_changed', $this->actor(), ['due_at' => $dueAt?->format(\DATE_ATOM)]);
            $entityManager->flush();
            $this->addFlash('success', null === $dueAt ? 'L’échéance a été retirée.' : 'L’échéance de production a été enregistrée.');
        } catch (\Exception) {
            $this->addFlash('danger', 'La date d’échéance est invalide.');
        }

        return $this->redirectToRoute('yoowii_admin_print_production_show', ['id' => $id]);
    }

    #[Route('/{id}/supplier-order', name: 'register_supplier_order', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_PRINT_PRODUCTION')]
    public function registerSupplierOrder(int $id, Request $request, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrf, RecordPrintJobActivity $activity, QueuePrintJobNotification $notifications): Response
    {
        $job = $this->job($entityManager, $id);
        $this->assertCsrf($csrf, $request, 'print_job_supplier_order_' . $id);
        $reference = trim((string) $request->request->get('supplier_order_reference'));

        try {
            $job->registerSupplierOrder($reference, new \DateTimeImmutable());
            $activity($job, 'supplier_order_registered', $this->actor(), ['supplier_order_reference' => $reference]);
            $notifications->customerStatusChanged($job);
            $entityManager->flush();
            $this->addFlash('success', 'La commande fournisseur est enregistrée : le dossier est en production.');
        } catch (\DomainException|\InvalidArgumentException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToRoute('yoowii_admin_print_production_show', ['id' => $id]);
    }

    #[Route('/{id}/shipment', name: 'register_shipment', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_PRINT_PRODUCTION')]
    public function registerShipment(int $id, Request $request, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrf, RecordPrintJobActivity $activity, QueuePrintJobNotification $notifications): Response
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
            $notifications->customerStatusChanged($job);
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

    private function dateFilter(string $value, bool $endOfDay): ?\DateTimeImmutable
    {
        if ('' === trim($value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value . ($endOfDay ? ' 23:59:59' : ' 00:00:00'));
        } catch (\Exception) {
            return null;
        }
    }
}
