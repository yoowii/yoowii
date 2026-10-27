<?php

declare(strict_types=1);

namespace App\Yoowii\Commerce\UI\Http\Admin\Controller;

use App\Entity\Product\Product;
use App\Yoowii\Commerce\Application\BulkProductAvailabilityUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ProductBulkAvailabilityController extends AbstractController
{
    #[Route('/products/bulk-availability/{enabled}', name: 'yoowii_admin_product_bulk_availability', requirements: ['enabled' => 'enable|disable'], methods: ['POST'])]
    public function update(
        string $enabled,
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager,
        EntityManagerInterface $entityManager,
        BulkProductAvailabilityUpdater $updater,
        TranslatorInterface $translator,
    ): RedirectResponse {
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('yoowii_bulk_product_availability', (string) $request->request->get('_csrf_token')))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $ids = $this->productIds($request->request->all('ids'));
        if ([] === $ids) {
            $this->addFlash('warning', 'yoowii.admin.product.bulk_availability.no_products_selected');

            return $this->redirectToRoute('sylius_admin_product_index');
        }

        /** @var list<Product> $products */
        $products = $entityManager->getRepository(Product::class)->findBy(['id' => $ids]);
        $updated = $updater->update($products, 'enable' === $enabled);
        $entityManager->flush();

        $this->addFlash('success', $translator->trans(
            'enable' === $enabled
                ? 'yoowii.admin.product.bulk_availability.updated_enabled'
                : 'yoowii.admin.product.bulk_availability.updated_disabled',
            ['%count%' => $updated],
        ));

        return $this->redirectToRoute('sylius_admin_product_index');
    }

    /**
     * @param mixed $ids
     *
     * @return list<int>
     */
    private function productIds(mixed $ids): array
    {
        if (!is_array($ids)) {
            return [];
        }

        $productIds = [];
        foreach ($ids as $id) {
            if (!is_scalar($id) || !ctype_digit((string) $id) || 0 >= (int) $id) {
                continue;
            }

            $productIds[(int) $id] = (int) $id;
        }

        return array_values($productIds);
    }
}
