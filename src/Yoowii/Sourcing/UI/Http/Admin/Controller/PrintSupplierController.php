<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\UI\Http\Admin\Data\PrintSupplierData;
use App\Yoowii\Sourcing\UI\Http\Admin\Form\PrintSupplierType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PrintSupplierController extends AbstractController
{
    #[Route('/suppliers/new', name: 'yoowii_admin_sourcing_supplier_create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $data = new PrintSupplierData();
        $form = $this->createForm(PrintSupplierType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $supplier = new PrintSupplier(
                $data->code,
                $data->name,
                $data->integrationMode,
                $data->capabilities,
            );

            if (!$data->active) {
                $supplier->deactivate();
            }

            $entityManager->persist($supplier);

            try {
                $entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $form->get('code')->addError(new FormError('Ce code fournisseur existe déjà.'));

                return $this->renderSourcingForm($form, 'Nouveau fournisseur');
            }

            $this->addFlash('success', 'Le fournisseur a été créé.');

            return $this->redirectToRoute('yoowii_admin_sourcing_dashboard');
        }

        return $this->renderSourcingForm($form, 'Nouveau fournisseur');
    }

    #[Route('/suppliers/{id}/edit', name: 'yoowii_admin_sourcing_supplier_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $supplier = $entityManager->find(PrintSupplier::class, $id);

        if (!$supplier instanceof PrintSupplier) {
            throw $this->createNotFoundException();
        }

        $data = PrintSupplierData::fromSupplier($supplier);
        $form = $this->createForm(PrintSupplierType::class, $data, ['code_disabled' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $supplier->rename($data->name);
            $supplier->changeIntegration($data->integrationMode, $data->capabilities);
            $data->active ? $supplier->activate() : $supplier->deactivate();
            $entityManager->flush();
            $this->addFlash('success', 'Le fournisseur a été mis à jour.');

            return $this->redirectToRoute('yoowii_admin_sourcing_dashboard');
        }

        return $this->renderSourcingForm($form, sprintf('Modifier %s', $supplier->name()));
    }

    private function renderSourcingForm(\Symfony\Component\Form\FormInterface $form, string $title): Response
    {
        return $this->render('admin/sourcing/form.html.twig', [
            'page_title' => $title,
            'form' => $form,
            'back_route' => 'yoowii_admin_sourcing_dashboard',
        ]);
    }
}
