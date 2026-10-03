<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\UI\Console;

use App\Yoowii\Pricing\Application\BuiltInPrintProductDefinitionRegistry;
use App\Yoowii\Pricing\Application\PrintQuoteService;
use App\Yoowii\Pricing\Application\RetailPrintPricingPolicyProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'yoowii:realisaprint:quote:diagnose', description: 'Diagnose a print quote without exposing Realisaprint credentials.')]
final class DiagnoseRealisaprintQuoteCommand extends Command
{
    public function __construct(private readonly BuiltInPrintProductDefinitionRegistry $definitions, private readonly PrintQuoteService $quotes, private readonly RetailPrintPricingPolicyProvider $policyProvider)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('product-code', InputArgument::REQUIRED, 'Yoowii print product code.')
            ->addOption('options', null, InputOption::VALUE_REQUIRED, 'JSON configuration options.', '{}')
            ->addOption('no-network', null, InputOption::VALUE_NONE, 'Validate product/options only; do not call the supplier API.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $options = $input->getOption('options');
            $productCode = $input->getArgument('product-code');
            if (!is_string($options) || !is_string($productCode)) {
                throw new \InvalidArgumentException('Product code and options are required.');
            }
            $decoded = json_decode($options, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded) || array_is_list($decoded)) {
                throw new \InvalidArgumentException('Options must be a JSON object.');
            }
            /** @var array<string, mixed> $decoded */
            $definition = $this->definitions->get($productCode);
            $configuration = $definition->configure($decoded);
            if ((bool) $input->getOption('no-network')) {
                $output->writeln('source: matrix_fallback');
                $output->writeln('fallback_reason: quote_disabled');
                $output->writeln('diagnostic: configuration is valid; supplier API was intentionally not called.');

                return Command::SUCCESS;
            }
            $quote = $this->quotes->quote($configuration, $this->policyProvider->get(), 'EUR', new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $trace = $quote->pricingSnapshot()->quoteTrace();
            $output->writeln('source: ' . ($trace?->source()->value ?? 'unknown'));
            $output->writeln('supplier: ' . $quote->supplierCode() . '/' . $quote->supplierProductCode());
            $output->writeln('price: ' . number_format($quote->pricingSnapshot()->unitPrice() / 100, 2, '.', '') . ' EUR');
            $output->writeln('correlation_id: ' . ($trace?->correlationId() ?? 'n/a'));
            $fallbackReason = $trace?->fallbackReason();
            $output->writeln('fallback_reason: ' . (null !== $fallbackReason ? $fallbackReason->value : 'none'));

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $output->writeln('<error>Quote diagnosis failed. Check the safe structured application logs with the correlation ID when available.</error>');

            return Command::FAILURE;
        }
    }
}
