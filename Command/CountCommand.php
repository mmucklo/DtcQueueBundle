<?php

namespace Dtc\QueueBundle\Command;

use Dtc\QueueBundle\Beanstalkd\JobManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CountCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('dtc:queue:count')
            ->setDescription('Display job queue status.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $jobManager = $container->get('dtc_queue.manager.job');

        if ($jobManager instanceof JobManager) {
            $output->writeln(print_r($jobManager->getStats(), true));

            return 0;
        }

        $count = $jobManager->getJobCount();

        $format = '%-50s %8s %8s %8s %8s';
        $status = $jobManager->getStatus();
        $msg = sprintf($format, 'Job name', 'Success', 'New', 'Running', 'Exception');
        $output->writeln($msg);

        foreach ($status as $func => $info) {
            $msg = sprintf($format, $func, $info['success'], $info['new'], $info['running'], $info['exception']);
            $output->writeln($msg);
        }

        $output->writeln("Total jobs: {$count}");
    }
}
