<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application\Import;

use App\Yoowii\Pricing\Domain\Flyer\FlyerConfiguration;
use App\Yoowii\Sourcing\Application\Import\Exception\PricingMatrixCsvImportFailed;
use App\Yoowii\Sourcing\Domain\Model\SupplierPricingMatrixVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;

final class FlyerPricingMatrixCsvImporter
{
    private const MAX_BYTES = 5_000_000;
    private const MAX_ROWS = 50_000;
    private const MAX_REPORTED_ERRORS = 100;

    /** @var list<string> */
    private const HEADERS = [
        'format',
        'sides',
        'paper',
        'grammage',
        'quantity',
        'finishing',
        'production_cost',
        'shipping_cost',
    ];

    public function import(
        SupplierProduct $supplierProduct,
        string $version,
        string $currencyCode,
        \DateTimeImmutable $effectiveFrom,
        \DateTimeImmutable $importedAt,
        string $csv,
    ): FlyerPricingMatrixImportResult {
        $csv = $this->validateDocument($csv);
        $stream = fopen('php://temp/maxmemory:5242880', 'r+');

        if (false === $stream) {
            throw new \RuntimeException('Unable to open the temporary CSV stream.');
        }

        try {
            if (strlen($csv) !== fwrite($stream, $csv)) {
                throw new \RuntimeException('Unable to write the temporary CSV stream.');
            }

            rewind($stream);
            $firstLine = fgets($stream);

            if (false === $firstLine) {
                throw new PricingMatrixCsvImportFailed(['The CSV document is empty.']);
            }

            $delimiter = $this->detectDelimiter($firstLine);
            rewind($stream);
            $header = fgetcsv($stream, null, $delimiter, '"', '');

            if (false === $header) {
                throw new PricingMatrixCsvImportFailed(['The CSV header cannot be read.']);
            }

            $headerIndexes = $this->validateHeader($header);
            $entries = [];
            $errors = [];
            $rowNumber = 1;
            $dataRows = 0;
            $importedRows = 0;

            while (false !== ($row = fgetcsv($stream, null, $delimiter, '"', ''))) {
                ++$rowNumber;

                if ($this->isBlankRow($row)) {
                    continue;
                }

                ++$dataRows;

                if ($dataRows > self::MAX_ROWS) {
                    $this->addError($errors, sprintf('Line %d: the maximum of %d data rows is exceeded.', $rowNumber, self::MAX_ROWS));

                    break;
                }

                if (count($row) !== count($header)) {
                    $this->addError($errors, sprintf('Line %d: expected %d columns, got %d.', $rowNumber, count($header), count($row)));

                    continue;
                }

                try {
                    $configuration = new FlyerConfiguration(
                        $this->cell($row, $headerIndexes, 'format'),
                        $this->cell($row, $headerIndexes, 'sides'),
                        $this->cell($row, $headerIndexes, 'paper'),
                        $this->positiveInteger($row, $headerIndexes, 'grammage'),
                        $this->positiveInteger($row, $headerIndexes, 'quantity'),
                        $this->cell($row, $headerIndexes, 'finishing'),
                    );
                    $productionCost = $this->nonNegativeInteger($row, $headerIndexes, 'production_cost');
                    $shippingCost = $this->nonNegativeInteger($row, $headerIndexes, 'shipping_cost');
                    $key = $configuration->matrixKey();

                    if (isset($entries[$key])) {
                        throw new \InvalidArgumentException(sprintf('duplicate configuration "%s".', $key));
                    }

                    $entries[$key] = [
                        'configuration' => $configuration->toArray(),
                        'production_cost' => $productionCost,
                        'shipping_cost' => $shippingCost,
                    ];
                    ++$importedRows;
                } catch (\InvalidArgumentException $exception) {
                    $this->addError($errors, sprintf('Line %d: %s', $rowNumber, $exception->getMessage()));
                }
            }

            if ([] !== $errors) {
                throw new PricingMatrixCsvImportFailed($errors);
            }

            if ([] === $entries) {
                throw new PricingMatrixCsvImportFailed(['The CSV document contains no pricing row.']);
            }

            $matrix = new SupplierPricingMatrixVersion(
                $supplierProduct,
                $version,
                $currencyCode,
                [
                    'schema_version' => 1,
                    'calculator' => 'print.flyer',
                    'entries' => $entries,
                ],
                $effectiveFrom,
                $importedAt,
            );

            return new FlyerPricingMatrixImportResult($matrix, $importedRows);
        } finally {
            fclose($stream);
        }
    }

    private function validateDocument(string $csv): string
    {
        if ('' === trim($csv)) {
            throw new PricingMatrixCsvImportFailed(['The CSV document is empty.']);
        }

        if (strlen($csv) > self::MAX_BYTES) {
            throw new PricingMatrixCsvImportFailed([sprintf('The CSV document exceeds the %d-byte limit.', self::MAX_BYTES)]);
        }

        if (str_contains($csv, "\0")) {
            throw new PricingMatrixCsvImportFailed(['The CSV document contains a NUL byte.']);
        }

        if (1 !== preg_match('//u', $csv)) {
            throw new PricingMatrixCsvImportFailed(['The CSV document must be valid UTF-8.']);
        }

        return str_starts_with($csv, "\xEF\xBB\xBF") ? substr($csv, 3) : $csv;
    }

    private function detectDelimiter(string $firstLine): string
    {
        $semicolons = substr_count($firstLine, ';');
        $commas = substr_count($firstLine, ',');

        if ($semicolons > $commas) {
            return ';';
        }

        if ($commas > $semicolons) {
            return ',';
        }

        throw new PricingMatrixCsvImportFailed(['Unable to determine whether the CSV delimiter is a comma or semicolon.']);
    }

    /**
     * @param list<string|null> $header
     *
     * @return array<string, int>
     */
    private function validateHeader(array $header): array
    {
        $normalizedHeader = array_map(
            static fn (?string $value): string => strtolower(trim((string) $value)),
            $header,
        );
        $errors = [];

        foreach (self::HEADERS as $requiredHeader) {
            if (!in_array($requiredHeader, $normalizedHeader, true)) {
                $errors[] = sprintf('The required column "%s" is missing.', $requiredHeader);
            }
        }

        foreach ($normalizedHeader as $column) {
            if (!in_array($column, self::HEADERS, true)) {
                $errors[] = sprintf('The column "%s" is not supported.', $column);
            }
        }

        if (count(array_unique($normalizedHeader)) !== count($normalizedHeader)) {
            $errors[] = 'The CSV header contains duplicate columns.';
        }

        if ([] !== $errors) {
            /** @var non-empty-list<string> $errors */
            throw new PricingMatrixCsvImportFailed($errors);
        }

        /** @var array<string, int> $indexes */
        $indexes = array_flip($normalizedHeader);

        return $indexes;
    }

    /** @param list<string|null> $row */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if ('' !== trim((string) $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string|null> $row
     * @param array<string, int> $headerIndexes
     */
    private function cell(array $row, array $headerIndexes, string $column): string
    {
        return strtolower(trim((string) $row[$headerIndexes[$column]]));
    }

    /**
     * @param list<string|null> $row
     * @param array<string, int> $headerIndexes
     */
    private function positiveInteger(array $row, array $headerIndexes, string $column): int
    {
        $value = $this->integer($row, $headerIndexes, $column);

        if ($value < 1) {
            throw new \InvalidArgumentException(sprintf('column "%s" must be greater than zero.', $column));
        }

        return $value;
    }

    /**
     * @param list<string|null> $row
     * @param array<string, int> $headerIndexes
     */
    private function nonNegativeInteger(array $row, array $headerIndexes, string $column): int
    {
        $value = $this->integer($row, $headerIndexes, $column);

        if ($value < 0) {
            throw new \InvalidArgumentException(sprintf('column "%s" must be greater than or equal to zero.', $column));
        }

        return $value;
    }

    /**
     * @param list<string|null> $row
     * @param array<string, int> $headerIndexes
     */
    private function integer(array $row, array $headerIndexes, string $column): int
    {
        $rawValue = trim((string) $row[$headerIndexes[$column]]);

        if (1 !== preg_match('/^-?\d+$/D', $rawValue)) {
            throw new \InvalidArgumentException(sprintf('column "%s" must contain an integer amount, received "%s".', $column, $rawValue));
        }

        $value = filter_var($rawValue, FILTER_VALIDATE_INT);

        if (false === $value) {
            throw new \InvalidArgumentException(sprintf('column "%s" contains an integer outside the supported range.', $column));
        }

        return $value;
    }

    /** @param list<string> $errors */
    private function addError(array &$errors, string $error): void
    {
        if (count($errors) < self::MAX_REPORTED_ERRORS) {
            $errors[] = $error;
        }
    }
}
