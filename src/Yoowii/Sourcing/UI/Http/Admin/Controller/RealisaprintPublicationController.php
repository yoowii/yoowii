<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Application\RealisaprintMappingValidator;
use App\Yoowii\Sourcing\Application\RealisaprintPublicationService;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintMappingValidation;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class RealisaprintPublicationController extends AbstractController
{
    #[Route('/realisaprint-publications/mappings/{id}/validate', name: 'yoowii_admin_realisaprint_mapping_validate', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function validate(int $id, Request $request, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrf, RealisaprintMappingValidator $validator): Response
    {
        $mapping = $this->mapping($id, $entityManager);
        $this->token($request, $csrf, 'validate_realisaprint_mapping_' . $id);
        $route = $this->route($mapping, $entityManager);
        $definition = $this->definition($mapping, $entityManager);
        $validation = $validator->validate($route, $mapping, $definition, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $entityManager->persist($validation);
        $entityManager->flush();

        return $this->redirectToRoute('yoowii_admin_realisaprint_mapping_validation', ['id' => $id]);
    }

    #[Route('/realisaprint-publications/mappings/{id}/validation', name: 'yoowii_admin_realisaprint_mapping_validation', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function validation(int $id, EntityManagerInterface $entityManager): Response
    {
        $mapping = $this->mapping($id, $entityManager);
        $validation = $entityManager->getRepository(RealisaprintMappingValidation::class)->findOneBy(['mapping' => $mapping], ['checkedAt' => 'DESC']);

        return $this->render('admin/sourcing/realisaprint_mapping_validation.html.twig', ['mapping' => $mapping, 'validation' => $validation]);
    }

    #[Route('/realisaprint-publications/mappings/{id}/publish', name: 'yoowii_admin_realisaprint_mapping_publish', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function publish(int $id, Request $request, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrf, RealisaprintPublicationService $publication): Response
    {
        $mapping = $this->mapping($id, $entityManager);
        $this->token($request, $csrf, 'publish_realisaprint_mapping_' . $id);
        try {
            $publication->publish($mapping, $this->route($mapping, $entityManager), new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $this->addFlash('success', 'Publication contrôlée terminée : produit, configurateur, mapping et route sont activés.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('yoowii_admin_realisaprint_mapping_validation', ['id' => $id]);
    }

    private function mapping(int $id, EntityManagerInterface $entityManager): SupplierProductMappingVersion
    {
        $mapping = $entityManager->find(SupplierProductMappingVersion::class, $id);
        if (!$mapping instanceof SupplierProductMappingVersion || 'realisaprint' !== $mapping->supplierProduct()->supplier()->code()) {
            throw $this->createNotFoundException();
        }

        return $mapping;
    }

    private function route(SupplierProductMappingVersion $mapping, EntityManagerInterface $entityManager): SupplierRoute
    {
        $route = $entityManager->getRepository(SupplierRoute::class)->findOneBy(['supplierProduct' => $mapping->supplierProduct(), 'yoowiiProductCode' => $mapping->yoowiiProductCode()], ['id' => 'ASC']);
        if (!$route instanceof SupplierRoute) {
            throw new \DomainException('La route brouillon correspondante est introuvable.');
        }

        return $route;
    }

    private function definition(SupplierProductMappingVersion $mapping, EntityManagerInterface $entityManager): PersistedPrintProductDefinition
    {
        $definition = $entityManager->getRepository(PersistedPrintProductDefinition::class)->findOneBy(['productCode' => $mapping->yoowiiProductCode()]);
        if (!$definition instanceof PersistedPrintProductDefinition) {
            throw new \DomainException('La définition de configurateur correspondante est introuvable.');
        }

        return $definition;
    }

    private function token(Request $request, CsrfTokenManagerInterface $csrf, string $id): void
    {
        if (!$csrf->isTokenValid(new CsrfToken($id, (string) $request->request->get('_token')))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
