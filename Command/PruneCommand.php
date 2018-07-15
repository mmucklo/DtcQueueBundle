<?php

namespace Dtc\QueueBundle\Command;

use Dtc\QueueBundle\Exception\UnsupportedException;
use Dtc\QueueBundle\Util\IntervalTrait;
use Dtc\QueueBundle\Util\Util;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PruneCommand extends ContainerAwareCommand
{
    use IntervalTrait;

    const OLDER_MESSAGE = '<int>[d|m|y|h|i|s] Specify how old the jobs should (defaults to timestamp unless a quantifier is specified [d_ays, m_onths, y_years, h_ours, i_minutes, s_econds';

    protected function configure()
    {
        $this
        ->setName('dtc:queue:prune')
<<<<<<< HEAD
        ->setDescription('Prune job with error status')
        ->addArgument('type', InputArgument::REQUIRED, '<stalled|stalled_runs|error|expired|old|old_runs|old_job_timings> Prune stalled, erroneous, expired, or old jobs, runs, or job timings')
=======
        ->setDescription('Prune jobs')
        ->addArgument('type', InputArgument::REQUIRED, '<stalled|stalled_runs|exception|expired|old|old_runs|old_job_timings> Prune stalled, exception, expired, or old jobs')
>>>>>>> master
            ->addOption('older', null, InputOption::VALUE_REQUIRED, self::OLDER_MESSAGE);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws \Exception
     */
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
                $container = $this->getContainer();
                $jobManager = $container->get('dtc_queue.manager.job');
                $count = $jobManager->pruneExpiredJobs();
                $output->writeln("$count Expired Job(s) pruned");
                break;
            default:
                return $this->executeStalledOther($input, $output);
        }

        return 0;
    }

<<<<<<< HEAD
    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
=======
    protected function pruneExceptionJobs(OutputInterface $output)
    {
        $container = $this->getContainer();
        $jobManager = $container->get('dtc_queue.manager.job');
        $count = $jobManager->pruneExceptionJobs();
        $output->writeln("$count Job(s) with status 'exception' pruned");
    }

>>>>>>> master
    public function executeStalledOther(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $jobManager = $container->get('dtc_queue.manager.job');
        $type = $input->getArgument('type');
        switch ($type) {
            case 'stalled':
                $count = $jobManager->pruneStalledJobs();
                $output->writeln("$count Stalled Job(s) pruned");
                break;
            case 'stalled_runs':
                $count = $container->get('dtc_queue.manager.run')->pruneStalledRuns();
                $output->writeln("$count Stalled Job(s) pruned");
                break;
            default:
                return $this->executeOlder($input, $output);
        }

        return 0;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
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
        $olderThan = Util::getMicrotimeDateTime();
        if (null === $modifier) {
            $olderThan->setTimestamp($durationOrTimestamp);
        } else {
            $interval = $this->getInterval($modifier, $durationOrTimestamp);
            $olderThan = $olderThan->sub($interval);
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
                $count = $container->get('dtc_queue.manager.job')->pruneArchivedJobs($olderThan);
                $typeName = 'Job';
                break;
            case 'old_runs':
                $count = $container->get('dtc_queue.manager.run')->pruneArchivedRuns($olderThan);
                $typeName = 'Run';
                break;
            case 'old_job_timings':
                $count = $container->get('dtc_queue.manager.job_timing')->pruneJobTimings($olderThan);
                $typeName = 'Job Timing';
                break;
            default:
                throw new UnsupportedException("Unknown type $type");
        }
        $output->writeln("$count Archived $typeName(s) pruned");

        return 0;
    }

}
