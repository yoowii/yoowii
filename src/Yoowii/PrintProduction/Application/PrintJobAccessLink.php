<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

use App\Yoowii\PrintProduction\Domain\Model\PrintAsset;
use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class PrintJobAccessLink
{
    private UriSigner $signer;

    public function __construct(
        string $secret,
        private UrlGeneratorInterface $router,
        private int $timeToLive,
    ) {
        $this->signer = new UriSigner($secret);
    }

    public function show(PrintJob $job, string $locale): string
    {
        return $this->signedRoute('yoowii_shop_flow_print_job_show', $job, $locale);
    }

    public function upload(PrintJob $job, string $locale): string
    {
        return $this->signedRoute('yoowii_shop_flow_print_job_upload', $job, $locale);
    }

    public function approveBat(PrintJob $job, string $locale): string
    {
        return $this->signedRoute('yoowii_shop_flow_print_job_approve_bat', $job, $locale);
    }

    public function download(PrintAsset $asset, string $locale): string
    {
        return $this->signedRoute('yoowii_shop_flow_print_job_download', $asset->printJob(), $locale, ['assetId' => $asset->id()]);
    }

    public function authorizes(Request $request, PrintJob $job): bool
    {
        return $job->guestAccessEnabled() && (string) $job->accessVersion() === $request->query->get('v') && $this->signer->checkRequest($request);
    }

    /** @param array<string, int|string|null> $parameters */
    private function signedRoute(string $route, PrintJob $job, string $locale, array $parameters = []): string
    {
        $uri = $this->router->generate($route, [
            '_locale' => $locale,
            'reference' => $job->reference(),
            'v' => $job->accessVersion(),
            ...$parameters,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->signer->sign($uri, new \DateTimeImmutable(sprintf('+%d seconds', $this->timeToLive)));
    }
}
