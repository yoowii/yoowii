<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Application\BuiltInPrintProductDefinitionRegistry;
use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class BuiltInPrintProductDefinitionRegistryTest extends TestCase
{
    public function testItExposesTheMvpPrintProducts(): void
    {
        $registry = new BuiltInPrintProductDefinitionRegistry();

        self::assertSame(['PRINT_FLYER', 'PRINT_BUSINESS_CARD'], $registry->codes());
        self::assertSame('PRINT_FLYER', $registry->get('PRINT_FLYER')->productCode());
    }

    public function testItRejectsAnUnknownProduct(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new BuiltInPrintProductDefinitionRegistry())->get('PRINT_UNKNOWN');
    }

    public function testItsAdministrationCodesIncludeAnInactivePersistedDefinition(): void
    {
        $definition = new PersistedPrintProductDefinition('PRINT_REALISAPRINT_356', 'v1', [
            'format' => ['type' => 'code', 'allowed_values' => ['a4']],
        ], ['format']);
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::exactly(2))->method('findBy')
            ->willReturnCallback(static function (array $criteria, ?array $orderBy = null) use ($definition): array {
                if (['active' => true] === $criteria) {
                    self::assertNull($orderBy);

                    return [];
                }

                self::assertSame([], $criteria);
                self::assertSame(['id' => 'ASC'], $orderBy);

                return [$definition];
            });
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(PersistedPrintProductDefinition::class)->willReturn($repository);

        $registry = new BuiltInPrintProductDefinitionRegistry($entityManager);

        self::assertNotContains('PRINT_REALISAPRINT_356', $registry->codes());
        self::assertContains('PRINT_REALISAPRINT_356', $registry->adminCodes());
    }
}
