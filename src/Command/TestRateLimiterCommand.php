<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[AsCommand(
    name: 'test:rate-limiter',
    description: 'Test rate limiter functionality',
)]
class TestRateLimiterCommand extends Command
{
    public function __construct(
        private readonly RateLimiterFactory $emailSenderLimiter,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Testing Rate Limiter (Email: 100/hour per recipient)');

        // Use the SAME recipient to test the limit
        $recipient = 'rate-limit-test@example.com';
        $limiter = $this->emailSenderLimiter->create($recipient);

        $io->info(sprintf('Attempting to send 105 emails to: %s', $recipient));
        
        // Reset for testing
        $limiter->reset();

        for ($i = 1; $i <= 105; ++$i) {
            $limit = $limiter->consume(1);

            if (!$limit->isAccepted()) {
                $retryAfter = $limit->getRetryAfter()->getTimestamp() - time();
                $io->error(sprintf(
                    'Limit reached at attempt #%d. Retry after %d seconds.',
                    $i,
                    $retryAfter
                ));

                return Command::SUCCESS;
            }

            if ($i % 10 === 0) {
                $io->success(sprintf('Attempt #%d: OK', $i));
            }
        }

        $io->warning('All 105 attempts passed. Rate limiter might not be working correctly.');

        return Command::SUCCESS;
    }
}
