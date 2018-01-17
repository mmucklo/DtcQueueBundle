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
            ->setName('dtc:queue:count')
            ->setDescription('Display job queue status.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $jobManager = $container->get('dtc_queue.manager.job');

        $waitingCount = $jobManager->getWaitingJobCount();
        $status = $jobManager->getStatus();

        $firstJob = key($status);
        if ($firstJob) {
            $jobKeys = array_keys($status);
            $maxLength = max(array_map(function ($item) {
                return strlen($item ?: '');
            }, $jobKeys));
            $formatLen = $maxLength > 50 ? 50 : $maxLength;
            $formatMinLen = strlen('Job name') + 1;
            $formatLen = $formatLen < $formatMinLen ? $formatMinLen : $formatLen;
            $format = '%-'.$formatLen.'s';
            $headingArgs = ['Job name'];
            $initialKeys = array_keys($status[$firstJob]);
            foreach ($initialKeys as $statusName) {
                $headingStr = ucwords(str_replace('_', ' ', $statusName));
                $format .= ' %'.(1 + strlen($headingStr)).'s';
                $headingArgs[] = $headingStr;
            }
            array_unshift($headingArgs, $format);
            $msg = call_user_func_array('sprintf', $headingArgs);
            $output->writeln($msg);

            foreach ($status as $func => $info) {
                $lineArgs = [$format, $func];
                foreach ($initialKeys as $statusKey) {
                    $lineArgs[] = $info[$statusKey];
                }
                $msg = call_user_func_array('sprintf', $lineArgs);
                $output->writeln($msg);
            }
        }

        $output->writeln("Total waiting jobs: {$waitingCount}");
    }
}
