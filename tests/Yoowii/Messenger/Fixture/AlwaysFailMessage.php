<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Messenger\Fixture;

final readonly class AlwaysFailMessage
{
    public function __construct(public string $runId)
    {
    }
}
