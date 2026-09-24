<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\UI\Console;

use App\Yoowii\PrintProduction\Infrastructure\Realisaprint\RealisaprintClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'yoowii:realisaprint:catalog', description: 'Inspect Realisaprint products or a product configuration without exposing credentials.')]
final class InspectRealisaprintCatalogCommand extends Command
{
    public function __construct(private readonly RealisaprintClient $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('product', InputArgument::OPTIONAL, 'Realisaprint product identifier to inspect its stocks and variables.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->client->isEnabled()) {
            $output->writeln('<comment>Realisaprint is disabled. Set YOOWII_REALISAPRINT_ENABLED=1 first.</comment>');

            return Command::FAILURE;
        }
        $product = $input->getArgument('product');
        try {
            $response = $this->client->post(is_string($product) && '' !== $product ? 'configurations' : 'products', is_string($product) && '' !== $product ? ['product' => $product] : []);
        } catch (\Throwable $exception) {
            $output->writeln('<error>Realisaprint API request failed: ' . $exception->getMessage() . '</error>');

            return Command::FAILURE;
        }
        $output->writeln(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
