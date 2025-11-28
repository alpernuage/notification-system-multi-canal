<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\NotificationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'notification:list',
    description: 'List all notifications',
)]
class NotificationListCommand extends Command
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $notifications = $this->notificationRepository->findBy([], ['createdAt' => 'DESC'], 20);

        if (empty($notifications)) {
            $io->warning('No notifications found');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['ID', 'Channel', 'Recipient', 'State', 'Retry', 'Created']);

        foreach ($notifications as $notification) {
            $table->addRow([
                $notification->getId(),
                $notification->getChannel(),
                substr($notification->getRecipient(), 0, 30),
                $notification->getState(),
                $notification->getRetryCount(),
                $notification->getCreatedAt()->format('Y-m-d H:i:s'),
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
