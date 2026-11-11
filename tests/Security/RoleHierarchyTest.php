<?php

declare(strict_types=1);

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

final class RoleHierarchyTest extends KernelTestCase
{
    public function testSuperAdministratorInheritsEveryAdministrativePermission(): void
    {
        self::bootKernel();

        $roleHierarchy = self::getContainer()->get('security.role_hierarchy');

        self::assertInstanceOf(RoleHierarchyInterface::class, $roleHierarchy);
        self::assertEqualsCanonicalizing([
            'ROLE_SUPER_ADMIN',
            'ROLE_ADMINISTRATION_ACCESS',
            'ROLE_API_ACCESS',
            'ROLE_ALLOWED_TO_SWITCH',
            'ROLE_PRINT_PRODUCTION',
        ], $roleHierarchy->getReachableRoleNames(['ROLE_SUPER_ADMIN']));
    }
}
