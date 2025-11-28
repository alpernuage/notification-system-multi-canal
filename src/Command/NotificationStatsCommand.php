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
    name: 'notification:stats',
    description: 'Show notification statistics',
)]
class NotificationStatsCommand extends Command
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Notification Statistics');

        // Stats by state
        $statsByState = $this->notificationRepository->getStatsByState();
        $io->section('By State');
        $table = new Table($output);
        $table->setHeaders(['State', 'Count']);
        foreach ($statsByState as $state => $count) {
            $table->addRow([$state, $count]);
        }
        $table->render();

        // Stats by channel
        $statsByChannel = $this->notificationRepository->getStatsByChannel();
        $io->section('By Channel');
        $table = new Table($output);
        $table->setHeaders(['Channel', 'Count']);
        foreach ($statsByChannel as $channel => $count) {
            $table->addRow([$channel, $count]);
        }
        $table->render();

        // Total
        $total = array_sum($statsByState);
        $io->success("Total notifications: {$total}");

        return Command::SUCCESS;
    }
}
