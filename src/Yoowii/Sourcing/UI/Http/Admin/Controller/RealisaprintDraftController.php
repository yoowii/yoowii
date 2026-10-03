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
                $options = $options;
                /** @var non-empty-list<string> $axes */
                $axes = $axes;
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
}
