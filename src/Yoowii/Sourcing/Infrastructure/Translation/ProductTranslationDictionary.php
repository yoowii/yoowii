<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Infrastructure\Translation;

/** Immutable local translations collected from the synchronized Realisaprint catalogue. */
final readonly class ProductTranslationDictionary
{
    /** @var array{products: array<string, string>, stocks: array<string, string>} */
    private array $translations;

    public function __construct(string $dictionaryPath)
    {
        $contents = @file_get_contents($dictionaryPath);
        if (false === $contents) {
            throw new \RuntimeException(sprintf('The product translation dictionary "%s" cannot be read.', $dictionaryPath));
        }

        try {
            $decoded = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('The product translation dictionary is invalid JSON.', previous: $exception);
        }

        $this->translations = $this->validate($decoded);
    }

    public function product(string $label): ?string
    {
        return $this->translations['products'][$label] ?? null;
    }

    public function stock(string $label): ?string
    {
        return $this->translations['stocks'][$label] ?? null;
    }

    /** @return array{products: array<string, string>, stocks: array<string, string>} */
    private function validate(mixed $decoded): array
    {
        if (!is_array($decoded)) {
            throw new \RuntimeException('The product translation dictionary must be a JSON object.');
        }

        $result = ['products' => [], 'stocks' => []];
        foreach (array_keys($result) as $section) {
            $entries = $decoded[$section] ?? null;
            if (!is_array($entries)) {
                throw new \RuntimeException(sprintf('The product translation dictionary section "%s" is missing or invalid.', $section));
            }
            foreach ($entries as $source => $translation) {
                if (!is_string($source) || !is_string($translation) || '' === trim($source) || '' === trim($translation)) {
                    throw new \RuntimeException(sprintf('The product translation dictionary section "%s" contains an invalid entry.', $section));
                }
                $result[$section][$source] = $translation;
            }
        }

        return $result;
    }
}
