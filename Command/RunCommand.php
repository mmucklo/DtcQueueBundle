<?php

namespace Dtc\QueueBundle\Command;

use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\Run;
use Dtc\QueueBundle\Util\Util;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends ContainerAwareCommand
{
    protected $logger;
    protected $runManager;
    /** @var Run $run */
    protected $run;
    protected $runClass;
    protected $runArchiveClass;

    protected function configure()
    {
        $this
            ->setName('dtc:queue_worker:run')
            ->setDefinition(
                array(
                    new InputArgument('worker_name', InputArgument::OPTIONAL, 'Name of worker', null),
                    new InputArgument('method', InputArgument::OPTIONAL, 'DI method of worker', null),
                    new InputOption('job_id', 'i', InputOption::VALUE_REQUIRED,
                        'Id of Job to run', null),
                    new InputOption('max_count', 'm', InputOption::VALUE_REQUIRED,
                        'Maximum number of jobs to work on before exiting', null),
                    new InputOption('duration', 'd', InputOption::VALUE_REQUIRED,
                        'Duration to run for in seconds', null),
                    new InputOption('timeout', 'to', InputOption::VALUE_REQUIRED,
                        'Process timeout in seconds (hard exit of process regardless)', 3600),
                    new InputOption('nano_sleep', 'to', InputOption::VALUE_REQUIRED,
                        'If using duration, this is the time to sleep when there\'s no jobs in nanoseconds', 500000000),
                    new InputOption('id', null, null, 'Id of a single job to run'),
                )
            )
            ->setDescription('Start up a job in queue');
    }

    protected function runJobById($jobId)
    {
        $container = $this->getContainer();
        $jobManager = $container->get('dtc_queue.job_manager');
        $workerManager = $container->get('dtc_queue.worker_manager');

        $job = $jobManager->getRepository()->find($jobId);
        if (!$job) {
            $this->logger->debug("Job id is not found: {$jobId}");

            return;
        }

        $job = $workerManager->runJob($job);
        $this->reportJob($job);

        return;
    }

    private function validateIntNull($varName, $var, $pow)
    {
        if (null === $var) {
            return null;
        }
        if (!ctype_digit(strval($var))) {
            throw new \Exception("$varName must be an integer");
        }

        if (strval(intval($var)) !== strval($var) || $var <= 0 || $var >= pow(2, $pow)) {
            throw new \Exception("$varName must be an base 10 integer within 2^32");
        }

        return intval($var);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $this->logger = $container->get('monolog.logger.dtc_queue');

        $workerManager = $container->get('dtc_queue.worker_manager');
        $workerName = $input->getArgument('worker_name');
        $methodName = $input->getArgument('method');
        $maxCount = $input->getOption('max_count', null);
        $duration = $input->getOption('duration', null);
        $processTimeout = $input->getOption('timeout', 3600);
        $nanoSleep = $input->getOption('nano_sleep', 500000000);

        $maxCount = $this->validateIntNull('max_count', $maxCount, 32);
        $duration = $this->validateIntNull('duration', $duration, 32);
        $processTimeout = $this->validateIntNull('timeout', $processTimeout, 32);
        $nanoSleep = $this->validateIntNull('nano_sleep', $nanoSleep, 63);

        // Check to see if there are other instances
        set_time_limit($processTimeout); // Set timeout on the process

        if ($jobId = $input->getOption('job_id')) {
            return $this->runJobById($jobId);   // Run a single job
        }

        $this->runStart($maxCount, $duration);
        try {
            $this->logger->info('Staring up a new job...');

            $endTime = null;
            if ($duration) {
                $interval = new \DateInterval("PT${duration}S");
                $endTime = $this->run->getStartedAt()->add($interval);
            }

            $currentJob = 1;
            do {
                $job = $workerManager->run($workerName, $methodName);

                if ($job) {
                    $this->reportJob($job);
                    $this->run->setProcessed($currentJob);
                    $this->run->setLastHeartbeatAt(new \DateTime());
                    if ($this->runManager) {
                        $this->runManager->persist($this->run);
                        $this->runManager->flush();
                    }
                    ++$currentJob;
                } else {
                    $this->logger->info('No job to run...');
                    if ($maxCount && !$duration) {
                        // time to finish
                        $this->end();

                        return;
                    }
                    time_nanosleep(0, $nanoSleep); // 500ms ??
                }
            } while ((!$maxCount || $currentJob <= $maxCount) && (!$duration || (new \DateTime()) < $endTime));
        } catch (\Exception $e) {
            // Uncaught error: possibly with QueueBundle itself
            $this->logger->critical($e->getMessage(), $e->getTrace());
        }
        $this->end();
    }

    protected function runStart($maxCount, $duration)
    {
        $container = $this->getContainer();
        $defaultManager = $container->getParameter('dtc_queue.default_manager');
        switch ($defaultManager) {
            case 'mongodb':
                $this->runManager = $container->get('dtc_queue.document_manager');
                $this->runClass = 'Dtc\QueueBundle\Document\Run';
                $this->runArchiveClass = 'Dtc\QueueBundle\Document\RunArchive';
                break;
            case 'orm':
                $this->runManager = $container->get('dtc_queue.entity_manager');
                $this->runClass = 'Dtc\QueueBundle\Entity\Run';
                $this->runArchiveClass = 'Dtc\QueueBundle\Entity\RunArchive';
                break;
            default:
                $this->runClass = 'Dtc\QueueBundle\Model\Run';
                $this->runArchiveClass = 'Dtc\QueueBundle\Model\RunArchive';
        }

        $this->run = new $this->runClass();
        $start = new \DateTime();
        $this->run->setLastHeartbeatAt($start);
        $this->run->setStartedAt($start);
        if (null !== $maxCount) {
            $this->run->setMaxCount($maxCount);
        }
        $timeEnd = null;
        if (null !== $duration) {
            $this->run->setDuration($duration);
        }
        $this->run->setHostname(gethostname());
        $this->run->setPid(getmypid());
        if ($this->runManager) {
            $this->runManager->persist($this->run);
            $this->runManager->flush();
        }
    }

    protected function end()
    {
        $this->run->setEndedAt(new \DateTime());
        if ($this->runManager) {
            $runArchive = new $this->runArchiveClass();
            Util::copy($this->run, $runArchive);
            $this->runManager->persist($runArchive);
            $this->runManager->remove($this->run);
            $this->runManager->flush();
        }
    }

    protected function reportJob(Job $job)
    {
        if (Job::STATUS_ERROR == $job->getStatus()) {
            $message = "Error with job id: {$job->getId()}\n".$job->getMessage();
            $this->logger->error($message);
        }

        $message = "Finished job id: {$job->getId()} in {$job->getElapsed()} seconds\n";
        $this->logger->info($message);
    }
}
