<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Domain\Print;

/**
 * Versioned, product-specific contract between a Prescript document and the
 * Yoowii configuration summary. It deliberately accepts field identifiers,
 * not arbitrary selectors or supplier markup.
 */
final readonly class PrescriptSummaryProfile
{
    /** @param list<array{code: string, label: string, input_id?: string, input_name?: string, required: bool}> $fields */
    private function __construct(private array $fields)
    {
    }

    /** @param array<string, mixed> $configurationMapping */
    public static function tryFromConfigurationMapping(array $configurationMapping): ?self
    {
        $provider = $configurationMapping['realisaprint'] ?? null;
        if (!is_array($provider) || ConfiguratorKind::Prescript !== ConfiguratorKind::fromConfigurationMapping($configurationMapping)) {
            return null;
        }

        $profile = $provider['prescript_summary_profile'] ?? null;
        if (!is_array($profile)) {
            return null;
        }

        try {
            return self::fromArray($profile);
        } catch (\InvalidArgumentException) {
            // Storefront availability must not depend on an optional UI profile.
            return null;
        }
    }

    /** @param array<string, mixed> $profile */
    public static function fromArray(array $profile): self
    {
        if ('v1' !== ($profile['version'] ?? null)) {
            throw new \InvalidArgumentException('Le profil de résumé Préscript doit utiliser la version "v1".');
        }
        if (!is_array($profile['fields'] ?? null) || !array_is_list($profile['fields'])) {
            throw new \InvalidArgumentException('Le profil de résumé Préscript doit contenir une liste "fields".');
        }

        $fields = [];
        $codes = [];
        foreach ($profile['fields'] as $field) {
            if (!is_array($field)) {
                throw new \InvalidArgumentException('Chaque champ du profil Préscript doit être un objet.');
            }
            $code = $field['code'] ?? null;
            $label = $field['label'] ?? null;
            $inputId = $field['input_id'] ?? null;
            $inputName = $field['input_name'] ?? null;
            if (!is_string($code) || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $code) || !is_string($label) || '' === trim($label)) {
                throw new \InvalidArgumentException('Chaque champ Préscript requiert un code et un libellé valides.');
            }
            if (isset($codes[$code])) {
                throw new \InvalidArgumentException(sprintf('Le code de champ Préscript "%s" est dupliqué.', $code));
            }
            if ((!is_string($inputId) || '' === $inputId) && (!is_string($inputName) || '' === $inputName)) {
                throw new \InvalidArgumentException(sprintf('Le champ Préscript "%s" requiert input_id ou input_name.', $code));
            }
            if (is_string($inputId) && !preg_match('/^[A-Za-z][A-Za-z0-9_.:-]{0,127}$/D', $inputId)) {
                throw new \InvalidArgumentException(sprintf('input_id du champ Préscript "%s" est invalide.', $code));
            }
            if (is_string($inputName) && !preg_match('/^[A-Za-z][A-Za-z0-9_.:-]{0,127}$/D', $inputName)) {
                throw new \InvalidArgumentException(sprintf('input_name du champ Préscript "%s" est invalide.', $code));
            }
            if (count($fields) >= 20) {
                throw new \InvalidArgumentException('Le profil Préscript ne peut pas contenir plus de 20 champs.');
            }

            $normalized = ['code' => $code, 'label' => trim($label), 'required' => false !== ($field['required'] ?? true)];
            if (is_string($inputId) && '' !== $inputId) {
                $normalized['input_id'] = $inputId;
            }
            if (is_string($inputName) && '' !== $inputName) {
                $normalized['input_name'] = $inputName;
            }
            $fields[] = $normalized;
            $codes[$code] = true;
        }

        return new self($fields);
    }

    /** @return array{version: 'v1', fields: list<array{code: string, label: string, input_id?: string, input_name?: string, required: bool}>} */
    public function toArray(): array
    {
        return ['version' => 'v1', 'fields' => $this->fields];
    }
}
