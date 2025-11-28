<?php

declare(strict_types=1);

namespace App\Sender;

use App\Entity\Notification;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.notification_sender')]
class SmsSender implements NotificationSenderInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function supports(string $channel): bool
    {
        return 'sms' === $channel;
    }

    public function send(Notification $notification): void
    {
        $this->logger->info('Sending SMS notification', [
            'notification_id' => $notification->getId(),
            'recipient' => $notification->getRecipient(),
        ]);

        // Mock implementation - log instead of sending real SMS
        $this->logger->info('[MOCK] SMS would be sent', [
            'notification_id' => $notification->getId(),
            'to' => $notification->getRecipient(),
            'message' => $notification->getMessage(),
        ]);

        // Uncomment for real SMS integration (e.g., Twilio):
        // $this->twilioClient->messages->create(
        //     $notification->getRecipient(),
        //     ['from' => '+1234567890', 'body' => $notification->getMessage()]
        // );

        $this->logger->info('SMS notification sent successfully (mock)', [
            'notification_id' => $notification->getId(),
        ]);
    }
}
