<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\NotificationFailedEvent;
use App\Event\NotificationSentEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NotificationAnalyticsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            NotificationSentEvent::class => 'onNotificationSent',
            NotificationFailedEvent::class => 'onNotificationFailed',
        ];
    }

    public function onNotificationSent(NotificationSentEvent $event): void
    {
        $notification = $event->getNotification();
        
        $this->logger->info('[ANALYTICS] Notification Sent', [
            'id' => $notification->getId(),
            'channel' => $notification->getChannel(),
            'duration' => $notification->getSentAt()->getTimestamp() - $notification->getCreatedAt()->getTimestamp(),
        ]);
    }

    public function onNotificationFailed(NotificationFailedEvent $event): void
    {
        $notification = $event->getNotification();
        $error = $event->getError();

        $this->logger->error('[ANALYTICS] Notification Failed', [
            'id' => $notification->getId(),
            'channel' => $notification->getChannel(),
            'error' => $error->getMessage(),
            'retry_count' => $notification->getRetryCount(),
        ]);
    }
}
