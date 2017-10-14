<?php

namespace Dtc\QueueBundle\Command;

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
        ->addArgument('type', InputArgument::REQUIRED, '<stalled|error|expired|old> Prune stalled, erroneous, expired, or old jobs')
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
            case 'stalled':
                $count = $jobManager->pruneStalledJobs();
                $output->writeln("$count Stalled Job(s) pruned");
                break;
            case 'old':
                $older = $input->getOption('older');
                if (!$older) {
                    $output->writeln('<error>--older must be specified</error>');

                    return 1;
                }
                if (!preg_match("/(\d+)([d|m|y|h|i|s]){0,1}/", $older, $matches)) {
                    $output->writeln('<error>Wrong format for --older</error>');

                    return 1;
                }

                return $this->pruneOldJobs($matches, $output);
            default:
                $output->writeln("<error>Unknown type $type.</error>");

                return 1;
        }

        return 0;
    }

    /**
     * @param string[]        $matches
     * @param OutputInterface $output
     *
     * @return int
     *
     * @throws \Exception
     */
    protected function pruneOldJobs(array $matches, OutputInterface $output)
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
            switch ($modifier) {
                case 'd':
                    $interval = new \DateInterval("P${durationOrTimestamp}D");
                    break;
                case 'm':
                    $interval = new \DateInterval("P${durationOrTimestamp}M");
                    break;
                case 'y':
                    $interval = new \DateInterval("P${durationOrTimestamp}Y");
                    break;
                case 'h':
                    $interval = new \DateInterval("PT${durationOrTimestamp}H");
                    break;
                case 'i':
                    $seconds = $durationOrTimestamp * 60;
                    $interval = new \DateInterval("PT${seconds}S");
                    break;
                case 's':
                    $interval = new \DateInterval("PT${durationOrTimestamp}S");
                    break;
                default:
                    throw new \Exception("Unknown duration modifier: $modifier");
            }
            $olderThan->sub($interval);
        }
        $container = $this->getContainer();
        $count = $container->get('dtc_queue.job_manager')->pruneArchivedJobs($olderThan);
        $output->writeln("$count Archived Job(s) pruned");

        return 0;
    }
}
