<?php

declare(strict_types=1);

namespace App\Sender;

use App\Entity\Notification;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AutoconfigureTag('app.notification_sender')]
class WebhookSender implements NotificationSenderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function supports(string $channel): bool
    {
        return 'webhook' === $channel;
    }

    public function send(Notification $notification): void
    {
        $this->logger->info('Sending webhook notification', [
            'notification_id' => $notification->getId(),
            'webhook_url' => $notification->getRecipient(),
        ]);

        $payload = [
            'id' => $notification->getId(),
            'channel' => $notification->getChannel(),
            'message' => $notification->getMessage(),
            'timestamp' => $notification->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];

        // Mock implementation - log instead of sending real HTTP request
        $this->logger->info('[MOCK] Webhook would be called', [
            'notification_id' => $notification->getId(),
            'url' => $notification->getRecipient(),
            'payload' => $payload,
        ]);

        // Uncomment for real webhook integration:
        // $this->httpClient->request('POST', $notification->getRecipient(), [
        //     'json' => $payload,
        // ]);

        $this->logger->info('Webhook notification sent successfully (mock)', [
            'notification_id' => $notification->getId(),
        ]);
    }
}
