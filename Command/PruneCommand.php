<?php

namespace Dtc\QueueBundle\Command;

use Dtc\QueueBundle\Exception\UnsupportedException;
use Dtc\QueueBundle\Manager\JobManagerInterface;
use Dtc\QueueBundle\Manager\JobTimingManager;
use Dtc\QueueBundle\Manager\RunManager;
use Dtc\QueueBundle\Util\Util;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PruneCommand extends Command
{
    public const OLDER_MESSAGE = '<int>[d|m|y|h|i|s] Specify how old the jobs should (defaults to timestamp unless a quantifier is specified [d_ays, m_onths, y_years, h_ours, i_minutes, s_econds';

    /** @var JobManagerInterface */
    private $jobManager;

    /** @var RunManager */
    private $runManager;

    /** @var JobTimingManager */
    private $jobTimingManager;

    protected function configure()
    {
        $this
        ->setName('dtc:queue:prune')
        ->setDescription('Prune jobs')
        ->addArgument('type', InputArgument::REQUIRED, '<stalled|stalled_runs|exception|expired|old|old_runs|old_job_timings> Prune stalled, exception, expired, or old jobs')
            ->addOption('older', null, InputOption::VALUE_REQUIRED, self::OLDER_MESSAGE);
    }

    public function setJobManager($jobManager)
    {
        $this->jobManager = $jobManager;
    }

    public function setRunManager($runManager)
    {
        $this->runManager = $runManager;
    }

    public function setJobTimingManager($jobTimingManager)
    {
        $this->jobTimingManager = $jobTimingManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $type = $input->getArgument('type');
        switch ($type) {
            case 'erroneous':
                $output->writeln("(Warning): 'erroneous' is deprecated, please use 'exception' instead");
                $this->pruneExceptionJobs($output);
                break;
            case 'exception':
                $this->pruneExceptionJobs($output);
                break;
            case 'expired':
                $count = $this->jobManager->pruneExpiredJobs();
                $output->writeln("$count Expired Job(s) pruned");
                break;
            default:
                return $this->executeStalledOther($input, $output);
        }

        return 0;
    }

    protected function pruneExceptionJobs(OutputInterface $output)
    {
        // @TODO: move this to dependency injection.
        $count = $this->jobManager->pruneExceptionJobs();
        $output->writeln("$count Job(s) with status 'exception' pruned");
    }

    public function executeStalledOther(InputInterface $input, OutputInterface $output)
    {
        $type = $input->getArgument('type');
        switch ($type) {
            case 'stalled':
                $count = $this->jobManager->pruneStalledJobs();
                $output->writeln("$count Stalled Job(s) pruned");
                break;
            case 'stalled_runs':
                $count = $this->runManager->pruneStalledRuns();
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
     * @param string[] $matches
     * @param string   $type
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
        $olderThan = Util::getMicrotimeDateTime();
        if (null === $modifier) {
            $olderThan->setTimestamp($durationOrTimestamp);
        } else {
            $interval = $this->getInterval($modifier, $durationOrTimestamp);
            $olderThan->sub($interval);
        }

        return $this->pruneOlderThan($type, $olderThan, $output);
    }

    /**
     * @param string $type
     *
     * @return int
     *
     * @throws UnsupportedException
     */
    protected function pruneOlderThan($type, \DateTime $olderThan, OutputInterface $output)
    {
        $typeName = null;
        switch ($type) {
            case 'old':
                $count = $this->jobManager->pruneArchivedJobs($olderThan);
                $typeName = 'Job';
                break;
            case 'old_runs':
                $count = $this->runManager->pruneArchivedRuns($olderThan);
                $typeName = 'Run';
                break;
            case 'old_job_timings':
                $count = $this->jobTimingManager->pruneJobTimings($olderThan);
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
                $interval = new \DateInterval("P{$duration}D");
                break;
            case 'm':
                $interval = new \DateInterval("P{$duration}M");
                break;
            case 'y':
                $interval = new \DateInterval("P{$duration}Y");
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
                $interval = new \DateInterval("PT{$duration}H");
                break;
            case 'i':
                $seconds = $duration * 60;
                $interval = new \DateInterval("PT{$seconds}S");
                break;
            case 's':
                $interval = new \DateInterval("PT{$duration}S");
                break;
            default:
                throw new UnsupportedException("Unknown duration modifier: $modifier");
        }

        return $interval;
    }
}
