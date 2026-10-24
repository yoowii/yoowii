<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Entity\Product\Product;
use App\Entity\Product\ProductImage;
use App\Yoowii\PrintProduction\Infrastructure\Realisaprint\RealisaprintClient;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Downloads one approved provider image only after an administrator selected it. */
final readonly class RealisaprintDraftPictureImporter
{
    public function __construct(private RealisaprintClient $client, private ImageUploaderInterface $imageUploader)
    {
    }

    public function attach(Product $product, string $url): void
    {
        if ('' === trim($url)) {
            return;
        }
        $download = $this->client->downloadPicture($url);
        $extension = match ($download['mime_type']) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $temporaryFile = tempnam(sys_get_temp_dir(), 'yoowii-realisaprint-picture-');
        if (false === $temporaryFile || false === file_put_contents($temporaryFile, $download['content'])) {
            throw new \RuntimeException('L’image Realisaprint ne peut pas être préparée.');
        }

        try {
            $image = new ProductImage();
            $image->setType('main');
            $image->setFile(new UploadedFile($temporaryFile, 'realisaprint-' . hash('sha256', $url) . '.' . $extension, $download['mime_type'], null, true));
            $product->addImage($image);
            $this->imageUploader->upload($image);
        } finally {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }
    }
}
