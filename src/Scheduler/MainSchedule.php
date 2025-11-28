<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\RetryFailedNotificationsMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('default')]
class MainSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                // Run every 5 minutes
                RecurringMessage::every('5 minutes', new RetryFailedNotificationsMessage())
            )
        ;
    }
}
