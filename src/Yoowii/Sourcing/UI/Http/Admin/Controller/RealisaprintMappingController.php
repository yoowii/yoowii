<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Application\RealisaprintMappingCompleteness;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use App\Yoowii\Sourcing\UI\Http\Admin\Data\RealisaprintMappingData;
use App\Yoowii\Sourcing\UI\Http\Admin\Data\RealisaprintMappingVariableData;
use App\Yoowii\Sourcing\UI\Http\Admin\Form\RealisaprintMappingType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RealisaprintMappingController extends AbstractController
{
    #[Route('/realisaprint-publications/{routeId}/mapping', name: 'yoowii_admin_realisaprint_mapping', requirements: ['routeId' => '\\d+'], methods: ['GET', 'POST'])]
    public function create(int $routeId, Request $request, EntityManagerInterface $entityManager, RealisaprintMappingCompleteness $completeness): Response
    {
        $route = $entityManager->find(SupplierRoute::class, $routeId);
        if (!$route instanceof SupplierRoute || 'realisaprint' !== $route->supplierProduct()->supplier()->code()) {
            throw $this->createNotFoundException();
        }
        /** @var RealisaprintCatalogProduct|null $catalogProduct */
        $catalogProduct = $entityManager->getRepository(RealisaprintCatalogProduct::class)->findOneBy(['providerProductId' => $route->supplierProduct()->code()]);

        if (!$catalogProduct instanceof RealisaprintCatalogProduct || !is_array($catalogProduct->configuration())) {
            throw $this->createNotFoundException('Configuration Realisaprint synchronisée introuvable.');
        }
        $data = new RealisaprintMappingData();
        $data->version = $this->nextVersion($entityManager, $route);
        $previous = $entityManager->getRepository(SupplierProductMappingVersion::class)->findOneBy(
            ['supplierProduct' => $route->supplierProduct(), 'yoowiiProductCode' => $route->yoowiiProductCode()],
            ['id' => 'DESC'],
        );
        $definition = $previous instanceof SupplierProductMappingVersion ? $previous->printProductDefinition() : null;
        if (!$definition instanceof PersistedPrintProductDefinition) {
            throw $this->createNotFoundException('Définition de configurateur introuvable.');
        }
        $this->prefill($data, $definition, $catalogProduct->configuration(), $previous);
        $choices = array_combine(array_keys($definition->options()), array_keys($definition->options())) ?: [];
        $form = $this->createForm(RealisaprintMappingType::class, $data, ['yoowii_options' => $choices]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $configuration = $catalogProduct->configuration();
                $variables = $this->variables($data, $configuration);
                $completeness->assertComplete($definition, $configuration, ['stock' => trim($data->stock), 'variables' => $variables]);
                $mapping = new SupplierProductMappingVersion(
                    $route->supplierProduct(),
                    $route->yoowiiProductCode(),
                    trim($data->version),
                    [
                        'realisaprint' => ['product' => $route->supplierProduct()->code(), 'stock' => trim($data->stock), 'variables' => $variables],
                        'yoowii' => ['configurator_driver' => 'realisaprint_classic'],
                    ],
                    new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                    null,
                    $definition,
                );
                $mapping->deactivate();
                $entityManager->persist($mapping);
                $entityManager->flush();
                $this->addFlash('success', 'Le mapping est enregistré comme brouillon. La validation couverture/prix reste obligatoire avant publication.');

                return $this->redirectToRoute('yoowii_admin_sourcing_dashboard');
            } catch (\JsonException|\InvalidArgumentException|\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            } catch (UniqueConstraintViolationException) {
                $form->get('version')->addError(new FormError('Cette version existe déjà pour cette référence et ce produit.'));
            }
        }

        return $this->render('admin/sourcing/realisaprint_mapping.html.twig', ['route' => $route, 'definition' => $definition, 'catalog_product' => $catalogProduct, 'form' => $form]);
    }

    private function nextVersion(EntityManagerInterface $entityManager, SupplierRoute $route): string
    {
        $versions = $entityManager->getRepository(SupplierProductMappingVersion::class)->findBy(['supplierProduct' => $route->supplierProduct(), 'yoowiiProductCode' => $route->yoowiiProductCode()]);
        $highest = 0;
        foreach ($versions as $mapping) {
            if (1 === preg_match("/^v(\d+)$/D", $mapping->version(), $matches)) {
                $highest = max($highest, (int) $matches[1]);
            }
        }

        return 'v' . ($highest + 1);
    }

    /** @param array<string, mixed> $configuration */
    private function prefill(RealisaprintMappingData $data, PersistedPrintProductDefinition $definition, array $configuration, ?SupplierProductMappingVersion $previous): void
    {
        $provider = $previous?->configurationMapping()['realisaprint'] ?? [];
        $stocks = $configuration['stocks'] ?? [];
        $configurationVariables = $configuration['variables'] ?? null;
        $configurationVariables = is_array($configurationVariables) ? $configurationVariables : [];
        $providerRules = is_array($provider) ? ($provider['variables'] ?? null) : null;
        $providerRules = is_array($providerRules) ? $providerRules : [];
        $previousStock = is_array($provider) ? ($provider['stock'] ?? null) : null;
        if (is_scalar($previousStock) && is_array($stocks) && array_key_exists((string) $previousStock, $stocks)) {
            $data->stock = (string) $previousStock;
        } elseif (is_array($stocks) && [] !== $stocks) {
            $data->stock = (string) array_key_first($stocks);
        }

        // Existing canonical codes are authoritative. Never slug provider labels again:
        // "-----" is "sans" in the product definition, not "option".
        foreach ($definition->options() as $option => $definitionOption) {
            $id = $definitionOption['provider_variable'] ?? null;
            if (!is_string($id) || !isset($configurationVariables[$id])) {
                continue;
            }
            $previousRule = $providerRules[$id] ?? null;
            $values = $definitionOption['provider_values'] ?? [];
            if (is_array($previousRule) && ($previousRule['option'] ?? null) === $option && is_array($previousRule['values'] ?? null)) {
                $candidate = $previousRule['values'];
                $allowed = $definitionOption['allowed_values'] ?? [];
                $configurationVariable = $configurationVariables[$id] ?? null;
                $providerValues = is_array($configurationVariable) ? ($configurationVariable['values'] ?? false) : false;
                if (is_array($allowed) && is_array($providerValues) &&
                    array_diff(array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $allowed), array_keys($candidate)) === [] &&
                    array_diff(array_keys($candidate), array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $allowed)) === [] &&
                    array_diff(array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', array_values($candidate)), array_map(static fn (int|string $value): string => (string) $value, array_keys($providerValues))) === []) {
                    $values = $candidate;
                }
            }
            $row = new RealisaprintMappingVariableData();
            $row->providerVariable = $id;
            $row->option = $option;
            $row->values = json_encode((object) (is_array($values) ? $values : []), \JSON_THROW_ON_ERROR);
            $data->variables[] = $row;
        }
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return array<string, array{option: string, values: array<string, bool|float|int|string>, quantity?: true}>
     */
    private function variables(RealisaprintMappingData $data, array $configuration): array
    {
        if ('' === trim($data->stock)) {
            throw new \InvalidArgumentException('Le stock Realisaprint est obligatoire.');
        }
        $variables = [];
        foreach ($data->variables as $row) {
            if ('' === trim($row->providerVariable) || '' === trim($row->option)) {
                throw new \InvalidArgumentException('Chaque ligne doit désigner une variable fournisseur et une option Yoowii.');
            }
            $values = json_decode($row->values, true, 512, \JSON_THROW_ON_ERROR);
            if (!is_array($values) || ([] !== $values && array_is_list($values))) {
                throw new \InvalidArgumentException(sprintf('Les valeurs de %s doivent être un objet JSON.', $row->providerVariable));
            }
            foreach ($values as $canonical => $provider) {
                if (!is_string($canonical) || !is_scalar($provider)) {
                    throw new \InvalidArgumentException(sprintf('Les valeurs de %s sont invalides.', $row->providerVariable));
                }
            }
            /** @var array<string, bool|float|int|string> $values */
            $providerVariable = trim($row->providerVariable);
            $rule = ['option' => trim($row->option), 'values' => $values];
            $configurationVariables = $configuration['variables'] ?? null;
            $variable = is_array($configurationVariables) ? ($configurationVariables[$providerVariable] ?? null) : null;
            if (is_array($variable) && true === ($variable['quantity'] ?? false)) {
                $rule['quantity'] = true;
            }
            $variables[$providerVariable] = $rule;
        }

        return $variables;
    }
}
