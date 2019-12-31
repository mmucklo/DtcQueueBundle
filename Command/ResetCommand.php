<?php

namespace Dtc\QueueBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResetCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('dtc:queue:reset')
            ->setDescription('Reset jobs with exception or stalled status');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // @TODO: move this to dependency injection.
        $container = $this->getApplication()->getKernel()->getContainer();
        $jobManager = $container->get('dtc_queue.manager.job');
        $countException = $jobManager->resetExceptionJobs();
        $countStalled = $jobManager->resetStalledJobs();
        $output->writeln("$countException job(s) in status 'exception' reset");
        $output->writeln("$countStalled job(s) stalled (in status 'running') reset");
    }
}
