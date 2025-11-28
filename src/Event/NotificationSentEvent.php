<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Notification;
use Symfony\Contracts\EventDispatcher\Event;

class NotificationSentEvent extends Event
{
    public function __construct(
        private readonly Notification $notification
    ) {
    }

    public function getNotification(): Notification
    {
        return $this->notification;
    }
}
