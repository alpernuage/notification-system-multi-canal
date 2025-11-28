<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Notification;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class NotificationFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Draft notifications
        for ($i = 1; $i <= 3; ++$i) {
            $notification = new Notification();
            $notification
                ->setChannel('email')
                ->setRecipient("user{$i}@example.com")
                ->setSubject("Test Email #{$i}")
                ->setMessage("This is a test email notification #{$i}")
                ->setState('draft');

            $manager->persist($notification);
        }

        // Approved notifications
        for ($i = 1; $i <= 2; ++$i) {
            $notification = new Notification();
            $notification
                ->setChannel('slack')
                ->setRecipient('#general')
                ->setMessage("Approved Slack notification #{$i}")
                ->setState('approved');

            $manager->persist($notification);
        }

        // Sent notifications
        for ($i = 1; $i <= 5; ++$i) {
            $notification = new Notification();
            $notification
                ->setChannel($i % 2 === 0 ? 'email' : 'slack')
                ->setRecipient($i % 2 === 0 ? "sent{$i}@example.com" : '#notifications')
                ->setSubject($i % 2 === 0 ? "Sent Email #{$i}" : null)
                ->setMessage("This notification was sent successfully #{$i}")
                ->setState('sent')
                ->setSentAt(new \DateTimeImmutable("-{$i} hours"));

            $manager->persist($notification);
        }

        // Failed notification
        $failedNotification = new Notification();
        $failedNotification
            ->setChannel('email')
            ->setRecipient('failed@example.com')
            ->setSubject('Failed Email')
            ->setMessage('This notification failed to send')
            ->setState('failed')
            ->setFailedAt(new \DateTimeImmutable('-1 hour'))
            ->setRetryCount(2)
            ->setLastError('SMTP connection timeout');

        $manager->persist($failedNotification);

        $manager->flush();
    }
}
