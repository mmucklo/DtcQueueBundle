<?php

namespace Dtc\QueueBundle\Command;

use Dtc\QueueBundle\Manager\JobManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CountCommand extends Command
{
    /** @var JobManagerInterface */
    private $jobManager;

    public function setJobManager($jobManager)
    {
        $this->jobManager = $jobManager;
    }

    protected function configure()
    {
        $this
            ->setName('dtc:queue:count')
            ->setDescription('Display job queue status.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $waitingCount = $this->jobManager->getWaitingJobCount();
        $status = $this->jobManager->getStatus();
        $firstJob = key($status);
        if ($firstJob) {
            $jobKeys = array_keys($status);
            $maxLength = max(array_map(function ($item) {
                return strlen($item ?: '');
            }, $jobKeys));
            $formatLen = $this->determineFormatLength($maxLength);
            $format = '%-'.$formatLen.'s';
            $headingArgs = ['Job name'];
            $initialKeys = array_keys($status[$firstJob]);
            $this->formatHeadings($initialKeys, $headingArgs, $format);
            array_unshift($headingArgs, $format);
            $msg = call_user_func_array('sprintf', $headingArgs);
            $output->writeln($msg);
            $this->outputStatus($output, $status, $initialKeys, $format);
        }
        $output->writeln("Total waiting jobs: {$waitingCount}");

        return $this::SUCCESS;
    }

    /**
     * @param string $format
     */
    private function outputStatus(OutputInterface $output, array $status, array $initialKeys, $format)
    {
        foreach ($status as $func => $info) {
            $lineArgs = [$format, $func];
            foreach ($initialKeys as $statusKey) {
                $lineArgs[] = $info[$statusKey];
            }
            $msg = call_user_func_array('sprintf', $lineArgs);
            $output->writeln($msg);
        }
    }

    /**
     * @param string $format
     */
    private function formatHeadings(array $initialKeys, array &$headingArgs, &$format)
    {
        foreach ($initialKeys as $statusName) {
            $headingStr = ucwords(str_replace('_', ' ', $statusName));
            $format .= ' %'.(1 + strlen($headingStr)).'s';
            $headingArgs[] = $headingStr;
        }
    }

    /**
     * @param int $maxLength
     *
     * @return int
     */
    private function determineFormatLength($maxLength)
    {
        $formatLen = $maxLength > 50 ? 50 : $maxLength;
        $formatMinLen = strlen('Job name') + 1;
        $formatLen = $formatLen < $formatMinLen ? $formatMinLen : $formatLen;

        return $formatLen;
    }
}
