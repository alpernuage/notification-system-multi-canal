<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\NotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function index(NotificationRepository $repository): Response

    {
        return $this->render('dashboard/index.html.twig', [
            'stats_by_state' => $repository->getStatsByState(),
            'stats_by_channel' => $repository->getStatsByChannel(),
            'recent_notifications' => $repository->findBy([], ['createdAt' => 'DESC'], 10),
        ]);
    }
}
