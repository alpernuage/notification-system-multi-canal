<?php

declare(strict_types=1);

namespace App\Message;

class SendNotificationMessage
{
    public function __construct(
        private readonly int $notificationId
    ) {
    }

    public function getNotificationId(): int
    {
        return $this->notificationId;
    }
}
