<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Infrastructure\Realisaprint;

final readonly class RealisaprintFtpTransport
{
    public function __construct(private bool $enabled, private string $host, private int $port, private string $user, private string $password, private string $baseDirectory)
    {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /** @param resource $stream */
    public function upload(string $remotePath, mixed $stream): void
    {
        if (!function_exists('ftp_connect')) {
            throw new \RuntimeException('The PHP FTP extension is required for Realisaprint artwork upload.');
        }
        $connection = ftp_connect($this->host, $this->port, 20);
        if (false === $connection || !ftp_login($connection, $this->user, $this->password)) {
            throw new \RuntimeException('Unable to connect to the Realisaprint FTP server.');
        }

        try {
            ftp_pasv($connection, true);
            $destination = trim($this->baseDirectory . '/' . ltrim($remotePath, '/'), '/');
            $this->createDirectories($connection, dirname($destination));
            if (!ftp_fput($connection, $destination, $stream, FTP_BINARY)) {
                throw new \RuntimeException('Unable to upload the artwork to Realisaprint FTP.');
            }
        } finally {
            ftp_close($connection);
        }
    }

    private function createDirectories(\FTP\Connection $connection, string $directory): void
    {
        $path = '';
        foreach (array_filter(explode('/', trim($directory, '/'))) as $segment) {
            $path .= '/' . $segment;
            @ftp_mkdir($connection, $path);
        }
    }
}
