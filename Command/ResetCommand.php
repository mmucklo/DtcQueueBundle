<?php

namespace Dtc\QueueBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ResetCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('dtc:queue:reset')
            ->setDefinition(
                array(
                    new InputArgument('worker_name', InputArgument::OPTIONAL, 'Name of worker', null),
                    new InputArgument('method', InputArgument::OPTIONAL, 'DI method of worker', null),
                    new inputOption('job_id', 'i', InputOption::VALUE_REQUIRED,
                        'Id of Job to run', null),
                    new inputOption('total_jobs', 't', InputOption::VALUE_REQUIRED,
                        'Total number of job to work on before exiting', 1),
                    new inputOption('timeout', 'to', InputOption::VALUE_REQUIRED,
                        'Process timeout in seconds', 3600),
                    new inputOption('id', null, null, 'Id of a single job to run'),
                )
            )
            ->setDescription('Reset jobs with error status')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $jobManager = $container->get('dtc_queue.job_manager');
        $jobManager->resetErroneousJobs();
    }
}
