<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

use App\Yoowii\PrintProduction\Domain\Model\PrintAsset;
use App\Yoowii\PrintProduction\Domain\PrintPreflightStatus;

/**
 * Technical inspection with a lightweight first pass and an optional PDF engine.
 */
final class InspectPrintArtwork
{
    public function __construct(private readonly ?AdvancedPdfPreflight $advancedPdfPreflight = null)
    {
    }

    /** @param resource $stream
     *  @return array{status: PrintPreflightStatus, report: array<string, mixed>}
     */
    public function __invoke(PrintAsset $asset, mixed $stream): array
    {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('The artwork stream is invalid.');
        }

        $path = tempnam(sys_get_temp_dir(), 'yoowii-preflight-');
        if (false === $path) {
            throw new \RuntimeException('Unable to create a temporary preflight file.');
        }

        try {
            $target = fopen($path, 'wb');
            if (false === $target) {
                throw new \RuntimeException('Unable to prepare the preflight file.');
            }

            try {
                stream_copy_to_stream($stream, $target);
            } finally {
                fclose($target);
            }

            $report = match ($asset->mimeType()) {
                'application/pdf' => $this->inspectPdf($path, $asset),
                'image/jpeg', 'image/png', 'image/tiff' => $this->inspectImage($path, $asset),
                default => ['checks' => [$this->check('unsupported_type', 'error', 'Le type de fichier ne peut pas être contrôlé.')], 'metadata' => []],
            };

            return ['status' => $this->statusFor($report['checks']), 'report' => $report];
        } finally {
            @unlink($path);
        }
    }

    /** @return array{checks: list<array{code: string, severity: string, message: string}>, metadata: array<string, scalar|null>} */
    private function inspectPdf(string $path, PrintAsset $asset): array
    {
        $checks = [];
        $metadata = ['mime_type' => $asset->mimeType(), 'size_bytes' => $asset->size()];
        $handle = fopen($path, 'rb');
        if (false === $handle) {
            throw new \RuntimeException('Unable to read the PDF for preflight.');
        }

        try {
            $header = (string) fread($handle, 8);
            if (!str_starts_with($header, '%PDF-')) {
                return ['checks' => [$this->check('pdf_signature', 'error', 'Le fichier ne possède pas une signature PDF valide.')], 'metadata' => $metadata];
            }
            $checks[] = $this->check('pdf_signature', 'passed', 'Signature PDF valide.');
            rewind($handle);
            $pages = 0;
            $mediaBox = null;
            $tail = '';
            while (!feof($handle)) {
                $chunk = fread($handle, 1024 * 1024);
                if (false === $chunk) {
                    throw new \RuntimeException('Unable to scan the PDF.');
                }
                $text = $tail . $chunk;
                $pages += preg_match_all('/\/Type\s*\/Page\b/', $text);
                if (null === $mediaBox && preg_match('/\/MediaBox\s*\[\s*(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)/', $text, $matches)) {
                    $mediaBox = [(float) $matches[1], (float) $matches[2], (float) $matches[3], (float) $matches[4]];
                }
                $tail = substr($text, -256);
            }
            if (0 === $pages) {
                $checks[] = $this->check('page_count', 'error', 'Aucune page PDF exploitable n’a été détectée.');
            } else {
                $metadata['pages'] = $pages;
                $checks[] = $this->check('page_count', 'passed', sprintf('%d page%s détectée%s.', $pages, 1 < $pages ? 's' : '', 1 < $pages ? 's' : ''));
            }
            if (null === $mediaBox) {
                $checks[] = $this->check('page_dimensions', 'warning', 'Dimensions de page PDF indisponibles : contrôle manuel requis.');
            } else {
                $width = round(abs($mediaBox[2] - $mediaBox[0]) * 25.4 / 72, 2);
                $height = round(abs($mediaBox[3] - $mediaBox[1]) * 25.4 / 72, 2);
                $metadata['width_mm'] = $width;
                $metadata['height_mm'] = $height;
                $checks[] = $this->check('page_dimensions', 'passed', sprintf('Format détecté : %s × %s mm.', $width, $height));
                $this->checkExpectedDimensions($checks, $metadata, $asset, $width, $height);
            }
        } finally {
            fclose($handle);
        }

        if (null !== $this->advancedPdfPreflight) {
            $advanced = $this->advancedPdfPreflight->inspect($path);
            $checks = array_merge($checks, $advanced['checks']);
            $metadata = array_merge($metadata, $advanced['metadata']);
        }

        return ['checks' => $checks, 'metadata' => $metadata];
    }

    /** @return array{checks: list<array{code: string, severity: string, message: string}>, metadata: array<string, scalar|null>} */
    private function inspectImage(string $path, PrintAsset $asset): array
    {
        $checks = [];
        $metadata = ['mime_type' => $asset->mimeType(), 'size_bytes' => $asset->size()];
        $image = @getimagesize($path);
        if (false === $image || !isset($image[0], $image[1])) {
            return ['checks' => [$this->check('image_dimensions', 'error', 'Les dimensions de l’image ne peuvent pas être lues.')], 'metadata' => $metadata];
        }
        $metadata['width_px'] = (int) $image[0];
        $metadata['height_px'] = (int) $image[1];
        $checks[] = $this->check('image_dimensions', 'passed', sprintf('Image détectée : %d × %d pixels.', $image[0], $image[1]));
        $dpi = $this->imageDpi($path, $asset->mimeType());
        if (null === $dpi) {
            $checks[] = $this->check('resolution', 'warning', 'Résolution DPI indisponible : contrôle manuel requis.');
        } elseif ($dpi < 150) {
            $metadata['dpi'] = $dpi;
            $checks[] = $this->check('resolution', 'error', sprintf('Résolution insuffisante : %d dpi (150 dpi minimum).', $dpi));
        } elseif ($dpi < 300) {
            $metadata['dpi'] = $dpi;
            $checks[] = $this->check('resolution', 'warning', sprintf('Résolution de %d dpi : 300 dpi est recommandé.', $dpi));
        } else {
            $metadata['dpi'] = $dpi;
            $checks[] = $this->check('resolution', 'passed', sprintf('Résolution de %d dpi.', $dpi));
            $this->checkExpectedDimensions($checks, $metadata, $asset, round($image[0] * 25.4 / $dpi, 2), round($image[1] * 25.4 / $dpi, 2));
        }

        return ['checks' => $checks, 'metadata' => $metadata];
    }

    /** @param list<array{code: string, severity: string, message: string}> $checks
     *  @param array<string, scalar|null> $metadata
     */
    private function checkExpectedDimensions(array &$checks, array &$metadata, PrintAsset $asset, float $width, float $height): void
    {
        $expected = $this->expectedDimensions($asset);
        if (null === $expected) {
            $checks[] = $this->check('ordered_format', 'warning', 'Le format commandé ne peut pas encore être contrôlé automatiquement.');

            return;
        }
        [$expectedWidth, $expectedHeight, $label] = $expected;
        $metadata['expected_width_mm'] = $expectedWidth;
        $metadata['expected_height_mm'] = $expectedHeight;
        $matchesFinished = $this->sameDimensions($width, $height, $expectedWidth, $expectedHeight, 2.0);
        $hasBleed = $this->sameDimensions($width, $height, $expectedWidth + 6, $expectedHeight + 6, 2.0);
        if ($hasBleed) {
            $checks[] = $this->check('ordered_format', 'passed', sprintf('Format %s avec fond perdu de 3 mm détecté.', $label));
        } elseif ($matchesFinished) {
            $checks[] = $this->check('bleed', 'warning', sprintf('Format %s détecté sans fond perdu de 3 mm.', $label));
        } elseif (!$this->sameDimensions($width, $height, $expectedWidth, $expectedHeight, 12.0)) {
            $checks[] = $this->check('ordered_format', 'error', sprintf('Le format détecté ne correspond pas au format commandé %s.', $label));
        } else {
            $checks[] = $this->check('ordered_format', 'warning', sprintf('Format proche de %s : contrôle manuel du fond perdu requis.', $label));
        }
    }

    /** @return array{float, float, string}|null */
    private function expectedDimensions(PrintAsset $asset): ?array
    {
        $snapshot = $asset->printJob()->productionSnapshot();
        $format = $snapshot['pricing']['configuration']['options']['format'] ?? null;
        if (!is_string($format)) {
            return null;
        }
        $normalized = strtoupper(str_replace([' ', '×'], ['', 'x'], $format));
        $paper = ['A3' => [297.0, 420.0], 'A4' => [210.0, 297.0], 'A5' => [148.0, 210.0], 'A6' => [105.0, 148.0], 'DL' => [99.0, 210.0]];
        if (isset($paper[$normalized])) {
            return [$paper[$normalized][0], $paper[$normalized][1], $normalized];
        }
        if (preg_match('/^(\d+(?:\.\d+)?)X(\d+(?:\.\d+)?)$/', $normalized, $matches)) {
            return [(float) $matches[1], (float) $matches[2], $format];
        }

        return null;
    }

    private function imageDpi(string $path, string $mimeType): ?int
    {
        if ('image/jpeg' === $mimeType && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path, null, true, false);
            $resolution = $exif['IFD0']['XResolution'] ?? null;
            $unit = $exif['IFD0']['ResolutionUnit'] ?? null;
            if (is_string($resolution) && str_contains($resolution, '/')) {
                [$numerator, $denominator] = array_map('floatval', explode('/', $resolution, 2));
                if (0.0 !== $denominator && 2 === (int) $unit) {
                    return (int) round($numerator / $denominator);
                }
            }
        }

        return null;
    }

    private function sameDimensions(float $width, float $height, float $expectedWidth, float $expectedHeight, float $tolerance): bool
    {
        return (abs($width - $expectedWidth) <= $tolerance && abs($height - $expectedHeight) <= $tolerance) ||
            (abs($width - $expectedHeight) <= $tolerance && abs($height - $expectedWidth) <= $tolerance);
    }

    /** @param list<array{code: string, severity: string, message: string}> $checks */
    private function statusFor(array $checks): PrintPreflightStatus
    {
        foreach ($checks as $check) {
            if ('error' === $check['severity']) {
                return PrintPreflightStatus::Failed;
            }
        }
        foreach ($checks as $check) {
            if ('warning' === $check['severity']) {
                return PrintPreflightStatus::Warning;
            }
        }

        return PrintPreflightStatus::Passed;
    }

    /** @return array{code: string, severity: string, message: string} */
    private function check(string $code, string $severity, string $message): array
    {
        return compact('code', 'severity', 'message');
    }
}
