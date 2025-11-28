<?php

declare(strict_types=1);

namespace App\Sender;

use App\Entity\Notification;

interface NotificationSenderInterface
{
    /**
     * Check if this sender supports the given channel.
     */
    public function supports(string $channel): bool;

    /**
     * Send the notification.
     *
     * @throws \Exception if sending fails
     */
    public function send(Notification $notification): void;
}
