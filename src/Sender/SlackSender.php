<?php

declare(strict_types=1);

namespace App\Sender;

use App\Entity\Notification;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\HttpClient\HttpClientInterface;


#[AutoconfigureTag('app.notification_sender')]
class SlackSender implements NotificationSenderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $slackWebhookUrl,
        private readonly RateLimiterFactory $slackSenderLimiter,
    ) {
    }

    public function supports(string $channel): bool
    {
        return 'slack' === $channel;
    }

    public function send(Notification $notification): void
    {
        // Create a limiter based on channel (recipient)
        $limiter = $this->slackSenderLimiter->create($notification->getRecipient());

        if (false === $limiter->consume(1)->isAccepted()) {
             throw new \Exception(sprintf('Rate limit exceeded for Slack channel %s', $notification->getRecipient()));
        }

        $this->logger->info('Sending Slack notification', [
            'notification_id' => $notification->getId(),
            'recipient' => $notification->getRecipient(),
        ]);

        // Mock implementation - log instead of sending to real Slack
        $payload = [
            'channel' => $notification->getRecipient(),
            'text' => $notification->getMessage(),
            'username' => 'Notification Bot',
        ];

        $this->logger->info('[MOCK] Slack notification would be sent', [
            'notification_id' => $notification->getId(),
            'payload' => $payload,
            'webhook_url' => $this->slackWebhookUrl,
        ]);

        // Uncomment for real Slack integration:
        // $this->httpClient->request('POST', $this->slackWebhookUrl, [
        //     'json' => $payload,
        // ]);

        $this->logger->info('Slack notification sent successfully (mock)', [
            'notification_id' => $notification->getId(),
        ]);
    }
}
