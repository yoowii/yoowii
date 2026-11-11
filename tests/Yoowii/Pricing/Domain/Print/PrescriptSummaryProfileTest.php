<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\Domain\Print;

use App\Yoowii\Pricing\Domain\Print\PrescriptSummaryProfile;
use PHPUnit\Framework\TestCase;

final class PrescriptSummaryProfileTest extends TestCase
{
    public function testItNormalizesAVersionedProfileWithKnownFieldIdentifiers(): void
    {
        $profile = PrescriptSummaryProfile::fromArray([
            'version' => 'v1',
            'fields' => [
                ['code' => 'format', 'label' => 'Format', 'input_id' => 'VART1', 'required' => true],
                ['code' => 'quantity', 'label' => 'Quantité', 'input_name' => 'optionsRadios', 'required' => true],
            ],
        ]);

        self::assertSame([
            'version' => 'v1',
            'fields' => [
                ['code' => 'format', 'label' => 'Format', 'required' => true, 'input_id' => 'VART1'],
                ['code' => 'quantity', 'label' => 'Quantité', 'required' => true, 'input_name' => 'optionsRadios'],
            ],
        ], $profile->toArray());
    }

    public function testItRejectsArbitrarySelectorsAndDuplicateCodes(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PrescriptSummaryProfile::fromArray([
            'version' => 'v1',
            'fields' => [
                ['code' => 'format', 'label' => 'Format', 'input_id' => '#unsafe selector'],
            ],
        ]);
    }

    public function testItIsOptionalForExistingPrescriptMappings(): void
    {
        self::assertNull(PrescriptSummaryProfile::tryFromConfigurationMapping([
            'realisaprint' => ['configurator' => 'prescript'],
        ]));
    }
}
