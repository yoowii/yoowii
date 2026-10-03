<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Yoowii\Sourcing\Application\RealisaprintDraftCreator;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use App\Yoowii\Sourcing\UI\Http\Admin\Data\RealisaprintDraftData;
use App\Yoowii\Sourcing\UI\Http\Admin\Form\RealisaprintDraftType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RealisaprintDraftController extends AbstractController
{
    #[Route('/realisaprint-catalog/{id}/draft', name: 'yoowii_admin_realisaprint_catalog_draft', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function create(int $id, Request $request, EntityManagerInterface $entityManager, RealisaprintDraftCreator $creator): Response
    {
        $catalogProduct = $entityManager->find(RealisaprintCatalogProduct::class, $id);
        if (!$catalogProduct instanceof RealisaprintCatalogProduct) {
            throw $this->createNotFoundException();
        }

        $data = new RealisaprintDraftData();
        $data->productCode = $this->suggestedCode($catalogProduct);
        $data->name = $catalogProduct->name();
        $this->prefillConfiguration($data, $catalogProduct);
        $form = $this->createForm(RealisaprintDraftType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $options = json_decode($data->options, true, 512, \JSON_THROW_ON_ERROR);
                $axes = json_decode($data->pricingAxes, true, 512, \JSON_THROW_ON_ERROR);
                if (!is_array($options) || !is_array($axes) || [] === $axes) {
                    throw new \InvalidArgumentException('Les options doivent être un objet et les axes de prix une liste non vide.');
                }
                /** @var array<string, array{type: string, required?: bool, allowed_values?: list<string|int>, minimum?: int|null, maximum?: int|null}> $options */
                /** @var non-empty-list<string> $axes */
                $product = $creator->create($catalogProduct, trim($data->productCode), trim($data->name), $options, $axes);
                $this->addFlash('success', sprintf('Le brouillon %s est créé et reste désactivé jusqu’à la publication contrôlée.', $product->getCode()));

                return $this->redirectToRoute('yoowii_admin_realisaprint_catalog_show', ['id' => $id]);
            } catch (\JsonException|\InvalidArgumentException|\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('admin/sourcing/realisaprint_catalog_draft.html.twig', ['catalog_product' => $catalogProduct, 'form' => $form]);
    }

    private function suggestedCode(RealisaprintCatalogProduct $catalogProduct): string
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $catalogProduct->name()) ?? 'PRODUCT');

        return 'PRINT_' . trim($code, '_');
    }

    private function prefillConfiguration(RealisaprintDraftData $data, RealisaprintCatalogProduct $catalogProduct): void
    {
        $configuration = $catalogProduct->configuration();
        if (!is_array($configuration)) {
            return;
        }

        $providerVariables = $this->providerVariables($configuration);
        $options = [];
        foreach ($providerVariables as $providerVariable => $providerValues) {
            $optionCode = $this->canonicalOptionCode($providerVariable, array_keys($options));
            $allowedValues = $this->allowedValues($providerValues);
            $integerOption = $this->isIntegerOption($optionCode);
            if ($integerOption) {
                $allowedValues = array_map(static fn (string $value): int => (int) $value, $allowedValues);
            }
            $options[$optionCode] = [
                'type' => $integerOption ? 'integer' : 'code',
                'required' => true,
                'allowed_values' => $allowedValues,
            ];
        }
        if ([] === $options) {
            return;
        }

        $data->options = json_encode($options, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
        $data->pricingAxes = json_encode(array_keys($options), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $configuration @return array<string, mixed> */
    private function providerVariables(array $configuration): array
    {
        $variables = [];
        $walk = static function (array $value) use (&$variables, &$walk): void {
            foreach ($value as $key => $item) {
                if (is_string($key) && str_starts_with(strtoupper($key), 'VARTICLE_')) {
                    $variables[$key] = $item;
                }
                if (is_array($item)) {
                    $walk($item);
                }
            }
        };
        $walk($configuration);

        return $variables;
    }

    /** @param list<string> $existing */
    private function canonicalOptionCode(string $providerVariable, array $existing): string
    {
        $code = strtolower($providerVariable);
        $code = preg_replace('/^varticle_/', '', $code) ?? $code;
        $code = trim($code, '_');
        $code = preg_replace('/[^a-z0-9]+/', '_', $code) ?? $code;
        $code = '' === $code ? 'option' : $code;
        $candidate = $code;
        $suffix = 2;
        while (in_array($candidate, $existing, true)) {
            $candidate = $code . '_' . $suffix++;
        }

        return $candidate;
    }

    /** @return list<string> */
    private function allowedValues(mixed $providerValues): array
    {
        if (!is_array($providerValues)) {
            return [];
        }
        $values = $providerValues['values'] ?? $providerValues;
        if (!is_array($values)) {
            return [];
        }
        $allowed = [];
        foreach ($values as $key => $value) {
            $candidate = is_string($key) || is_int($key) ? $key : $value;
            if (is_string($candidate) || is_int($candidate)) {
                $allowed[] = (string) $candidate;
            }
        }

        return array_values(array_unique($allowed));
    }

    private function isIntegerOption(string $optionCode): bool
    {
        return in_array($optionCode, ['quantity', 'grammage'], true);
    }
}
