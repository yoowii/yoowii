<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Application;

use App\Yoowii\Sourcing\Application\RealisaprintConfigurationSelection;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use PHPUnit\Framework\TestCase;

final class RealisaprintConfigurationSelectionTest extends TestCase
{
    public function testItSelectsMissingAndPreviouslyErroredConfigurations(): void
    {
        $selection = new RealisaprintConfigurationSelection();
        $missing = new RealisaprintCatalogProduct('1', 'Missing', new \DateTimeImmutable());
        $errored = new RealisaprintCatalogProduct('2', 'Errored', new \DateTimeImmutable());
        $errored->refreshConfiguration(['error' => 'Too soon'], new \DateTimeImmutable());
        $valid = new RealisaprintCatalogProduct('3', 'Valid', new \DateTimeImmutable());
        $valid->refreshConfiguration([], new \DateTimeImmutable());

        self::assertTrue($selection->needsRefresh($missing));
        self::assertTrue($selection->needsRefresh($errored));
        self::assertFalse($selection->needsRefresh($valid));
    }
}
