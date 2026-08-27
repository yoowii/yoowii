<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Yoowii\Pricing\Application\BuiltInPrintProductDefinitionRegistry;
use App\Yoowii\Pricing\Application\PrintQuoteService;
use App\Yoowii\Pricing\Domain\Print\PrintPricingPolicy;
use App\Yoowii\Pricing\Domain\Print\PrintQuote;
use App\Yoowii\Sourcing\UI\Http\Admin\Data\PrintQuotePreviewData;
use App\Yoowii\Sourcing\UI\Http\Admin\Form\PrintQuotePreviewType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PrintQuotePreviewController extends AbstractController
{
    #[Route('/preview', name: 'yoowii_admin_sourcing_preview', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        BuiltInPrintProductDefinitionRegistry $definitions,
        PrintQuoteService $quoteService,
    ): Response {
        $data = new PrintQuotePreviewData();
        $form = $this->createForm(PrintQuotePreviewType::class, $data);
        $form->handleRequest($request);
        $quote = null;

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $configurationData = json_decode($data->configurationJson, true, 512, JSON_THROW_ON_ERROR);

                if (!is_array($configurationData) || array_is_list($configurationData)) {
                    throw new \InvalidArgumentException('La configuration doit être un objet JSON.');
                }

                foreach (array_keys($configurationData) as $key) {
                    if (!is_string($key)) {
                        throw new \InvalidArgumentException('Les clés de configuration doivent être des chaînes.');
                    }
                }

                /** @var array<string, mixed> $configurationData */
                $configuration = $definitions->get($data->productCode)->configure($configurationData);
                $quote = $quoteService->quote(
                    $configuration,
                    new PrintPricingPolicy(
                        sprintf('admin-preview-%s', (new \DateTimeImmutable())->format('Ymd')),
                        $data->markupBasisPoints,
                        $data->minimumMargin,
                        $data->handlingFee,
                    ),
                    strtoupper($data->currencyCode),
                    new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                );
            } catch (\JsonException|\InvalidArgumentException|\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->renderPreview($form, $quote);
    }

    private function renderPreview(FormInterface $form, ?PrintQuote $quote): Response
    {
        return $this->render('admin/sourcing/preview.html.twig', [
            'page_title' => 'Prévisualiser un tarif print',
            'form' => $form,
            'quote' => $quote,
        ]);
    }
}
