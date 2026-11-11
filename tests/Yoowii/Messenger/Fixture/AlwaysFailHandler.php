<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Messenger\Fixture;

final class AlwaysFailHandler
{
    public function __invoke(AlwaysFailMessage $message): void
    {
        throw new \RuntimeException('Intentional isolated Messenger test failure');
    }
}
