<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\UI\Http\Storefront;

use App\Entity\Order\Order;
use App\Entity\User\ShopUser;
use App\Yoowii\PrintProduction\Application\PrintJobAccessLink;
use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PrintOrderFilesController extends AbstractController
{
    #[Route('/{_locale}/flow/orders/{tokenValue}/print-files', name: 'yoowii_shop_flow_order_print_files', requirements: ['_locale' => '^[a-z]{2}(?:_[A-Z]{2})?$'], methods: ['GET'])]
    public function __invoke(string $tokenValue, Request $request, EntityManagerInterface $entityManager, PrintJobAccessLink $links): Response
    {
        $order = $entityManager->getRepository(Order::class)->findOneBy(['tokenValue' => $tokenValue]);
        if (!$order instanceof Order || 'paid' !== $order->getPaymentState()) {
            throw $this->createNotFoundException();
        }

        /** @var list<PrintJob> $jobs */
        $jobs = $entityManager->createQueryBuilder()
            ->select('job')
            ->from(PrintJob::class, 'job')
            ->join('job.orderItem', 'item')
            ->andWhere('item.order = :order')
            ->setParameter('order', $order)
            ->orderBy('job.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        $locale = $request->attributes->get('_locale');
        $locale = is_string($locale) ? $locale : '';
        $jobLinks = [];
        $user = $this->getUser();
        $isOwner = $user instanceof ShopUser && $order->getCustomer() === $user->getCustomer();
        $visibleJobs = [];
        foreach ($jobs as $job) {
            if ($isOwner || $job->guestAccessEnabled()) {
                $visibleJobs[] = $job;
                $jobLinks[$job->reference()] = $links->show($job, $locale);
            }
        }

        $response = $this->render('shop/flow/order/print_files.html.twig', [
            'order' => $order,
            'jobs' => $visibleJobs,
            'job_links' => $jobLinks,
        ]);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
