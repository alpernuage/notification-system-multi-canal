<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\NotificationFailedEvent;
use App\Event\NotificationSentEvent;
use App\Message\SendNotificationMessage;
use App\Repository\NotificationRepository;
use App\Service\NotificationDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
class SendNotificationHandler
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly NotificationDispatcher $notificationDispatcher,
        private readonly WorkflowInterface $notificationStateMachine,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly LockFactory $lockFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(SendNotificationMessage $message): void
    {
        $notificationId = $message->getNotificationId();

        // Create a lock for this specific notification
        $lock = $this->lockFactory->createLock("notification_{$notificationId}");

        if (!$lock->acquire()) {
            $this->logger->warning('Notification is already being processed', [
                'notification_id' => $notificationId,
            ]);
            return;
        }

        try {
            $this->logger->info('Processing SendNotificationMessage', [
                'notification_id' => $notificationId,
            ]);

            $notification = $this->notificationRepository->find($notificationId);

            if (null === $notification) {
                $this->logger->error('Notification not found', [
                    'notification_id' => $notificationId,
                ]);

                return;
            }

            // Transition to sending state
            if ($this->notificationStateMachine->can($notification, 'send')) {
                $this->notificationStateMachine->apply($notification, 'send');
                $this->entityManager->flush();
            }

            // Dispatch to appropriate sender
            $this->notificationDispatcher->dispatch($notification);

            // Mark as sent
            $notification->setSentAt(new \DateTimeImmutable());
            $this->notificationStateMachine->apply($notification, 'mark_as_sent');
            $this->entityManager->flush();

            $this->logger->info('Notification sent successfully', [
                'notification_id' => $notificationId,
            ]);

            // Dispatch success event
            $this->eventDispatcher->dispatch(new NotificationSentEvent($notification));

        } catch (\Exception $e) {
            $this->logger->error('Failed to send notification', [
                'notification_id' => $notificationId,
                'error' => $e->getMessage(),
            ]);

            // Mark as failed
            $notification->setFailedAt(new \DateTimeImmutable());
            $notification->setLastError($e->getMessage());
            $notification->incrementRetryCount();

            if ($this->notificationStateMachine->can($notification, 'mark_as_failed')) {
                $this->notificationStateMachine->apply($notification, 'mark_as_failed');
            }

            $this->entityManager->flush();

            // Dispatch failure event
            $this->eventDispatcher->dispatch(new NotificationFailedEvent($notification, $e));

            // Re-throw to trigger Messenger retry
            throw $e;
        } finally {
            $lock->release();
        }
    }
}


