<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\PrintProduction\Infrastructure\Realisaprint;

use App\Yoowii\PrintProduction\Infrastructure\Realisaprint\RealisaprintClient;
use PHPUnit\Framework\TestCase;

final class RealisaprintClientPictureUrlTest extends TestCase
{
    public function testItOnlyAcceptsOfficialRealisaprintCataloguePictures(): void
    {
        self::assertTrue(RealisaprintClient::isPictureUrl('https://www.realisaprint.com/api_img/images/agenda/example.webp'));
        self::assertFalse(RealisaprintClient::isPictureUrl('https://example.test/api_img/images/agenda/example.webp'));
        self::assertFalse(RealisaprintClient::isPictureUrl('http://www.realisaprint.com/api_img/images/agenda/example.webp'));
        self::assertFalse(RealisaprintClient::isPictureUrl('https://www.realisaprint.com/site/image.jpg'));
    }
}
