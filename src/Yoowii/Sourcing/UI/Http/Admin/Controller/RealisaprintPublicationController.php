<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Application\RealisaprintMappingValidator;
use App\Yoowii\Sourcing\Application\RealisaprintValidationPreview;
use App\Yoowii\Sourcing\Application\RealisaprintPublicationService;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
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
        try {
            $sample = $this->testSample($definition, $validator, $request);
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
            return $this->redirectToRoute('yoowii_admin_realisaprint_mapping_validation', ['id' => $id]);
        }
        $validation = $validator->validate($route, $mapping, $definition, $sample, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $entityManager->persist($validation);
        $entityManager->flush();

        return $this->redirectToRoute('yoowii_admin_realisaprint_mapping_validation', ['id' => $id]);
    }

    #[Route('/realisaprint-publications/mappings/{id}/validation', name: 'yoowii_admin_realisaprint_mapping_validation', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function validation(int $id, EntityManagerInterface $entityManager, RealisaprintValidationPreview $previewBuilder, RealisaprintMappingValidator $validator): Response
    {
        $mapping = $this->mapping($id, $entityManager);
        $validation = $entityManager->getRepository(RealisaprintMappingValidation::class)->findOneBy(['mapping' => $mapping], ['checkedAt' => 'DESC']);
        $catalog = $entityManager->getRepository(RealisaprintCatalogProduct::class)->findOneBy(['providerProductId' => $mapping->supplierProduct()->code()]);
        $definition = $this->definition($mapping, $entityManager);
        $sample = $validation instanceof RealisaprintMappingValidation ? $validation->testConfiguration() : $validator->sample($definition);
        $preview = $previewBuilder->build($definition, $mapping, $catalog instanceof RealisaprintCatalogProduct ? ($catalog->configuration() ?? []) : [], $sample);
        $validUntil = $validation instanceof RealisaprintMappingValidation ? $validation->checkedAt()->modify('+30 minutes') : null;
        $canPublish = $validation instanceof RealisaprintMappingValidation
            && $validation->coverageComplete() && $validation->quotePassed() && $preview['covered']
            && hash_equals($validation->testFingerprint(), $validator->fingerprint($mapping, $validation->testConfiguration()))
            && $validUntil >= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this->render('admin/sourcing/realisaprint_mapping_validation.html.twig', [
            'mapping' => $mapping,
            'route' => $this->route($mapping, $entityManager),
            'validation' => $validation,
            'preview' => $preview,
            'can_publish' => $canPublish,
            'valid_until' => $validUntil,
            'sample' => $sample,
            'numeric_test_fields' => $this->numericTestFields($definition),
        ]);
    }

    #[Route('/realisaprint-publications/mappings/{id}/publish', name: 'yoowii_admin_realisaprint_mapping_publish', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function publish(int $id, Request $request, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrf, RealisaprintPublicationService $publication): Response
    {
        $mapping = $this->mapping($id, $entityManager);
        $this->token($request, $csrf, 'publish_realisaprint_mapping_' . $id);
        try {
            $publication->publish($mapping, $this->route($mapping, $entityManager), new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $this->addFlash('success', 'Publication contrôlée terminée : produit, configurateur, mapping et route sont activés.');
        } catch (\DomainException|\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('yoowii_admin_realisaprint_mapping_validation', ['id' => $id]);
    }

    /**  array<string, string|int|float> */
    private function testSample(PersistedPrintProductDefinition $definition, RealisaprintMappingValidator $validator, Request $request): array
    {
        $sample = $validator->sample($definition);
        $submitted = $request->request->all('test_values');
        if (!is_array($submitted)) {
            throw new \InvalidArgumentException('Les valeurs d’essai sont requises.');
        }
        foreach ($this->numericTestFields($definition) as $code => $field) {
            $value = $submitted[$code] ?? null;
            if (!is_string($value) || '' === trim($value)) {
                throw new \InvalidArgumentException(sprintf('La valeur d’essai « %s » est requise.', $field['label']));
            }
            $sample[$code] = $value;
        }
        $configuration = $definition->definition()->configure($sample);

        return $configuration->toArray();
    }

    /**  array<string, array{label: string, type: string, suggestion: bool}> */
    private function numericTestFields(PersistedPrintProductDefinition $definition): array
    {
        $fields = [];
        foreach ($definition->options() as $code => $option) {
            if (!is_array($option) || [] !== ($option['allowed_values'] ?? []) || !in_array($option['type'] ?? null, ['integer', 'float'], true)) {
                continue;
            }
            $fields[$code] = ['label' => is_string($option['label'] ?? null) ? $option['label'] : $code, 'type' => $option['type'], 'suggestion' => !isset($option['default'])];
        }

        return $fields;
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
