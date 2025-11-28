<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Notification;
use Symfony\Contracts\EventDispatcher\Event;

class NotificationFailedEvent extends Event
{
    public function __construct(
        private readonly Notification $notification,
        private readonly \Throwable $error
    ) {
    }

    public function getNotification(): Notification
    {
        return $this->notification;
    }

    public function getError(): \Throwable
    {
        return $this->error;
    }
}
