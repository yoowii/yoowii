<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\UI\Http\Storefront;

use App\Entity\User\ShopUser;
use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PrintJobAccountController extends AbstractController
{
    #[Route('/{_locale}/account/print-jobs', name: 'yoowii_shop_account_print_jobs', requirements: ['_locale' => '^[a-z]{2}(?:_[A-Z]{2})?$'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof ShopUser || null === $user->getCustomer()) {
            throw $this->createAccessDeniedException();
        }

        $jobs = $entityManager->createQueryBuilder()
            ->select('job', 'item', 'salesOrder')
            ->from(PrintJob::class, 'job')
            ->join('job.orderItem', 'item')
            ->join('item.order', 'salesOrder')
            ->where('salesOrder.customer = :customer')
            ->setParameter('customer', $user->getCustomer())
            ->orderBy('job.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('shop/account/print_job/index.html.twig', ['jobs' => $jobs]);
    }
}
