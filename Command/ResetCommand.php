<?php

namespace Dtc\QueueBundle\Command;

use Dtc\QueueBundle\Manager\JobManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResetCommand extends Command
{
    /** @var JobManagerInterface */
    private $jobManager;

    protected function configure()
    {
        $this
            ->setName('dtc:queue:reset')
            ->setDescription('Reset jobs with exception or stalled status');
    }

    public function setJobManager($jobManager)
    {
        $this->jobManager = $jobManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $countException = $this->jobManager->resetExceptionJobs();
        $countStalled = $this->jobManager->resetStalledJobs();
        $output->writeln("$countException job(s) in status 'exception' reset");
        $output->writeln("$countStalled job(s) stalled (in status 'running') reset");
        return 0;
    }
}
