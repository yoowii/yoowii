<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\UI\Http\Admin\Data\SupplierProductMappingData;
use App\Yoowii\Sourcing\UI\Http\Admin\Form\SupplierProductMappingType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class SupplierProductMappingController extends AbstractController
{
    #[Route('/mappings/new', name: 'yoowii_admin_sourcing_mapping_create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $data = new SupplierProductMappingData();
        $data->effectiveFrom = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $form = $this->createForm(SupplierProductMappingType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $mapping = json_decode($data->configurationMapping, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($mapping)) {
                    throw new \InvalidArgumentException('Le mapping JSON doit être un objet.');
                }
                $entityManager->persist(new SupplierProductMappingVersion(
                    $data->supplierProduct ?? throw new \LogicException('Missing supplier product.'),
                    $data->yoowiiProductCode,
                    $data->version,
                    $mapping,
                    $data->effectiveFrom ?? throw new \LogicException('Missing effective date.'),
                ));
                $entityManager->flush();
            } catch (\JsonException|\InvalidArgumentException $exception) {
                $form->get('configurationMapping')->addError(new FormError($exception->getMessage()));

                return $this->renderFormPage($form);
            } catch (UniqueConstraintViolationException) {
                $form->get('version')->addError(new FormError('Cette version existe déjà pour ce produit et cette référence.'));

                return $this->renderFormPage($form);
            }
            $this->addFlash('success', 'Le mapping versionné a été créé et activé.');

            return $this->redirectToRoute('yoowii_admin_sourcing_dashboard');
        }

        return $this->renderFormPage($form);
    }

    #[Route('/mappings/{id}/deactivate', name: 'yoowii_admin_sourcing_mapping_deactivate', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function deactivate(int $id, Request $request, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrf): Response
    {
        $mapping = $entityManager->find(SupplierProductMappingVersion::class, $id);
        if (!$mapping instanceof SupplierProductMappingVersion) {
            throw $this->createNotFoundException();
        }
        if (!$csrf->isTokenValid(new CsrfToken('deactivate_mapping_' . $id, (string) $request->request->get('_token')))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $mapping->deactivate();
        $entityManager->flush();
        $this->addFlash('success', 'Le mapping a été désactivé.');

        return $this->redirectToRoute('yoowii_admin_sourcing_dashboard');
    }

    private function renderFormPage(\Symfony\Component\Form\FormInterface $form): Response
    {
        return $this->render('admin/sourcing/form.html.twig', ['page_title' => 'Nouveau mapping fournisseur', 'form' => $form, 'back_route' => 'yoowii_admin_sourcing_dashboard']);
    }
}
