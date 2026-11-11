<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\UI\Http\Admin\Data\SupplierProductData;
use App\Yoowii\Sourcing\UI\Http\Admin\Form\SupplierProductType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SupplierProductController extends AbstractController
{
    #[Route('/supplier-products/new', name: 'yoowii_admin_sourcing_product_create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $data = new SupplierProductData();
        $form = $this->createForm(SupplierProductType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $supplier = $data->supplier ?? throw new \LogicException('Validated supplier is missing.');
            $product = new SupplierProduct($supplier, $data->code, $data->name);

            if (!$data->active) {
                $product->deactivate();
            }

            $entityManager->persist($product);

            try {
                $entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $form->get('code')->addError(new FormError('Cette référence existe déjà chez ce fournisseur.'));

                return $this->renderSourcingForm($form, 'Nouvelle référence fournisseur');
            }

            $this->addFlash('success', 'La référence fournisseur a été créée.');

            return $this->redirectToRoute('yoowii_admin_sourcing_dashboard');
        }

        return $this->renderSourcingForm($form, 'Nouvelle référence fournisseur');
    }

    #[Route('/supplier-products/{id}/edit', name: 'yoowii_admin_sourcing_product_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $product = $entityManager->find(SupplierProduct::class, $id);

        if (!$product instanceof SupplierProduct) {
            throw $this->createNotFoundException();
        }

        $data = SupplierProductData::fromProduct($product);
        $form = $this->createForm(SupplierProductType::class, $data, ['identity_disabled' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $product->rename($data->name);
            $data->active ? $product->activate() : $product->deactivate();
            $entityManager->flush();
            $this->addFlash('success', 'La référence fournisseur a été mise à jour.');

            return $this->redirectToRoute('yoowii_admin_sourcing_dashboard');
        }

        return $this->renderSourcingForm($form, sprintf('Modifier %s', $product->name()));
    }

    private function renderSourcingForm(FormInterface $form, string $title): Response
    {
        return $this->render('admin/sourcing/form.html.twig', [
            'page_title' => $title,
            'form' => $form,
            'back_route' => 'yoowii_admin_sourcing_dashboard',
        ]);
    }
}
