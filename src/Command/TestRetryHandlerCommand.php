<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\RetryFailedNotificationsMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'test:retry-handler',
    description: 'Trigger the retry handler manually',
)]
class TestRetryHandlerCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Triggering Retry Handler');

        $this->messageBus->dispatch(new RetryFailedNotificationsMessage());

        $io->success('Retry message dispatched. Check logs or messenger worker output.');

        return Command::SUCCESS;
    }
}
