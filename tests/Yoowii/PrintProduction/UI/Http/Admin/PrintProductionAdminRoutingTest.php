<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\PrintProduction\UI\Http\Admin;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PrintProductionAdminRoutingTest extends WebTestCase
{
    public function testProductionDashboardIsProtectedByTheSyliusAdminFirewall(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/print-production/jobs');

        self::assertResponseRedirects('/admin/login');
    }

    public function testProductionMutationDoesNotAcceptGet(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/print-production/jobs/1/bat');

        self::assertResponseStatusCodeSame(405);
    }
}
