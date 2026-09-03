<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

use Symfony\Component\Process\Process;

/**
 * Runs Poppler and Ghostscript without a shell, so uploaded file names are never
 * interpolated into a command line. The binaries are an explicit deployment
 * prerequisite documented in the lot plan.
 */
final class AdvancedPdfPreflight
{
    /**
     * @return array{checks: list<array{code: string, severity: string, message: string}>, metadata: array<string, scalar|null>}
     */
    public function inspect(string $path): array
    {
        try {
            $pdfInfo = $this->run(['pdfinfo', '-box', $path]);
            $fonts = $this->run(['pdffonts', $path]);
            $images = $this->run(['pdfimages', '-list', $path]);
            $inkCoverage = $this->run(['gs', '-dSAFER', '-q', '-o', '-', '-sDEVICE=inkcov', $path]);
        } catch (\Throwable) {
            return [
                'checks' => [$this->check('prepress_engine', 'error', 'Le moteur prépresse PDF est indisponible : contrôle impossible.')],
                'metadata' => ['prepress_engine' => 'unavailable'],
            ];
        }

        $checks = [];
        $metadata = ['prepress_engine' => 'poppler_ghostscript'];
        $pdfVersion = $this->value($pdfInfo, 'PDF version');
        if (null !== $pdfVersion) {
            $metadata['pdf_version'] = $pdfVersion;
            $checks[] = $this->check('pdf_version', 'passed', sprintf('PDF version %s analysé.', $pdfVersion));
        }
        if ('yes' === strtolower((string) $this->value($pdfInfo, 'Encrypted'))) {
            $checks[] = $this->check('encryption', 'error', 'Le PDF est chiffré et ne peut pas être produit.');
        } else {
            $checks[] = $this->check('encryption', 'passed', 'PDF non chiffré.');
        }

        $this->inspectPdfBoxes($pdfInfo, $checks, $metadata);
        $this->inspectFonts($fonts, $checks, $metadata);
        $this->inspectImages($images, $checks, $metadata);
        $this->inspectColourCoverage($inkCoverage, $checks, $metadata);
        $this->inspectPdfStandard($path, $checks, $metadata);

        return ['checks' => $checks, 'metadata' => $metadata];
    }

    /** @param list<array{code: string, severity: string, message: string}> $checks
     *  @param array<string, scalar|null> $metadata
     */
    private function inspectPdfBoxes(string $pdfInfo, array &$checks, array &$metadata): void
    {
        $trim = $this->box($pdfInfo, 'TrimBox');
        $bleed = $this->box($pdfInfo, 'BleedBox');
        if (null === $trim) {
            $checks[] = $this->check('trim_box', 'warning', 'TrimBox absent : le format fini doit être contrôlé par un opérateur.');
        } else {
            [$width, $height] = $this->dimensionsMm($trim);
            $metadata['trim_width_mm'] = $width;
            $metadata['trim_height_mm'] = $height;
            $sameAsBleed = null !== $bleed && $this->sameBox($trim, $bleed);
            $checks[] = $this->check(
                'trim_box',
                $sameAsBleed ? 'warning' : 'passed',
                $sameAsBleed ? 'TrimBox et BleedBox identiques : contrôle du format fini recommandé.' : sprintf('TrimBox détectée : %s × %s mm.', $width, $height),
            );
        }
        if (null === $bleed) {
            $checks[] = $this->check('bleed_box', 'warning', 'BleedBox absente : le fond perdu doit être contrôlé par un opérateur.');
        } else {
            [$width, $height] = $this->dimensionsMm($bleed);
            $metadata['bleed_width_mm'] = $width;
            $metadata['bleed_height_mm'] = $height;
            $checks[] = $this->check('bleed_box', 'passed', sprintf('BleedBox détectée : %s × %s mm.', $width, $height));
        }
    }

    /** @param list<array{code: string, severity: string, message: string}> $checks
     *  @param array<string, scalar|null> $metadata
     */
    private function inspectFonts(string $output, array &$checks, array &$metadata): void
    {
        $fontRows = [];
        $afterSeparator = false;
        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            if (str_starts_with(trim($line), '---')) {
                $afterSeparator = true;

                continue;
            }
            if (!$afterSeparator || '' === trim($line)) {
                continue;
            }
            if (preg_match('/^(.*?)\s{2,}.*?\s{2,}(yes|no)\s+(yes|no)\s+(yes|no)\s+\d+\s+\d+\s*$/i', $line, $matches)) {
                $fontRows[] = ['name' => trim($matches[1]), 'embedded' => 'yes' === strtolower($matches[2])];
            }
        }
        $metadata['font_count'] = count($fontRows);
        $missing = array_values(array_filter($fontRows, static fn (array $font): bool => !$font['embedded']));
        if ([] !== $missing) {
            $metadata['non_embedded_fonts'] = count($missing);
            $checks[] = $this->check('embedded_fonts', 'error', sprintf('%d police%s non incorporée%s : exporte un PDF avec les polices incorporées.', count($missing), 1 < count($missing) ? 's' : '', 1 < count($missing) ? 's' : ''));
        } elseif ([] === $fontRows) {
            $checks[] = $this->check('embedded_fonts', 'passed', 'Aucune police PDF détectée.');
        } else {
            $checks[] = $this->check('embedded_fonts', 'passed', sprintf('%d police%s incorporée%s.', count($fontRows), 1 < count($fontRows) ? 's' : '', 1 < count($fontRows) ? 's' : ''));
        }
    }

    /** @param list<array{code: string, severity: string, message: string}> $checks
     *  @param array<string, scalar|null> $metadata
     */
    private function inspectImages(string $output, array &$checks, array &$metadata): void
    {
        $resolutions = [];
        $afterSeparator = false;
        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            if (str_starts_with(trim($line), '---')) {
                $afterSeparator = true;

                continue;
            }
            if (!$afterSeparator || !preg_match('/\s(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)\s+\S+\s+\d+\s+\S+\s*$/', $line, $matches)) {
                continue;
            }
            $resolutions[] = min((float) $matches[1], (float) $matches[2]);
        }
        $metadata['embedded_image_count'] = count($resolutions);
        if ([] === $resolutions) {
            $checks[] = $this->check('embedded_image_resolution', 'passed', 'Aucune image matricielle intégrée détectée.');

            return;
        }
        $minimum = (int) floor(min($resolutions));
        $metadata['minimum_embedded_image_dpi'] = $minimum;
        if ($minimum < 150) {
            $checks[] = $this->check('embedded_image_resolution', 'error', sprintf('Une image intégrée est à %d dpi : 150 dpi minimum requis.', $minimum));
        } elseif ($minimum < 300) {
            $checks[] = $this->check('embedded_image_resolution', 'warning', sprintf('Image intégrée à %d dpi : 300 dpi est recommandé.', $minimum));
        } else {
            $checks[] = $this->check('embedded_image_resolution', 'passed', sprintf('Images intégrées : minimum %d dpi.', $minimum));
        }
    }

    /** @param list<array{code: string, severity: string, message: string}> $checks
     *  @param array<string, scalar|null> $metadata
     */
    private function inspectColourCoverage(string $output, array &$checks, array &$metadata): void
    {
        if (!preg_match('/^\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+CMYK/m', $output, $matches)) {
            $checks[] = $this->check('colour_coverage', 'warning', 'Couverture CMJN indisponible : contrôle manuel requis.');

            return;
        }
        $coverage = array_map('floatval', array_slice($matches, 1, 4));
        $metadata['cmyk_coverage'] = implode(',', array_map(static fn (float $value): string => number_format($value, 5, '.', ''), $coverage));
        if (0.0 === array_sum($coverage)) {
            $checks[] = $this->check('colour_coverage', 'warning', 'Aucune couverture CMJN détectée : vérifie le contenu du document.');
        } elseif (0.0 === $coverage[0] && 0.0 === $coverage[1] && 0.0 === $coverage[2]) {
            $checks[] = $this->check('colour_coverage', 'warning', 'Document monochrome détecté : vérifie que le noir seul est intentionnel.');
        } else {
            $checks[] = $this->check('colour_coverage', 'passed', 'Couverture CMJN détectée.');
        }
    }

    /** @param list<array{code: string, severity: string, message: string}> $checks
     *  @param array<string, scalar|null> $metadata
     */
    private function inspectPdfStandard(string $path, array &$checks, array &$metadata): void
    {
        $sample = file_get_contents($path, false, null, 0, 5 * 1024 * 1024);
        if (false === $sample) {
            $checks[] = $this->check('output_intent', 'warning', 'Profil de sortie PDF indisponible : contrôle manuel requis.');

            return;
        }
        $pdfX = str_contains($sample, 'GTS_PDFX') || str_contains($sample, 'PDF/X');
        $outputIntent = str_contains($sample, '/OutputIntent');
        $metadata['pdf_x'] = $pdfX ? 'yes' : 'no';
        $metadata['output_intent'] = $outputIntent ? 'yes' : 'no';
        if ($pdfX) {
            $checks[] = $this->check('pdf_standard', 'passed', 'Standard PDF/X détecté.');
        } elseif ($outputIntent) {
            $checks[] = $this->check('output_intent', 'passed', 'Profil de sortie PDF détecté.');
        } else {
            $checks[] = $this->check('output_intent', 'warning', 'Aucun profil de sortie PDF/X détecté : contrôle colorimétrique recommandé.');
        }
    }

    /** @return list<float>|null */
    private function box(string $output, string $name): ?array
    {
        if (!preg_match('/^' . preg_quote($name, '/') . ':\s*(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)/m', $output, $matches)) {
            return null;
        }

        return array_map('floatval', array_slice($matches, 1));
    }

    /** @param list<float> $box
     *  @return array{float, float}
     */
    private function dimensionsMm(array $box): array
    {
        return [round(abs($box[2] - $box[0]) * 25.4 / 72, 2), round(abs($box[3] - $box[1]) * 25.4 / 72, 2)];
    }

    /** @param list<float> $first
     *  @param list<float> $second
     */
    private function sameBox(array $first, array $second): bool
    {
        foreach ($first as $index => $value) {
            if (abs($value - $second[$index]) > 0.01) {
                return false;
            }
        }

        return true;
    }

    private function value(string $output, string $name): ?string
    {
        if (!preg_match('/^' . preg_quote($name, '/') . ':\s*(.+)$/mi', $output, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }

    /** @param list<string> $command */
    private function run(array $command): string
    {
        $process = new Process($command);
        $process->setTimeout(30);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        return $process->getOutput();
    }

    /** @return array{code: string, severity: string, message: string} */
    private function check(string $code, string $severity, string $message): array
    {
        return compact('code', 'severity', 'message');
    }
}
