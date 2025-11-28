<?php

declare(strict_types=1);

namespace App\Sender;

use App\Entity\Notification;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimiterFactory;


#[AutoconfigureTag('app.notification_sender')]
class EmailSender implements NotificationSenderInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly RateLimiterFactory $emailSenderLimiter,
    ) {
    }

    public function supports(string $channel): bool
    {
        return 'email' === $channel;
    }

    public function send(Notification $notification): void
    {
        // Create a limiter based on recipient to prevent spamming one user
        $limiter = $this->emailSenderLimiter->create($notification->getRecipient());

        // Consume 1 token
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            throw new \Exception(sprintf(
                'Rate limit exceeded for email to %s. Retry after %s seconds.',
                $notification->getRecipient(),
                $limit->getRetryAfter()->getTimestamp() - time()
            ));
        }

        $this->logger->info('Sending email notification', [
            'notification_id' => $notification->getId(),
            'recipient' => $notification->getRecipient(),
        ]);

        $email = (new Email())
            ->from('noreply@notifications.local')
            ->to($notification->getRecipient())
            ->subject($notification->getSubject() ?? 'Notification')
            ->text($notification->getMessage())
            ->html("<p>{$notification->getMessage()}</p>");

        $this->mailer->send($email);

        $this->logger->info('Email notification sent successfully', [
            'notification_id' => $notification->getId(),
        ]);
    }
}
