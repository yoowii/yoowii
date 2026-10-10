<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\UI\Http\Storefront\Controller;

use App\Yoowii\PrintProduction\Infrastructure\Realisaprint\RealisaprintClient;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Attribute\Route;

/** Server-side callback used by the JavaScript embedded in a Prescript template. */
final class RealisaprintPrescriptConfigurationController extends AbstractController
{
    #[Route('/products/{productCode}/prescript/save-configuration', name: 'yoowii_shop_realisaprint_prescript_save_configuration', requirements: ['productCode' => '[A-Za-z0-9._-]+'], methods: ['POST'])]
    public function save(string $productCode, Request $request, UriSigner $uriSigner, EntityManagerInterface $entityManager, RealisaprintClient $client): Response
    {
        if (!$uriSigner->checkRequest($request)) {
            return new JsonResponse(['error' => 'URL de configuration Préscript invalide.'], Response::HTTP_FORBIDDEN, ['Cache-Control' => 'no-store']);
        }

        $mapping = $this->mapping($productCode, $entityManager);
        if (!$mapping instanceof SupplierProductMappingVersion) {
            return new JsonResponse(['error' => 'Produit Préscript indisponible.'], Response::HTTP_NOT_FOUND, ['Cache-Control' => 'no-store']);
        }
        $provider = $mapping->configurationMapping()['realisaprint'] ?? null;
        if (!is_array($provider)) {
            return new JsonResponse(['error' => 'Configuration Préscript invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY, ['Cache-Control' => 'no-store']);
        }

        $variables = $request->request->all('variables');
        $sanitized = [];
        if (is_array($variables)) {
            foreach ($variables as $name => $value) {
                if (is_string($name) && is_scalar($value) && '' !== trim((string) $value)) {
                    $sanitized[$name] = (string) $value;
                }
            }
        }

        try {
            // Do not forward client-provided credentials, product, stock or callback URL.
            $response = $client->post('save_configuration', [
                'product' => (string) ($provider['product'] ?? ''),
                'stock' => (string) ($provider['stock'] ?? ''),
                'variables' => $sanitized,
            ]);

            return new JsonResponse($response, Response::HTTP_OK, ['Cache-Control' => 'no-store']);
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'La configuration Realisaprint ne peut pas être enregistrée pour le moment.'], Response::HTTP_BAD_GATEWAY, ['Cache-Control' => 'no-store']);
        }
    }

    private function mapping(string $productCode, EntityManagerInterface $entityManager): ?SupplierProductMappingVersion
    {
        foreach ($entityManager->getRepository(SupplierProductMappingVersion::class)->findBy(['yoowiiProductCode' => $productCode, 'active' => true]) as $mapping) {
            if (!$mapping instanceof SupplierProductMappingVersion || 'realisaprint' !== $mapping->supplierProduct()->supplier()->code()) {
                continue;
            }
            $provider = $mapping->configurationMapping()['realisaprint'] ?? null;
            if (is_array($provider) && 'prescript' === ($provider['configurator'] ?? 'classic')) {
                return $mapping;
            }
        }

        return null;
    }
}
