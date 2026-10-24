<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\UI\Http\Admin\Form;

use App\Yoowii\Sourcing\UI\Http\Admin\Form\RealisaprintDraftType;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

final class RealisaprintDraftTypeTest extends TestCase
{
    public function testItExposesSyncedStocksAsRequiredTechnicalChoices(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder
            ->expects(self::exactly(8))
            ->method('add')
            ->willReturnCallback(static function (string $name, string $type, array $options) use ($builder): FormBuilderInterface {
                if ('stock' === $name) {
                    self::assertSame(ChoiceType::class, $type);
                    self::assertTrue($options['required']);
                    self::assertFalse($options['placeholder']);
                    self::assertSame(['837 — Agenda' => '837', '1228 — Agenda semainier' => '1228'], $options['choices']);
                }
                if ('taxon' === $name) {
                    self::assertSame(EntityType::class, $type);
                    self::assertFalse($options['required']);
                    self::assertSame('Choisir une catégorie', $options['placeholder']);
                }

                return $builder;
            })
        ;

        (new RealisaprintDraftType())->buildForm($builder, [
            'stock_choices' => ['837 — Agenda' => '837', '1228 — Agenda semainier' => '1228'],
        ]);
    }
}
