<?php

namespace Dtc\QueueBundle\Command;

use Dtc\QueueBundle\Exception\UnsupportedException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PruneCommand extends ContainerAwareCommand
{
    const OLDER_MESSAGE = '<int>[d|m|y|h|i|s] Specify how old the jobs should (defaults to timestamp unless a quantifier is specified [d_ays, m_onths, y_years, h_ours, i_minutes, s_econds';

    protected function configure()
    {
        $this
        ->setName('dtc:queue:prune')
        ->setDescription('Prune job with error status')
        ->addArgument('type', InputArgument::REQUIRED, '<stalled|stalled_runs|error|expired|old|old_runs|old_job_timings> Prune stalled, erroneous, expired, or old jobs')
            ->addOption('older', null, InputOption::VALUE_REQUIRED, self::OLDER_MESSAGE);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $jobManager = $container->get('dtc_queue.job_manager');
        $type = $input->getArgument('type');
        switch ($type) {
            case 'error':
                $count = $jobManager->pruneErroneousJobs();
                $output->writeln("$count Erroneous Job(s) pruned");
                break;
            case 'expired':
                $count = $jobManager->pruneExpiredJobs();
                $output->writeln("$count Expired Job(s) pruned");
                break;
            default:
                return $this->executeStalledOther($input, $output);
        }

        return 0;
    }

    public function executeStalledOther(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $jobManager = $container->get('dtc_queue.job_manager');
        $type = $input->getArgument('type');
        switch ($type) {
            case 'stalled':
                $count = $jobManager->pruneStalledJobs();
                $output->writeln("$count Stalled Job(s) pruned");
                break;
            case 'stalled_runs':
                $count = $container->get('dtc_queue.run_manager')->pruneStalledRuns();
                $output->writeln("$count Stalled Job(s) pruned");
                break;
            default:
                return $this->executeOlder($input, $output);
        }

        return 0;
    }

    public function executeOlder(InputInterface $input, OutputInterface $output)
    {
        $older = $input->getOption('older');
        $type = $input->getArgument('type');
        if (!$older) {
            $output->writeln('<error>--older must be specified</error>');

            return 1;
        }
        if (!preg_match("/(\d+)([d|m|y|h|i|s]){0,1}$/", $older, $matches)) {
            $output->writeln('<error>Wrong format for --older</error>');

            return 1;
        }

        return $this->pruneOldJobs($matches, $type, $output);
    }

    /**
     * @param string[]        $matches
     * @param string          $type
     * @param OutputInterface $output
     *
     * @return int
     *
     * @throws \Exception
     */
    protected function pruneOldJobs(array $matches, $type, OutputInterface $output)
    {
        $durationOrTimestamp = intval($matches[1]);
        $modifier = isset($matches[2]) ? $matches[2] : null;

        if (!$durationOrTimestamp) {
            $output->writeln('<error>No duration or timestamp passed in.</error>');

            return 1;
        }
        $olderThan = new \DateTime();
        if (null === $modifier) {
            $olderThan->setTimestamp($durationOrTimestamp);
        } else {
            $interval = $this->getInterval($modifier, $durationOrTimestamp);
            $olderThan->sub($interval);
        }

        return $this->pruneOlderThan($type, $olderThan, $output);
    }

    /**
     * @param string          $type
     * @param \DateTime       $olderThan
     * @param OutputInterface $output
     *
     * @return int
     *
     * @throws UnsupportedException
     */
    protected function pruneOlderThan($type, \DateTime $olderThan, OutputInterface $output)
    {
        $container = $this->getContainer();
        $typeName = null;
        switch ($type) {
            case 'old':
                $count = $container->get('dtc_queue.job_manager')->pruneArchivedJobs($olderThan);
                $typeName = 'Job';
                break;
            case 'old_runs':
                $count = $container->get('dtc_queue.run_manager')->pruneArchivedRuns($olderThan);
                $typeName = 'Run';
                break;
            case 'old_job_timings':
                $count = $container->get('dtc_queue.run_manager')->pruneJobTimings($olderThan);
                $typeName = 'Job Timing';
                break;
            default:
                throw new UnsupportedException("Unknown type $type");
        }
        $output->writeln("$count Archived $typeName(s) pruned");

        return 0;
    }

    /**
     * Returns the date interval based on the modifier and the duration.
     *
     * @param string $modifier
     * @param int    $duration
     *
     * @return \DateInterval
     *
     * @throws UnsupportedException
     */
    protected function getInterval($modifier, $duration)
    {
        switch ($modifier) {
            case 'd':
                $interval = new \DateInterval("P${duration}D");
                break;
            case 'm':
                $interval = new \DateInterval("P${duration}M");
                break;
            case 'y':
                $interval = new \DateInterval("P${duration}Y");
                break;
            default:
                $interval = $this->getIntervalTime($modifier, $duration);
        }

        return $interval;
    }

    /**
     * @param string $modifier
     * @param int    $duration
     *
     * @return \DateInterval
     *
     * @throws UnsupportedException
     */
    protected function getIntervalTime($modifier, $duration)
    {
        switch ($modifier) {
            case 'h':
                $interval = new \DateInterval("PT${duration}H");
                break;
            case 'i':
                $seconds = $duration * 60;
                $interval = new \DateInterval("PT${seconds}S");
                break;
            case 's':
                $interval = new \DateInterval("PT${duration}S");
                break;
            default:
                throw new UnsupportedException("Unknown duration modifier: $modifier");
        }

        return $interval;
    }
}
