<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Commerce\UI\Http\Admin;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProductBulkAvailabilityRoutingTest extends WebTestCase
{
    public function testBulkAvailabilityMutationDoesNotAcceptGetRequests(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/products/bulk-availability/enable');

        self::assertResponseStatusCodeSame(405);
    }
}
