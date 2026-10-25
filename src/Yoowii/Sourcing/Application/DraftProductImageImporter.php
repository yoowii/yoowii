<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Entity\Product\Product;
use App\Entity\Product\ProductImage;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Imports an administrator-supplied product image from a public URL or upload. */
final readonly class DraftProductImageImporter
{
    private const array MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(private HttpClientInterface $httpClient, private ImageUploaderInterface $imageUploader)
    {
    }

    public function attachFromUrl(Product $product, string $url): void
    {
        $url = trim($url);
        if (false === filter_var($url, \FILTER_VALIDATE_URL) || 'https' !== strtolower((string) parse_url($url, \PHP_URL_SCHEME))) {
            throw new \InvalidArgumentException('L’URL de l’image doit être une URL HTTPS valide.');
        }

        $response = $this->httpClient->request('GET', $url, [
            'timeout' => 10,
            'max_duration' => 20,
            'max_redirects' => 0,
        ]);
        $content = $response->getContent(false);
        $mimeType = strtolower(trim(explode(';', (string) (($response->getHeaders(false)['content-type'][0] ?? '')))[0]));
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300 || '' === $content || strlen($content) > 8 * 1024 * 1024 || !isset(self::MIME_EXTENSIONS[$mimeType])) {
            throw new \DomainException('Le téléchargement de l’image a échoué. Utilisez une image JPEG, PNG ou WebP de 8 Mo maximum.');
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'yoowii-draft-picture-');
        if (false === $temporaryFile || false === file_put_contents($temporaryFile, $content)) {
            throw new \RuntimeException('L’image ne peut pas être préparée.');
        }

        try {
            $this->attachFile($product, new UploadedFile($temporaryFile, 'image-' . hash('sha256', $url) . '.' . self::MIME_EXTENSIONS[$mimeType], $mimeType, null, true));
        } finally {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }
    }

    public function attachUploadedFile(Product $product, UploadedFile $file): void
    {
        if (!$file->isValid() || !isset(self::MIME_EXTENSIONS[(string) $file->getMimeType()])) {
            throw new \InvalidArgumentException('Choisissez une image JPEG, PNG ou WebP valide.');
        }

        $this->attachFile($product, $file);
    }

    private function attachFile(Product $product, UploadedFile $file): void
    {
        $image = new ProductImage();
        $image->setType('main');
        $image->setFile($file);
        $product->addImage($image);
        $this->imageUploader->upload($image);
    }
}
