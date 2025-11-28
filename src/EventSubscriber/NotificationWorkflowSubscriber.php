<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Notification;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Workflow\Event\GuardEvent;

class NotificationWorkflowSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.notification.guard' => ['onGuard'],
            'workflow.notification.entered' => ['onEntered'],
            'workflow.notification.transition' => ['onTransition'],
            'workflow.notification.completed' => ['onCompleted'],
        ];
    }

    public function onGuard(GuardEvent $event): void
    {
        /** @var Notification $notification */
        $notification = $event->getSubject();
        $transition = $event->getTransition()->getName();

        $this->logger->info('Workflow guard check', [
            'notification_id' => $notification->getId(),
            'transition' => $transition,
            'current_state' => $notification->getState(),
        ]);
    }

    public function onEntered(Event $event): void
    {
        /** @var Notification $notification */
        $notification = $event->getSubject();
        $place = $event->getMarking()->getPlaces();

        $this->logger->info('Workflow entered new state', [
            'notification_id' => $notification->getId(),
            'new_state' => array_key_first($place),
            'channel' => $notification->getChannel(),
        ]);
    }

    public function onTransition(Event $event): void
    {
        /** @var Notification $notification */
        $notification = $event->getSubject();
        $transition = $event->getTransition()->getName();

        $this->logger->info('Workflow transition started', [
            'notification_id' => $notification->getId(),
            'transition' => $transition,
            'from' => $event->getTransition()->getFroms(),
            'to' => $event->getTransition()->getTos(),
        ]);
    }

    public function onCompleted(Event $event): void
    {
        /** @var Notification $notification */
        $notification = $event->getSubject();
        $transition = $event->getTransition()->getName();

        $this->logger->info('Workflow transition completed', [
            'notification_id' => $notification->getId(),
            'transition' => $transition,
            'final_state' => $notification->getState(),
        ]);
    }
}
