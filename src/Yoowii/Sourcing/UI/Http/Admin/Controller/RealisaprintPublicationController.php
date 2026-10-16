<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\PrintProduction\Infrastructure\Realisaprint\RealisaprintClient;
use App\Yoowii\Sourcing\Application\RealisaprintInitialDisplayStateBuilder;
use App\Yoowii\Sourcing\Application\RealisaprintMappingValidator;
use App\Yoowii\Sourcing\Application\RealisaprintPublicationService;
use App\Yoowii\Sourcing\Application\RealisaprintValidationPreview;
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
            $sample = $this->testSample($definition, $validator, $request, $mapping);
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('yoowii_admin_realisaprint_mapping_validation', ['id' => $id]);
        }
        $validation = $validator->validate($route, $mapping, $definition, $sample, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $entityManager->persist($validation);
        $entityManager->flush();

        $flashType = $validation->quotePassed() ? 'realisaprint_test_success' : (null !== $validation->priceApiDiagnostic() ? 'realisaprint_test_error' : 'error');
        $this->addFlash(
            $flashType,
            $validation->quotePassed()
                ? 'Le contrôle du prix API est terminé avec succès.'
                : 'Le contrôle du prix API a échoué. Consultez le résultat du contrôle.',
        );

        return $this->redirectToRoute('yoowii_admin_realisaprint_mapping_validation', ['id' => $id]);
    }

    #[Route('/realisaprint-publications/mappings/{id}/display-preview', name: 'yoowii_admin_realisaprint_mapping_display_preview', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function displayPreview(int $id, Request $request, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrf, RealisaprintInitialDisplayStateBuilder $builder): Response
    {
        $mapping = $this->mapping($id, $entityManager);
        $this->token($request, $csrf, 'preview_realisaprint_mapping_' . $id);
        $state = $builder->build($mapping, $this->definition($mapping, $entityManager), new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $mapping->storeInitialDisplayState($state);
        $entityManager->flush();
        $this->addFlash(isset($state['error']) ? 'warning' : 'success', isset($state['error']) ? 'L’aperçu fournisseur a échoué : le storefront utilisera le formulaire statique publié.' : 'L’aperçu du configurateur a été actualisé sans lancer de cotation.');

        return $this->redirectToRoute('yoowii_admin_realisaprint_mapping_validation', ['id' => $id]);
    }

    #[Route('/realisaprint-publications/mappings/{id}/validation', name: 'yoowii_admin_realisaprint_mapping_validation', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function validation(int $id, EntityManagerInterface $entityManager, RealisaprintValidationPreview $previewBuilder, RealisaprintMappingValidator $validator, RealisaprintClient $client): Response
    {
        $mapping = $this->mapping($id, $entityManager);
        $validation = $entityManager->getRepository(RealisaprintMappingValidation::class)->findOneBy(['mapping' => $mapping], ['checkedAt' => 'DESC']);
        $catalog = $entityManager->getRepository(RealisaprintCatalogProduct::class)->findOneBy(['providerProductId' => $mapping->supplierProduct()->code()]);
        $definition = $this->definition($mapping, $entityManager);
        $sample = $this->withFixedValues($validator->sample($definition), $mapping);
        if ($validation instanceof RealisaprintMappingValidation) {
            $sample = array_replace($sample, $validation->testConfiguration());
        }
        $preview = $previewBuilder->build($definition, $mapping, $catalog instanceof RealisaprintCatalogProduct ? ($catalog->configuration() ?? []) : [], $sample);
        $validUntil = $validation instanceof RealisaprintMappingValidation ? $validation->checkedAt()->modify('+30 minutes') : null;
        $canPublish = $validation instanceof RealisaprintMappingValidation &&
            $validation->coverageComplete() && $validation->quotePassed() && $preview['covered'] &&
            hash_equals($validation->testFingerprint(), $validator->fingerprint($mapping, $validation->testConfiguration())) &&
            $validUntil >= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $initialState = $mapping->initialDisplayState();
        $showVariablesRequest = is_array($initialState) && is_array($initialState['show_variables_request'] ?? null)
            ? $initialState['show_variables_request']
            : null;
        $priceDiagnostic = $validation instanceof RealisaprintMappingValidation ? $validation->priceApiDiagnostic() : null;
        $priceCalls = is_array($priceDiagnostic) && is_array($priceDiagnostic['calls'] ?? null) ? $priceDiagnostic['calls'] : [];
        $priceDiagnostics = [];
        foreach ($priceCalls as $call) {
            if (!is_array($call) || !is_string($call['operation'] ?? null) || !is_array($call['request'] ?? null)) {
                continue;
            }
            $priceDiagnostics[] = $call + ['curl' => $client->diagnosticCurl($call['operation'], $this->diagnosticParameters($call['request']))];
        }

        return $this->render('admin/sourcing/realisaprint_mapping_validation.html.twig', [
            'mapping' => $mapping,
            'route' => $this->route($mapping, $entityManager),
            'catalog_product' => $catalog instanceof RealisaprintCatalogProduct ? $catalog : null,
            'validation' => $validation,
            'preview' => $preview,
            'can_publish' => $canPublish,
            'valid_until' => $validUntil,
            'sample' => $sample,
            'test_fields' => $this->testFields($definition, $mapping),
            'initial_display_state' => $initialState,
            'api_diagnostic_curl' => is_array($showVariablesRequest) ? $client->diagnosticCurl('show_variables', $this->diagnosticParameters($showVariablesRequest)) : null,
            'price_api_diagnostics' => $priceDiagnostics,
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

    /** @return array<string, string|int|float> */
    private function testSample(PersistedPrintProductDefinition $definition, RealisaprintMappingValidator $validator, Request $request, SupplierProductMappingVersion $mapping): array
    {
        $sample = $this->withFixedValues($validator->sample($definition), $mapping);
        $submitted = $request->request->all('test_values');
        $fields = $this->testFields($definition, $mapping);
        foreach ($fields as $code => $field) {
            if (!$field['editable']) {
                continue;
            }
            if (array_key_exists($code, $submitted) && (is_string($submitted[$code]) || is_int($submitted[$code]) || is_float($submitted[$code]))) {
                $sample[$code] = $submitted[$code];
            }
        }

        return $this->withFixedValues($sample, $mapping);
    }

    /**
     * @param array<string, string|int|float> $values
     *
     * @return array<string, string|int|float>
     */
    private function withFixedValues(array $values, SupplierProductMappingVersion $mapping): array
    {
        $configuration = $mapping->configurationMapping();
        $realisaprint = $configuration['realisaprint'] ?? [];
        if (!is_array($realisaprint)) {
            return $values;
        }
        $rules = $realisaprint['variables'] ?? [];
        if (!is_array($rules)) {
            return $values;
        }
        foreach ($rules as $rule) {
            if (!is_array($rule) || !is_string($rule['option'] ?? null) || !is_scalar($rule['fixed_value'] ?? null)) {
                continue;
            }
            $ruleValues = is_array($rule['values'] ?? null) ? $rule['values'] : [];
            foreach ($ruleValues as $canonical => $provider) {
                if (!is_scalar($provider)) {
                    continue;
                }
                if ((string) $provider === (string) $rule['fixed_value']) {
                    $values[$rule['option']] = ctype_digit((string) $canonical) ? (int) $canonical : (string) $canonical;

                    continue 2;
                }
            }

            throw new \InvalidArgumentException(sprintf('La valeur fixe de « %s » ne correspond pas au mapping publié.', $rule['option']));
        }

        return $values;
    }

    /** @return array<string, array{label: string, control: 'select'|'textarea'|'input', input_type: 'text'|'number', choices: array<string, string>, editable: bool, fixed_label: string|null}> */
    private function testFields(PersistedPrintProductDefinition $definition, SupplierProductMappingVersion $mapping): array
    {
        $fixed = $this->withFixedValues([], $mapping);
        $fields = [];
        foreach ($definition->options() as $code => $option) {
            $choices = [];
            $allowedValues = $option['allowed_values'] ?? [];
            $valueLabels = is_array($option['value_labels'] ?? null) ? $option['value_labels'] : [];
            if (!is_array($allowedValues)) {
                $allowedValues = [];
            }
            foreach ($allowedValues as $value) {
                if (!is_string($value) && !is_int($value)) {
                    continue;
                }
                $key = (string) $value;
                $choices[$key] = is_string($valueLabels[$key] ?? null) ? $valueLabels[$key] : $key;
            }
            $type = is_string($option['type'] ?? null) ? $option['type'] : 'text';
            $providerType = is_string($option['provider_type'] ?? null) ? $option['provider_type'] : null;
            $control = 'input';
            if ('textarea' === $providerType || 'textarea' === $type) {
                $control = 'textarea';
            } elseif ('select' === $providerType || [] !== $choices || in_array($type, ['code', 'checkbox'], true)) {
                // A checkbox option has canonical values such as oui/non. A select
                // keeps both values explicit, including the supplier default.
                $control = 'select';
            }
            $fields[$code] = [
                'label' => is_string($option['label'] ?? null) ? $option['label'] : $code,
                'control' => $control,
                'input_type' => in_array($type, ['integer', 'float'], true) ? 'number' : 'text',
                'choices' => $choices,
                'editable' => !array_key_exists($code, $fixed),
                'fixed_label' => array_key_exists($code, $fixed) ? (string) $fixed[$code] : null,
            ];
        }

        return $fields;
    }

    /**
     * @param array<mixed, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    private function diagnosticParameters(array $parameters): array
    {
        $namedParameters = [];
        foreach ($parameters as $name => $value) {
            if (is_string($name)) {
                $namedParameters[$name] = $value;
            }
        }

        return $namedParameters;
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
        $definition = $mapping->printProductDefinition();
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
