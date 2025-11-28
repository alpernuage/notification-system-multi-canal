<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Sender\NotificationSenderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

class NotificationDispatcher
{
    public function __construct(
        #[AutowireLocator('app.notification_sender')]
        private readonly ServiceLocator $senders,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Dispatch notification to the appropriate sender based on channel.
     *
     * @throws \RuntimeException if no sender supports the channel
     */
    public function dispatch(Notification $notification): void
    {
        $channel = $notification->getChannel();

        $this->logger->info('Dispatching notification', [
            'notification_id' => $notification->getId(),
            'channel' => $channel,
        ]);

        /** @var NotificationSenderInterface $sender */
        foreach ($this->senders->getProvidedServices() as $senderId => $senderClass) {
            $sender = $this->senders->get($senderId);

            if ($sender->supports($channel)) {
                $this->logger->info('Found sender for channel', [
                    'notification_id' => $notification->getId(),
                    'channel' => $channel,
                    'sender' => $senderClass,
                ]);

                $sender->send($notification);

                return;
            }
        }

        throw new \RuntimeException(sprintf('No sender found for channel: %s', $channel));
    }
}
