<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
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
    public function create(int $routeId, Request $request, EntityManagerInterface $entityManager): Response
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

        $data = new RealisaprintMappingData();
        $this->prefill($data, $definition);
        $choices = array_combine(array_keys($definition->options()), array_keys($definition->options())) ?: [];
        $form = $this->createForm(RealisaprintMappingType::class, $data, ['yoowii_options' => $choices]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $variables = $this->variables($data);
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

    private function prefill(RealisaprintMappingData $data, PersistedPrintProductDefinition $definition): void
    {
        foreach (array_keys($definition->options()) as $option) {
            $row = new RealisaprintMappingVariableData();
            $row->option = $option;
            $allowedValues = $definition->options()[$option]['allowed_values'] ?? [];
            $identityValues = [];
            foreach ($allowedValues as $value) {
                $identityValues[(string) $value] = $value;
            }
            $row->values = json_encode($identityValues, \JSON_THROW_ON_ERROR);
            $data->variables[] = $row;
        }
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
            if (!is_array($values) || array_is_list($values)) {
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
