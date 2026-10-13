<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use Doctrine\ORM\EntityManagerInterface;

/** Repairs fixed and free text options on an existing inactive definition revision. */
final readonly class RealisaprintDraftRepair
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RealisaprintMappingCompleteness $completeness,
    ) {
    }

    /** @return list<array{label: string, variable: string, old: string, new: string, kind: string}> */
    public function preview(SupplierRoute $route, RealisaprintCatalogProduct $catalog, PersistedPrintProductDefinition $definition): array
    {
        $this->assertDraft($definition, $this->latestMapping($route));
        $variables = $catalog->configuration()['variables'] ?? [];
        if (!is_array($variables)) {
            throw new \DomainException('Actualise d’abord la configuration fournisseur.');
        }
        $mappingRules = $this->latestMapping($route)->configurationMapping()['realisaprint']['variables'] ?? [];
        $mappingRules = is_array($mappingRules) ? $mappingRules : [];
        $changes = [];
        foreach ($definition->options() as $code => $option) {
            if (!is_string($code) || !is_array($option)) {
                continue;
            }
            $id = $option['provider_variable'] ?? null;
            $source = is_string($id) ? ($variables[$id] ?? null) : null;
            if (!is_array($source) || 'text' !== ($source['type'] ?? null) || false !== ($source['values'] ?? null)) {
                continue;
            }
            if (true !== ($source['readonly'] ?? false)) {
                $default = is_string($source['default'] ?? null) ? $source['default'] : '';
                if ('text' !== ($option['type'] ?? null) || !is_string($option['default'] ?? null) || '' === trim($option['default']) ||
                    [] !== ($mappingRules[$id]['values'] ?? [])) {
                    $changes[] = [
                        'label' => is_string($option['label'] ?? null) ? $option['label'] : $code,
                        'variable' => $id,
                        'old' => is_scalar($option['default'] ?? null) ? (string) $option['default'] : '—',
                        'new' => $default,
                        'kind' => 'sample',
                    ];
                }
                continue;
            }
            $default = $source['default'] ?? null;
            if (!is_string($default) || '' === trim($default)) {
                throw new \DomainException(sprintf('La variable %s est en lecture seule mais n’a pas de valeur par défaut exploitable.', $id));
            }
            if (($option['allowed_values'] ?? null) === [$default] &&
                ($option['provider_values'][$default] ?? null) === $default &&
                ($option['provider_type'] ?? null) === 'select' &&
                ($mappingRules[$id]['values'] ?? null) === [$default => $default]) {
                continue;
            }
            $changes[] = [
                'label' => is_string($option['label'] ?? null) ? $option['label'] : $code,
                'variable' => $id,
                'old' => is_scalar($option['default'] ?? null) ? (string) $option['default'] : '—',
                'new' => $default,
                'kind' => 'fixed',
            ];
        }

        return $changes;
    }

    /**
     * Returns every default which can be used to build the initial
     * show_variables request. Supplier-imposed values are intentionally not
     * editable: changing them would make the mapping invalid.
     *
     * @return list<array{code: string, label: string, type: string, required: bool, default: string|int|float|null, choices: array<string, string>, minimum: int|float|null, maximum: int|float|null, fixed: bool}>
     */
    public function defaultFields(SupplierRoute $route, PersistedPrintProductDefinition $definition): array
    {
        $this->assertDraft($definition, $this->latestMapping($route));
        $fields = [];
        foreach ($definition->options() as $code => $option) {
            if (!is_string($code) || !is_array($option)) {
                continue;
            }
            $choices = [];
            foreach ($option['value_labels'] ?? [] as $value => $label) {
                if (is_string($value) && is_string($label)) {
                    $choices[$value] = $label;
                }
            }
            $default = $option['default'] ?? null;
            $fields[] = [
                'code' => $code,
                'label' => is_string($option['label'] ?? null) ? $option['label'] : $code,
                'type' => is_string($option['type'] ?? null) ? $option['type'] : 'text',
                'required' => (bool) ($option['required'] ?? true),
                'default' => is_string($default) || is_int($default) || is_float($default) ? $default : null,
                'choices' => $choices,
                'minimum' => is_int($option['minimum'] ?? null) || is_float($option['minimum'] ?? null) ? $option['minimum'] : null,
                'maximum' => is_int($option['maximum'] ?? null) || is_float($option['maximum'] ?? null) ? $option['maximum'] : null,
                'fixed' => true === ($option['fixed'] ?? false),
            ];
        }

        return $fields;
    }

    /** @param array<string, mixed> $samples */
    public function repair(SupplierRoute $route, RealisaprintCatalogProduct $catalog, PersistedPrintProductDefinition $definition, array $samples = [], array $defaults = []): SupplierProductMappingVersion
    {
        $changes = $this->preview($route, $catalog, $definition);
        $previous = $this->latestMapping($route);
        $provider = $previous->configurationMapping()['realisaprint'] ?? null;
        if (!is_array($provider) || !is_array($provider['variables'] ?? null)) {
            throw new \DomainException('Le mapping initial du brouillon est introuvable.');
        }
        $options = $definition->options();
        $rules = $provider['variables'];
        foreach ($changes as $change) {
            $id = $change['variable'];
            $rule = $rules[$id] ?? null;
            $code = is_array($rule) && is_string($rule['option'] ?? null) && isset($options[$rule['option']]) ? $rule['option'] : null;
            if (null === $code) {
                foreach ($options as $candidate => $option) { if (is_array($option) && ($option['provider_variable'] ?? null) === $id) { $code = $candidate; break; } }
            }
            if (!is_string($code)) { throw new DomainException(sprintf('La correspondance de %s est introuvable.', $id)); }
            if ('sample' === $change['kind']) {
                $sample = $samples[$id] ?? $change['new'];
                if (!is_string($sample) || '' === trim($sample) || mb_strlen($sample) > 255) {
                    throw new \DomainException(sprintf('Renseigne une valeur d’essai pour %s.', $change['label']));
                }
                $options[$code]['type'] = 'text';
                $options[$code]['allowed_values'] = [];
                $options[$code]['default'] = trim($sample);
                $options[$code]['provider_type'] = 'text';
                $options[$code]['readonly'] = false;
                $options[$code]['value_labels'] = [];
                $options[$code]['provider_values'] = [];
                $rules[$id] = ['option' => $code, 'values' => []];
                continue;
            }
            $default = $change['new'];
            $options[$code]['type'] = 'text';
            $options[$code]['allowed_values'] = [$default];
            $options[$code]['default'] = $default;
            $options[$code]['value_labels'] = [$default => $default];
            // A single selectable value remains submitted by Symfony, unlike a disabled text field.
            $options[$code]['provider_type'] = 'select';
            $options[$code]['provider_values'] = [$default => $default];
            $options[$code]['fixed'] = true;
            $rules[$id] = ['option' => $code, 'values' => [$default => $default]];
        }
        $this->applyDefaults($route, $definition, $options, $defaults);
        $provider['variables'] = $rules;
        $definition->replaceDraftOptions($options);
        $this->completeness->assertComplete($definition, $catalog->configuration() ?? [], $provider);
        $version = $this->nextVersion($route);
        $mapping = new SupplierProductMappingVersion(
            $route->supplierProduct(),
            $route->yoowiiProductCode(),
            $version,
            ['realisaprint' => $provider],
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            null,
            $definition,
        );
        $mapping->deactivate();
        $this->entityManager->persist($mapping);
        $this->entityManager->flush();

        return $mapping;
    }

    /** @param array<string, array<string, mixed>> $options @param array<string, mixed> $defaults */
    private function applyDefaults(SupplierRoute $route, PersistedPrintProductDefinition $definition, array &$options, array $defaults): void
    {
        $editable = [];
        foreach ($this->defaultFields($route, $definition) as $field) {
            if (!$field['fixed']) {
                $editable[$field['code']] = true;
            }
        }
        foreach ($defaults as $code => $value) {
            if (!is_string($code) || !isset($editable[$code]) || !is_string($value)) {
                continue;
            }
            $options[$code]['default'] = trim($value);
        }

        // Validate submitted defaults through the same domain definitions that
        // validate a storefront configuration, then retain their typed form.
        $configuration = [];
        foreach ($options as $code => $option) {
            $value = $option['default'] ?? null;
            if (is_string($value) || is_int($value) || is_float($value)) {
                $configuration[$code] = $value;
            }
        }
        $previous = $definition->options();
        try {
            $definition->replaceDraftOptions($options);
            $normalized = $definition->definition()->configure($configuration)->toArray();
            foreach ($defaults as $code => $unused) {
                if (is_string($code) && array_key_exists($code, $normalized)) {
                    $options[$code]['default'] = $normalized[$code];
                }
            }
        } catch (\Throwable $exception) {
            $definition->replaceDraftOptions($previous);

            throw $exception;
        }
    }

    private function assertDraft(PersistedPrintProductDefinition $definition, SupplierProductMappingVersion $mapping): void
    {
        if ($definition->active() || (null !== $mapping->printProductDefinition() && $mapping->printProductDefinition() !== $definition)) {
            throw new \DomainException('Seule la dernière révision inactive peut être réparée.');
        }
    }

    private function latestMapping(SupplierRoute $route): SupplierProductMappingVersion
    {
        $mapping = $this->entityManager->getRepository(SupplierProductMappingVersion::class)->findOneBy(
            ['supplierProduct' => $route->supplierProduct(), 'yoowiiProductCode' => $route->yoowiiProductCode()],
            ['id' => 'DESC'],
        );
        if (!$mapping instanceof SupplierProductMappingVersion) {
            throw new \DomainException('Aucun mapping du produit lié n’a été trouvé.');
        }

        return $mapping;
    }

    private function nextVersion(SupplierRoute $route): string
    {
        $max = 0;
        foreach ($this->entityManager->getRepository(SupplierProductMappingVersion::class)->findBy([
            'supplierProduct' => $route->supplierProduct(), 'yoowiiProductCode' => $route->yoowiiProductCode(),
        ]) as $mapping) {
            if (preg_match('/^v(\d+)$/D', $mapping->version(), $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return 'v' . ($max + 1);
    }
}
