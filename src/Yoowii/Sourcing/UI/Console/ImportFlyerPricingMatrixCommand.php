<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Console;

use App\Yoowii\Sourcing\Application\Import\Exception\PricingMatrixCsvImportFailed;
use App\Yoowii\Sourcing\Application\Import\FlyerPricingMatrixCsvImporter;
use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\Domain\Model\SupplierPricingMatrixVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'yoowii:sourcing:import-flyer-matrix',
    description: 'Import a versioned flyer pricing matrix for a print supplier.',
)]
final class ImportFlyerPricingMatrixCommand extends Command
{
    private const MAX_FILE_BYTES = 5_000_000;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FlyerPricingMatrixCsvImporter $importer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('supplier', InputArgument::REQUIRED, 'Stable supplier code, for example laboprint.')
            ->addArgument('supplier-product', InputArgument::REQUIRED, 'Supplier product code, for example FLYER_STANDARD.')
            ->addArgument('version', InputArgument::REQUIRED, 'Immutable matrix version, for example 2026-09-01.')
            ->addArgument('effective-from', InputArgument::REQUIRED, 'Effective date in YYYY-MM-DD format.')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the canonical CSV file.')
            ->addOption('currency', null, InputOption::VALUE_REQUIRED, 'Three-letter currency code.', 'EUR')
            ->addOption('activate', null, InputOption::VALUE_NONE, 'Activate the imported version immediately.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $supplierCode = $this->stringArgument($input, 'supplier');
        $supplierProductCode = $this->stringArgument($input, 'supplier-product');
        $version = $this->stringArgument($input, 'version');
        $file = $this->stringArgument($input, 'file');
        $currencyOption = $input->getOption('currency');
        $currencyCode = is_string($currencyOption) ? strtoupper($currencyOption) : '';

        try {
            $effectiveFrom = $this->dateArgument($input, 'effective-from');
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return self::FAILURE;
        }

        $supplier = $this->entityManager->getRepository(PrintSupplier::class)->findOneBy(['code' => $supplierCode]);

        if (!$supplier instanceof PrintSupplier) {
            $io->error(sprintf('Unknown print supplier "%s".', $supplierCode));

            return self::FAILURE;
        }

        $supplierProduct = $this->entityManager->getRepository(SupplierProduct::class)->findOneBy([
            'supplier' => $supplier,
            'code' => $supplierProductCode,
        ]);

        if (!$supplierProduct instanceof SupplierProduct) {
            $io->error(sprintf('Unknown product "%s" for supplier "%s".', $supplierProductCode, $supplierCode));

            return self::FAILURE;
        }

        $existingVersion = $this->entityManager->getRepository(SupplierPricingMatrixVersion::class)->findOneBy([
            'supplierProduct' => $supplierProduct,
            'version' => $version,
        ]);

        if (null !== $existingVersion) {
            $io->error(sprintf('Matrix version "%s" already exists for this supplier product.', $version));

            return self::FAILURE;
        }

        if (!is_file($file) || !is_readable($file)) {
            $io->error(sprintf('The CSV file "%s" is not a readable regular file.', $file));

            return self::FAILURE;
        }

        $fileSize = filesize($file);

        if (false === $fileSize || $fileSize > self::MAX_FILE_BYTES) {
            $io->error(sprintf('The CSV file must not exceed %d bytes.', self::MAX_FILE_BYTES));

            return self::FAILURE;
        }

        $csv = file_get_contents($file);

        if (false === $csv) {
            $io->error(sprintf('Unable to read CSV file "%s".', $file));

            return self::FAILURE;
        }

        $importedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        try {
            $result = $this->importer->import(
                $supplierProduct,
                $version,
                $currencyCode,
                $effectiveFrom,
                $importedAt,
                $csv,
            );
        } catch (PricingMatrixCsvImportFailed $exception) {
            $io->error($exception->getMessage());
            $io->listing($exception->errors());

            return self::FAILURE;
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return self::FAILURE;
        }

        if ((bool) $input->getOption('activate')) {
            $result->matrix()->activate($importedAt);
        }

        $this->entityManager->persist($result->matrix());
        $this->entityManager->flush();

        $io->success(sprintf(
            'Imported %d flyer prices as version "%s" (%s).',
            $result->importedRows(),
            $version,
            $result->matrix()->status()->value,
        ));

        return self::SUCCESS;
    }

    private function stringArgument(InputInterface $input, string $name): string
    {
        $value = $input->getArgument($name);

        return is_string($value) ? $value : '';
    }

    private function dateArgument(InputInterface $input, string $name): \DateTimeImmutable
    {
        $value = $this->stringArgument($input, $name);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();

        if (
            false === $date
            || (false !== $errors && (0 !== $errors['warning_count'] || 0 !== $errors['error_count']))
            || $date->format('Y-m-d') !== $value
        ) {
            throw new \InvalidArgumentException(sprintf('Argument "%s" must use the YYYY-MM-DD format.', $name));
        }

        return $date;
    }
}
