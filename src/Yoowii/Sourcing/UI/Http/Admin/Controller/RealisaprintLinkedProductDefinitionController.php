<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Application\RealisaprintLinkedProductDefinitionEditor;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use App\Yoowii\Sourcing\UI\Http\Admin\Data\RealisaprintLinkedProductDefinitionData;
use App\Yoowii\Sourcing\UI\Http\Admin\Form\RealisaprintLinkedProductDefinitionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RealisaprintLinkedProductDefinitionController extends AbstractController
{
    #[Route('/realisaprint-catalog/{id}/linked-products/{routeId}/definition', name: 'yoowii_admin_realisaprint_linked_product_definition', requirements: ['id' => '\\d+', 'routeId' => '\\d+'], methods: ['GET', 'POST'])]
    public function show(int $id, int $routeId, Request $request, EntityManagerInterface $entityManager, RealisaprintLinkedProductDefinitionEditor $editor): Response
    {
        $catalogProduct = $entityManager->find(RealisaprintCatalogProduct::class, $id);
        $route = $entityManager->find(SupplierRoute::class, $routeId);
        if (!$catalogProduct instanceof RealisaprintCatalogProduct || !$route instanceof SupplierRoute ||
            'realisaprint' !== $route->supplierProduct()->supplier()->code() ||
            $catalogProduct->providerProductId() !== $route->supplierProduct()->code()) {
            throw $this->createNotFoundException();
        }

        $mapping = $entityManager->getRepository(SupplierProductMappingVersion::class)->findOneBy(
            ['supplierProduct' => $route->supplierProduct(), 'yoowiiProductCode' => $route->yoowiiProductCode()],
            ['id' => 'DESC'],
        );
        if (!$mapping instanceof SupplierProductMappingVersion) {
            throw $this->createNotFoundException('Mapping fournisseur introuvable.');
        }
        $definition = $mapping->printProductDefinition();
        if (!$definition instanceof PersistedPrintProductDefinition) {
            throw $this->createNotFoundException('Définition de configurateur introuvable.');
        }
        $provider = $mapping->configurationMapping()['realisaprint'] ?? [];
        $stock = is_array($provider) && is_scalar($provider['stock'] ?? null) ? (string) $provider['stock'] : '';
        $data = new RealisaprintLinkedProductDefinitionData();
        $data->configuratorMode = is_array($provider) && 'prescript' === ($provider['configurator'] ?? null) ? 'prescript' : 'classic';
        $data->options = json_encode($definition->options(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
        $data->pricingAxes = json_encode($definition->pricingAxes(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
        $prescriptAvailable = $catalogProduct->supportsPrescriptStock($stock);
        $form = $this->createForm(RealisaprintLinkedProductDefinitionType::class, $data, ['prescript_available' => $prescriptAvailable]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $options = json_decode($data->options, true, 512, \JSON_THROW_ON_ERROR);
                $axes = json_decode($data->pricingAxes, true, 512, \JSON_THROW_ON_ERROR);
                if (!is_array($options) || array_is_list($options) || !is_array($axes) || !array_is_list($axes) || [] === $axes || array_filter($axes, static fn (mixed $axis): bool => !is_string($axis)) !== []) {
                    throw new \InvalidArgumentException('Les options doivent être un objet JSON et les axes de prix une liste non vide de codes.');
                }
                $newMapping = $editor->update($route, $catalogProduct, $definition, $data->configuratorMode, $options, $axes);
                $this->addFlash('success', 'La définition liée a été mise à jour. Relance le contrôle de couverture et de prix avant publication.');

                return $this->redirectToRoute('yoowii_admin_realisaprint_mapping_validation', ['id' => $newMapping->id()]);
            } catch (\JsonException|\InvalidArgumentException|\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('admin/sourcing/realisaprint_linked_product_definition.html.twig', [
            'catalog_product' => $catalogProduct,
            'route' => $route,
            'definition' => $definition,
            'mapping' => $mapping,
            'form' => $form,
            'editable' => !$definition->active() && !$mapping->isActive(),
            'can_create_revision' => $definition->active() && $mapping->isActive(),
            'prescript_available' => $prescriptAvailable,
        ]);
    }
}
