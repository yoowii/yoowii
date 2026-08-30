<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Infrastructure\Storage;

use App\Yoowii\PrintProduction\Application\PrintAssetStorage;

final readonly class PrivateLocalPrintAssetStorage implements PrintAssetStorage
{
    public function __construct(private string $rootDirectory)
    {
    }

    public function store(string $key, mixed $stream): void
    {
        if (!is_resource($stream) || str_contains($key, '..')) {
            throw new \InvalidArgumentException('Invalid private print asset.');
        }
        $path = rtrim($this->rootDirectory, '/') . '/' . $key;
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create the private print directory.');
        }
        $target = fopen($path, 'xb');
        if (false === $target) {
            throw new \RuntimeException('Unable to create the private print asset.');
        }

        try {
            stream_copy_to_stream($stream, $target);
        } finally {
            fclose($target);
        }
        chmod($path, 0600);
    }
}
