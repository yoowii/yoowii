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
use Symfony\Component\String\Slugger\AsciiSlugger;

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
        $data->stock = $this->defaultStock($catalogProduct);
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
                $product = $creator->create($catalogProduct, trim($data->productCode), trim($data->name), trim($data->stock), $options, $axes);
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
        foreach ($providerVariables as $providerVariable) {
            $name = is_string($providerVariable['name'] ?? null) ? $providerVariable['name'] : 'Option';
            $integerOption = true === ($providerVariable['quantity'] ?? false) || 'float' === ($providerVariable['type'] ?? null);
            $optionCode = $this->canonicalOptionCode($name, array_keys($options), $integerOption, (string) ($providerVariable['type'] ?? ''));
            $valueLabels = $this->providerValues($providerVariable['values'] ?? null);
            $providerValueMap = $this->providerValueMap($providerVariable['values'] ?? null);
            $allowedValues = $integerOption ? [] : array_keys($valueLabels);
            $options[$optionCode] = [
                'type' => $integerOption ? 'integer' : 'code',
                'required' => true,
                'allowed_values' => $allowedValues,
                'label' => $name,
                'provider_variable' => is_string($providerVariable['id'] ?? null) ? $providerVariable['id'] : null,
                'provider_type' => is_string($providerVariable['type'] ?? null) ? $providerVariable['type'] : 'select',
                'value_labels' => $valueLabels,
                'provider_values' => $providerValueMap,
                'area' => (int) ($providerVariable['area'] ?? 1),
                'position' => (int) ($providerVariable['position'] ?? 0),
                'readonly' => (bool) ($providerVariable['readonly'] ?? false),
                'default' => $this->canonicalDefault($providerVariable, $providerValueMap, $integerOption),
            ];
            if ($integerOption) {
                $options[$optionCode]['minimum'] = 1;
            }
        }
        if ([] === $options) {
            return;
        }

        $data->options = json_encode($options, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
        $data->pricingAxes = json_encode(array_keys($options), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }

    private function defaultStock(RealisaprintCatalogProduct $catalogProduct): string
    {
        $stocks = $catalogProduct->configuration()['stocks'] ?? [];

        return is_array($stocks) && [] !== $stocks ? (string) array_key_first($stocks) : '';
    }

    /** @param array<string, mixed> $configuration @return list<array<string, mixed>> */
    private function providerVariables(array $configuration): array
    {
        $variables = $configuration['variables'] ?? [];
        if (!is_array($variables)) {
            return [];
        }
        $result = [];
        foreach ($variables as $id => $variable) {
            if (is_string($id) && is_array($variable)) {
                $variable['id'] = $id;
                $result[] = $variable;
            }
        }

        return $result;
    }

    /** @param list<string> $existing */
    private function canonicalOptionCode(string $name, array $existing, bool $integerOption, string $providerType): string
    {
        $code = $this->slug($name);
        $code = '' === $code ? 'option' : $code;
        if (in_array($code, $existing, true) && 'checkbox' === $providerType) {
            $code .= '_active';
        } elseif (in_array($code, $existing, true) && !$integerOption) {
            $code .= '_zone';
        }
        $candidate = $code;
        $suffix = 2;
        while (in_array($candidate, $existing, true)) {
            $candidate = $code . '_' . $suffix++;
        }

        return $candidate;
    }

    /** @return array<string, string> canonical value => public label */
    private function providerValues(mixed $providerValues): array
    {
        if (!is_array($providerValues)) {
            return [];
        }
        $mapped = [];
        foreach ($providerValues as $key => $label) {
            if (!is_string($label) && !is_int($label)) {
                continue;
            }
            $canonical = $this->slug((string) $label);
            $canonical = '' === $canonical ? 'option' : $canonical;
            $mapped[$canonical] = (string) $label;
        }

        return $mapped;
    }

    /** @return array<string, string> canonical value => provider value */
    private function providerValueMap(mixed $providerValues): array
    {
        if (!is_array($providerValues)) {
            return [];
        }
        $mapped = [];
        foreach ($providerValues as $key => $label) {
            if (!is_string($label) && !is_int($label)) {
                continue;
            }
            $canonical = $this->slug((string) $label);
            $mapped['' === $canonical ? 'option' : $canonical] = (string) $key;
        }

        return $mapped;
    }

    /** @param array<string, mixed> $providerVariable @param array<string, string> $providerValues */
    private function canonicalDefault(array $providerVariable, array $providerValues, bool $integerOption): string|int|null
    {
        $default = $providerVariable['default'] ?? null;
        if ($integerOption && (is_int($default) || (is_string($default) && ctype_digit($default)))) {
            return (int) $default;
        }
        if (!is_string($default) && !is_int($default)) {
            return null;
        }
        foreach ($providerValues as $canonical => $provider) {
            if ((string) $default === $provider) {
                return $canonical;
            }
        }

        return null;
    }

    private function slug(string $value): string
    {
        $value = trim($value);
        if (1 === preg_match('/^-+$/', $value)) {
            return 'sans';
        }
        $value = (new AsciiSlugger('fr'))->slug($value)->lower()->toString();
        $value = str_replace('-', '_', $value);

        return trim($value, '_');
    }
}
