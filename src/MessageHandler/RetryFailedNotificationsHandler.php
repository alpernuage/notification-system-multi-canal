<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\RetryFailedNotificationsMessage;
use App\Message\SendNotificationMessage;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
class RetryFailedNotificationsHandler
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly WorkflowInterface $notificationStateMachine,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RetryFailedNotificationsMessage $message): void
    {
        $this->logger->info('Checking for failed notifications to retry...');

        // Find failed notifications that can be retried (e.g., retry_count < 3)
        // Note: Repository method needs to be implemented or we use a custom query here
        // For simplicity, let's fetch all 'failed' and check retry count in loop or use a better query
        
        $failedNotifications = $this->notificationRepository->findBy(['state' => 'failed']);
        $count = 0;

        foreach ($failedNotifications as $notification) {
            if ($notification->getRetryCount() >= 3) {
                continue;
            }

            if ($this->notificationStateMachine->can($notification, 'retry')) {
                $this->logger->info('Retrying notification', ['id' => $notification->getId()]);

                $this->notificationStateMachine->apply($notification, 'retry');
                $this->entityManager->flush();

                // Dispatch for sending
                $this->messageBus->dispatch(new SendNotificationMessage($notification->getId()));
                ++$count;
            }
        }

        $this->logger->info("Retried {$count} notifications.");
    }
}
