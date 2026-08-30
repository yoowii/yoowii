<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\UI\Http\Storefront;

use App\Entity\Order\Order;
use App\Entity\User\ShopUser;
use App\Yoowii\PrintProduction\Application\PrintAssetStorage;
use App\Yoowii\PrintProduction\Application\PrintJobAccessLink;
use App\Yoowii\PrintProduction\Application\RegisterPrintAsset;
use App\Yoowii\PrintProduction\Domain\Model\PrintAsset;
use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use App\Yoowii\PrintProduction\Domain\PrintAssetType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/{_locale}/flow/print-jobs', name: 'yoowii_shop_flow_print_job_', requirements: ['_locale' => '^[a-z]{2}(?:_[A-Z]{2})?$'])]
final class PrintJobController extends AbstractController
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    #[Route('/{reference}', name: 'show', methods: ['GET'])]
    public function show(string $reference, Request $request, EntityManagerInterface $entityManager, PrintJobAccessLink $links): Response
    {
        $job = $this->accessibleJob($reference, $request, $entityManager, $links);
        $assets = $this->activeAssets($job, $entityManager);
        $locale = $this->requestLocale($request);
        $artwork = $assets[PrintAssetType::CustomerArtwork->value] ?? null;
        $bat = $assets[PrintAssetType::Bat->value] ?? null;

        $response = $this->render('shop/flow/print_job/show.html.twig', [
            'job' => $job,
            'artwork' => $artwork,
            'bat' => $bat,
            'upload_url' => $links->upload($job, $locale),
            'approve_bat_url' => $links->approveBat($job, $locale),
            'artwork_url' => $artwork instanceof PrintAsset ? $links->download($artwork, $locale) : null,
            'bat_url' => $bat instanceof PrintAsset ? $links->download($bat, $locale) : null,
        ]);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    #[Route('/{reference}/status', name: 'status', methods: ['GET'])]
    public function status(string $reference, Request $request, EntityManagerInterface $entityManager, PrintJobAccessLink $links): JsonResponse
    {
        $job = $this->accessibleJob($reference, $request, $entityManager, $links);

        return $this->json([
            'reference' => $job->reference(),
            'status' => $job->status()->value,
            'tracking_number' => $job->trackingNumber(),
            'tracking_url' => $job->trackingUrl(),
            'updated_at' => $job->updatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/{reference}/files', name: 'upload', methods: ['POST'])]
    public function upload(string $reference, Request $request, EntityManagerInterface $entityManager, PrintJobAccessLink $links, RegisterPrintAsset $register): Response
    {
        $job = $this->accessibleJob($reference, $request, $entityManager, $links);
        if (!$this->isCsrfTokenValid('print_job_upload_' . $reference, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        if ('1' !== $request->request->get('artwork_confirmation')) {
            return $this->uploadError($request, 'yoowii.print_flow.errors.confirmation_required');
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->uploadError($request, 'yoowii.print_flow.errors.invalid_file');
        }

        $stream = fopen($file->getPathname(), 'rb');
        if (false === $stream) {
            throw new \RuntimeException('Unable to read uploaded file.');
        }

        try {
            $asset = $register($job, PrintAssetType::CustomerArtwork, $file->getClientOriginalName(), (string) $file->getMimeType(), (int) $file->getSize(), $stream);
        } catch (\DomainException $exception) {
            return $this->uploadError($request, $exception->getMessage(), false);
        } finally {
            fclose($stream);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'asset_id' => $asset->id(),
                'status' => $job->status()->value,
                'redirect_url' => $links->show($job, $this->requestLocale($request)),
            ], Response::HTTP_CREATED);
        }

        $this->addFlash('success', 'yoowii.print_flow.upload_success');

        return $this->redirect($links->show($job, $this->requestLocale($request)));
    }

    #[Route('/{reference}/assets/{assetId}', name: 'download', requirements: ['assetId' => '\\d+'], methods: ['GET'])]
    public function download(string $reference, int $assetId, Request $request, EntityManagerInterface $entityManager, PrintJobAccessLink $links, PrintAssetStorage $storage): StreamedResponse
    {
        $job = $this->accessibleJob($reference, $request, $entityManager, $links);
        $asset = $entityManager->getRepository(PrintAsset::class)->find($assetId);
        if (!$asset instanceof PrintAsset || $asset->printJob() !== $job || !$asset->isActive()) {
            throw $this->createNotFoundException();
        }

        $response = new StreamedResponse(static function () use ($storage, $asset): void {
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
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    #[Route('/{reference}/bat/approve', name: 'approve_bat', methods: ['POST'])]
    public function approveBat(string $reference, Request $request, EntityManagerInterface $entityManager, PrintJobAccessLink $links): Response
    {
        $job = $this->accessibleJob($reference, $request, $entityManager, $links);
        if (!$this->isCsrfTokenValid('print_job_bat_' . $reference, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        if ('1' !== $request->request->get('bat_confirmation')) {
            $this->addFlash('danger', 'yoowii.print_flow.errors.bat_confirmation_required');

            return $this->redirect($links->show($job, $this->requestLocale($request)));
        }

        $job->markBatApproved(new \DateTimeImmutable());
        $entityManager->flush();
        $this->addFlash('success', 'yoowii.print_flow.bat_approved');

        return $this->redirect($links->show($job, $this->requestLocale($request)));
    }

    private function accessibleJob(string $reference, Request $request, EntityManagerInterface $entityManager, PrintJobAccessLink $links): PrintJob
    {
        $job = $entityManager->getRepository(PrintJob::class)->findOneBy(['reference' => $reference]);
        if (!$job instanceof PrintJob) {
            throw $this->createNotFoundException();
        }

        $user = $this->getUser();
        $order = $job->orderItem()->getOrder();
        $isOwner = $user instanceof ShopUser && $order instanceof Order && $order->getCustomer() === $user->getCustomer();
        if (!$isOwner && !$links->authorizes($request, $job)) {
            throw $this->createNotFoundException();
        }

        return $job;
    }

    /** @return array<string, PrintAsset> */
    private function activeAssets(PrintJob $job, EntityManagerInterface $entityManager): array
    {
        $assets = $entityManager->getRepository(PrintAsset::class)->findBy(['printJob' => $job, 'supersededAt' => null], ['createdAt' => 'DESC']);
        $active = [];
        foreach ($assets as $asset) {
            $active[$asset->type()->value] ??= $asset;
        }

        return $active;
    }

    private function uploadError(Request $request, string $message, bool $translate = true): Response
    {
        if ($request->isXmlHttpRequest()) {
            return $this->json(['message' => $translate ? $this->translator->trans($message) : $message], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->addFlash('danger', $message);

        return $this->redirect($request->headers->get('referer') ?? '/');
    }

    private function requestLocale(Request $request): string
    {
        $locale = $request->attributes->get('_locale');

        return is_string($locale) ? $locale : '';
    }
}
