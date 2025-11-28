<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Notification;
use App\Message\SendNotificationMessage;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\WorkflowInterface;

#[Route('/api/notifications', name: 'api_notifications_')]
class NotificationController extends AbstractController
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly WorkflowInterface $notificationStateMachine,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $notifications = $this->notificationRepository->findBy([], ['createdAt' => 'DESC'], 50);
        
        $data = array_map(fn (Notification $n) => [
            'id' => $n->getId(),
            'channel' => $n->getChannel(),
            'recipient' => $n->getRecipient(),
            'state' => $n->getState(),
            'created_at' => $n->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $notifications);

        return $this->json($data);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['channel'], $data['recipient'], $data['message'])) {
            return $this->json(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
        }

        $notification = new Notification();
        $notification
            ->setChannel($data['channel'])
            ->setRecipient($data['recipient'])
            ->setMessage($data['message'])
            ->setSubject($data['subject'] ?? null);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        // Auto-approve and send
        if ($this->notificationStateMachine->can($notification, 'approve')) {
            $this->notificationStateMachine->apply($notification, 'approve');
            $this->entityManager->flush();

            $this->messageBus->dispatch(new SendNotificationMessage($notification->getId()));
        }

        return $this->json([
            'id' => $notification->getId(),
            'status' => 'created',
            'state' => $notification->getState(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $notification = $this->notificationRepository->find($id);

        if (!$notification) {
            return $this->json(['error' => 'Notification not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $notification->getId(),
            'channel' => $notification->getChannel(),
            'recipient' => $notification->getRecipient(),
            'subject' => $notification->getSubject(),
            'message' => $notification->getMessage(),
            'state' => $notification->getState(),
            'created_at' => $notification->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'sent_at' => $notification->getSentAt()?->format(\DateTimeInterface::ATOM),
            'failed_at' => $notification->getFailedAt()?->format(\DateTimeInterface::ATOM),
            'retry_count' => $notification->getRetryCount(),
            'last_error' => $notification->getLastError(),
        ]);
    }
}
