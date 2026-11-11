<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\UI\Console;

use App\Yoowii\PrintProduction\Infrastructure\Realisaprint\RealisaprintClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'yoowii:realisaprint:check', description: 'Check Realisaprint API connectivity with its read-only products endpoint.')]
final class CheckRealisaprintConnectionCommand extends Command
{
    public function __construct(private readonly RealisaprintClient $client)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->client->isEnabled()) {
            $output->writeln('<comment>Realisaprint is disabled. Set YOOWII_REALISAPRINT_ENABLED=1 in .env.local to perform the check.</comment>');

            return Command::FAILURE;
        }

        try {
            $response = $this->client->post('products', []);
        } catch (\Throwable $exception) {
            $output->writeln('<error>Realisaprint API connection failed: ' . $exception->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $products = $response['products'] ?? null;
        if (!is_array($products)) {
            $output->writeln('<error>Realisaprint API did not return a product list.</error>');

            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Realisaprint API connection successful: %d product(s) available.</info>', count($products)));

        return Command::SUCCESS;
    }
}
