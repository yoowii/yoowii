<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\UI\Http\Storefront;

use App\Entity\Order\Order;
use App\Entity\User\ShopUser;
use App\Yoowii\PrintProduction\Application\RegisterPrintAsset;
use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use App\Yoowii\PrintProduction\Domain\PrintAssetType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}/account/flow/print-jobs', requirements: ['_locale' => '^[a-z]{2}(?:_[A-Z]{2})?$'])]
final class PrintJobController extends AbstractController
{
    #[Route('/{reference}', name: 'yoowii_shop_flow_print_job_show', methods: ['GET'])]
    public function show(string $reference, EntityManagerInterface $entityManager): JsonResponse
    {
        $job = $this->ownedJob($reference, $entityManager);

        return $this->json(['reference' => $job->reference(), 'status' => $job->status()->value, 'tracking_number' => $job->trackingNumber(), 'tracking_url' => $job->trackingUrl(), 'updated_at' => $job->updatedAt()->format(\DateTimeInterface::ATOM)]);
    }

    #[Route('/{reference}/files', name: 'yoowii_shop_flow_print_job_upload', methods: ['POST'])]
    public function upload(string $reference, Request $request, EntityManagerInterface $entityManager, RegisterPrintAsset $register): Response
    {
        if (!$this->isCsrfTokenValid('print_job_upload_' . $reference, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $job = $this->ownedJob($reference, $entityManager);
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->json(['error' => 'invalid_file'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $stream = fopen($file->getPathname(), 'rb');
        if (false === $stream) {
            throw new \RuntimeException('Unable to read uploaded file.');
        }

        try {
            $asset = $register($job, PrintAssetType::CustomerArtwork, $file->getClientOriginalName(), (string) $file->getMimeType(), (int) $file->getSize(), $stream);
        } finally {
            fclose($stream);
        }

        return $this->json(['asset_id' => $asset->id(), 'status' => $job->status()->value], Response::HTTP_CREATED);
    }

    #[Route('/{reference}/bat/approve', name: 'yoowii_shop_flow_print_job_approve_bat', methods: ['POST'])]
    public function approveBat(string $reference, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->isCsrfTokenValid('print_job_bat_' . $reference, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $job = $this->ownedJob($reference, $entityManager);
        $job->markBatApproved(new \DateTimeImmutable());
        $entityManager->flush();

        return $this->json(['status' => $job->status()->value]);
    }

    private function ownedJob(string $reference, EntityManagerInterface $entityManager): PrintJob
    {
        $user = $this->getUser();
        if (!$user instanceof ShopUser) {
            throw $this->createAccessDeniedException();
        }
        $job = $entityManager->getRepository(PrintJob::class)->findOneBy(['reference' => $reference]);
        $order = $job instanceof PrintJob ? $job->orderItem()->getOrder() : null;
        if (!$order instanceof Order || $order->getCustomer() !== $user->getCustomer()) {
            throw $this->createNotFoundException();
        }

        return $job;
    }
}
