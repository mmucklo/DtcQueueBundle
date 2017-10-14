<?php

namespace Dtc\QueueBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResetCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('dtc:queue:reset')
            ->setDescription('Reset jobs with error or stalled status');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $jobManager = $container->get('dtc_queue.job_manager');
        $countError = $jobManager->resetErroneousJobs();
        $countStalled = $jobManager->resetStalledJobs();
        $output->writeln("$countError job(s) in status 'error' reset");
        $output->writeln("$countStalled job(s) in status 'running' reset");
    }
}
