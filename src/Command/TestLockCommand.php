<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(
    name: 'test:lock',
    description: 'Test distributed lock functionality',
)]
class TestLockCommand extends Command
{
    public function __construct(
        private readonly LockFactory $lockFactory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Testing Distributed Lock');

        $resource = 'test_resource';
        $lock1 = $this->lockFactory->createLock($resource);
        $lock2 = $this->lockFactory->createLock($resource);

        $io->section('Acquiring Lock 1');
        if ($lock1->acquire()) {
            $io->success('Lock 1 acquired successfully');
        } else {
            $io->error('Failed to acquire Lock 1');
            return Command::FAILURE;
        }

        $io->section('Attempting to acquire Lock 2 (should fail)');
        if ($lock2->acquire()) {
            $io->error('Lock 2 acquired! Locking is NOT working.');
            $lock1->release();
            $lock2->release();
            return Command::FAILURE;
        } else {
            $io->success('Lock 2 failed to acquire (Expected)');
        }

        $io->section('Releasing Lock 1');
        $lock1->release();
        $io->success('Lock 1 released');

        $io->section('Attempting to acquire Lock 2 (should succeed)');
        if ($lock2->acquire()) {
            $io->success('Lock 2 acquired successfully');
            $lock2->release();
        } else {
            $io->error('Failed to acquire Lock 2 after Lock 1 release');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
