<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\UI\Http\Admin;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SourcingAdminAccessTest extends WebTestCase
{
    public function testTheDashboardRouteIsRegisteredAndProtected(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/print-sourcing');

        self::assertResponseRedirects('/admin/login');
    }

    public function testMutationRoutesDoNotAcceptGetRequests(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/print-sourcing/routes/1/toggle');

        self::assertResponseStatusCodeSame(405);
    }
}
