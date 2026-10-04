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
use Symfony\Component\String\Slugger\AsciiSlugger;

final class RealisaprintMappingController extends AbstractController
{
    #[Route('/realisaprint-publications/{routeId}/mapping', name: 'yoowii_admin_realisaprint_mapping', requirements: ['routeId' => '\\d+'], methods: ['GET', 'POST'])]
    public function create(int $routeId, Request $request, EntityManagerInterface $entityManager, RealisaprintMappingCompleteness $completeness): Response
    {
        $route = $entityManager->find(SupplierRoute::class, $routeId);
        if (!$route instanceof SupplierRoute || 'realisaprint' !== $route->supplierProduct()->supplier()->code()) {
            throw $this->createNotFoundException();
        }
        /** @var PersistedPrintProductDefinition|null $definition */
        $definition = $entityManager->getRepository(PersistedPrintProductDefinition::class)->findOneBy(['productCode' => $route->yoowiiProductCode()]);
        if (!$definition instanceof PersistedPrintProductDefinition) {
            throw $this->createNotFoundException('Définition de configurateur introuvable.');
        }
        /** @var RealisaprintCatalogProduct|null $catalogProduct */
        $catalogProduct = $entityManager->getRepository(RealisaprintCatalogProduct::class)->findOneBy(['providerProductId' => $route->supplierProduct()->code()]);

        if (!$catalogProduct instanceof RealisaprintCatalogProduct || !is_array($catalogProduct->configuration())) {
            throw $this->createNotFoundException('Configuration Realisaprint synchronisée introuvable.');
        }
        $data = new RealisaprintMappingData();
        $data->version = $this->nextVersion($entityManager, $route);
        $this->prefill($data, $definition, $catalogProduct->configuration());
        $choices = array_combine(array_keys($definition->options()), array_keys($definition->options())) ?: [];
        $form = $this->createForm(RealisaprintMappingType::class, $data, ['yoowii_options' => $choices]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $variables = $this->variables($data);
                $configuration = $catalogProduct->configuration();
                $completeness->assertComplete($definition, $configuration, ['stock' => trim($data->stock), 'variables' => $variables]);
                $mapping = new SupplierProductMappingVersion(
                    $route->supplierProduct(),
                    $route->yoowiiProductCode(),
                    trim($data->version),
                    ['realisaprint' => ['product' => $route->supplierProduct()->code(), 'stock' => trim($data->stock), 'variables' => $variables]],
                    new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
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
    private function prefill(RealisaprintMappingData $data, PersistedPrintProductDefinition $definition, array $configuration): void
    {
        $stocks = $configuration['stocks'] ?? [];
        if (is_array($stocks) && [] !== $stocks) {
            $data->stock = (string) array_key_first($stocks);
        }
        $optionsByVariable = [];
        foreach ($definition->options() as $option => $definitionOption) {
            /** @var array<string, mixed> $definitionOption */
            if (is_string($definitionOption['provider_variable'] ?? null)) {
                $optionsByVariable[$definitionOption['provider_variable']] = $option;
            }
        }
        $variables = $configuration['variables'] ?? [];
        if (!is_array($variables)) {
            return;
        }
        /** @var array<string, mixed> $variables */
        foreach ($variables as $providerVariable => $providerDefinition) {
            /** @var mixed $providerDefinition */
            if (!is_string($providerVariable) || !is_array($providerDefinition) || !isset($optionsByVariable[$providerVariable])) {
                continue;
            }
            $row = new RealisaprintMappingVariableData();
            $row->providerVariable = $providerVariable;
            $row->option = $optionsByVariable[$providerVariable];
            $row->values = json_encode($this->providerValues($providerDefinition['values'] ?? false), \JSON_THROW_ON_ERROR);
            $data->variables[] = $row;
        }
    }

    /** @return array<string, string> */
    private function providerValues(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }
        $mapped = [];
        foreach ($values as $providerValue => $label) {
            if (!is_scalar($label)) {
                continue;
            }
            $canonical = (new AsciiSlugger('fr'))->slug((string) $label)->lower()->toString();
            $canonical = trim(str_replace('-', '_', $canonical), '_');
            $mapped['' === $canonical ? 'option' : $canonical] = (string) $providerValue;
        }

        return $mapped;
    }

    /** @return array<string, array{option: string, values: array<string, bool|float|int|string>}> */
    private function variables(RealisaprintMappingData $data): array
    {
        if ('' === trim($data->stock)) {
            throw new \InvalidArgumentException('Le stock Realisaprint est obligatoire.');
        }
        $variables = [];
        foreach ($data->variables as $row) {
            if (!$row instanceof RealisaprintMappingVariableData || '' === trim($row->providerVariable) || '' === trim($row->option)) {
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
            $variables[trim($row->providerVariable)] = ['option' => trim($row->option), 'values' => $values];
        }

        return $variables;
    }
}
