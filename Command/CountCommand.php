<?php

namespace Dtc\QueueBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CountCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('dtc:queue_worker:count')
            ->setDescription('Display job queue status.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $jobManager = $container->get('dtc_queue.job_manager');
        $count = $jobManager->getJobCount();

        $format = '%-50s %8s %8s %8s';
        $status = $jobManager->getStatus();
        $msg = sprintf($format, 'Job name', 'Success', 'New', 'Error');
        $output->writeln($msg);

        foreach ($status as $func => $info) {
            $msg = sprintf($format, $func, $info['success'], $info['new'], $info['error']);
            $output->writeln($msg);
        }

        $output->writeln("Total jobs: {$count}");
    }
}
