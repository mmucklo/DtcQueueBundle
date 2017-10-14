<?php

namespace Dtc\QueueBundle\Command;

use Doctrine\Common\Persistence\ObjectManager;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\Run;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends ContainerAwareCommand
{
    /** @var ObjectManager */
    protected $runManager;

    /** @var Run $run */
    protected $run;

    /** @var string */
    protected $runClass;

    /** @var OutputInterface */
    protected $output;

    /** @var LoggerInterface */
    protected $logger;

    protected function configure()
    {
        $this
            ->setName('dtc:queue:run')
            ->setDefinition(
                array(
                    new InputArgument('worker_name', InputArgument::OPTIONAL, 'Name of worker', null),
                    new InputArgument('method', InputArgument::OPTIONAL, 'DI method of worker', null),
                    new InputOption(
                        'id',
                        'i',
                        InputOption::VALUE_REQUIRED,
                        'Id of Job to run',
                        null
                    ),
                    new InputOption(
                        'max_count',
                        'm',
                        InputOption::VALUE_REQUIRED,
                        'Maximum number of jobs to work on before exiting',
                        null
                    ),
                    new InputOption(
                        'duration',
                        'd',
                        InputOption::VALUE_REQUIRED,
                        'Duration to run for in seconds',
                        null
                    ),
                    new InputOption(
                        'timeout',
                        't',
                        InputOption::VALUE_REQUIRED,
                        'Process timeout in seconds (hard exit of process regardless)',
                        3600
                    ),
                    new InputOption(
                        'nano_sleep',
                        's',
                        InputOption::VALUE_REQUIRED,
                        'If using duration, this is the time to sleep when there\'s no jobs in nanoseconds',
                        500000000
                    ),
                    new InputOption(
                        'logger',
                        'l',
                        InputOption::VALUE_REQUIRED,
                        'Log using the logger service specified, or output to console if null (or an invalid logger service id) is passed in'
                    ),
                )
            )
            ->setDescription('Start up a job in queue');
    }

    protected function runJobById($start, $jobId)
    {
        $this->runStart($start);
        $container = $this->getContainer();
        $jobManager = $container->get('dtc_queue.job_manager');
        $workerManager = $container->get('dtc_queue.worker_manager');

        $job = $jobManager->getRepository()->find($jobId);
        if (!$job) {
            $this->log('error', "Job id is not found: {$jobId}");
            $this->runStop($start);

            return;
        }

        $this->
        $job = $workerManager->runJob($job);
        $this->reportJob($job);
        $this->run->setProcessed(1);
        $this->runStop($start);

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

    public function log($level, $msg, array $context = [])
    {
        if ($this->logger) {
            $this->logger->$level($msg, $context);

            return;
        }

        $date = new \DateTime();
        $this->output->write("[$level] [".$date->format('c').'] '.$msg);
        if ($context) {
            $this->output->write(print_r($context, true));
        }
        $this->output->writeln('');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $start = microtime(true);
        $this->output = $output;
        $container = $this->getContainer();
        $workerName = $input->getArgument('worker_name');
        $methodName = $input->getArgument('method');
        $maxCount = $input->getOption('max_count', null);
        $duration = $input->getOption('duration', null);
        $processTimeout = $input->getOption('timeout', 3600);
        $nanoSleep = $input->getOption('nano_sleep', 500000000);
        $loggerService = $input->getOption('logger');

        if ($container->has($loggerService)) {
            $this->logger = $container->get($loggerService);
        }

        $maxCount = $this->validateIntNull('max_count', $maxCount, 32);
        $duration = $this->validateIntNull('duration', $duration, 32);
        $processTimeout = $this->validateIntNull('timeout', $processTimeout, 32);
        $nanoSleep = $this->validateIntNull('nano_sleep', $nanoSleep, 63);

        if (null === $maxCount && null === $duration) {
            $maxCount = 1;
        }
        // Check to see if there are other instances
        set_time_limit($processTimeout); // Set timeout on the process

        if ($jobId = $input->getOption('id')) {
            return $this->runJobById($start, $jobId);   // Run a single job
        }

        return $this->runLoop($start, $workerName, $methodName, $nanoSleep, $maxCount, $duration);
    }

    protected function runLoop($start, $workerName, $methodName, $nanoSleep, $maxCount, $duration)
    {
        $container = $this->getContainer();
        $workerManager = $container->get('dtc_queue.worker_manager');
        $workerManager->setLoggingFunc([$this, 'log']);
        $this->runStart($start, $maxCount, $duration);
        try {
            $this->log('info', 'Staring up a new job...');

            $endTime = null;
            if ($duration) {
                $interval = new \DateInterval("PT${duration}S");
                $endTime = $this->run->getStartedAt()->add($interval);
            }

            $currentJob = 1;
            $noMoreJobsToRun = false;
            do {
                $this->run->setLastHeartbeatAt(new \DateTime());
                $this->run->setElapsed(microtime(true) - $start);
                if ($this->runManager) {
                    $this->runManager->persist($this->run);
                    $this->runManager->flush();
                }

                $job = $workerManager->run($workerName, $methodName, true, $this->run->getId());
                if ($job) {
                    $noMoreJobsToRun = false;
                    $this->reportJob($job);
                    $this->run->setProcessed($currentJob);
                    if ($this->runManager) {
                        $this->runManager->persist($this->run);
                        $this->runManager->flush();
                    }
                    ++$currentJob;
                } else {
                    if (!$noMoreJobsToRun) {
                        $this->log('info', 'No more jobs to run ('.($currentJob - 1).' processed so far).');
                        $noMoreJobsToRun = true;
                    }
                    if ($maxCount && !$duration) {
                        // time to finish
                        $this->runStop($start);

                        return 0;
                    }
                    $nanoSleepTime = function_exists('random_int') ? random_int(0, $nanoSleep) : mt_rand(0, $nanoSleep);
                    time_nanosleep(0, $nanoSleepTime); // 500ms ??
                }
            } while ((null === $maxCount || $currentJob <= $maxCount) && (null === $duration || (new \DateTime()) < $endTime));
        } catch (\Exception $e) {
            // Uncaught error: possibly with QueueBundle itself
            $this->log('critical', $e->getMessage(), $e->getTrace());
        }
        $this->runStop($start);

        return 0;
    }

    /**
     * Sets up the runManager (document / entity persister) if appropriate.
     *
     * @param $maxCount
     * @param $duration
     */
    protected function runStart($start, $maxCount = null, $duration = null)
    {
        $container = $this->getContainer();
        $defaultManager = $container->getParameter('dtc_queue.default_manager');
        if ('mongodb' == $defaultManager && $container->has('dtc_queue.document_manager')) {
            $this->runManager = $container->get('dtc_queue.document_manager');
        } elseif ('orm' == $defaultManager && $container->has('dtc_queue.entity_manager')) {
            $this->runManager = $container->get('dtc_queue.entity_manager');
        }

        $this->runClass = $container->getParameter('dtc_queue.run_class');

        $this->run = new $this->runClass();
        $startDate = \DateTime::createFromFormat('U.u', $start);
        $this->run->setLastHeartbeatAt($startDate);
        $this->run->setStartedAt($startDate);
        if (null !== $maxCount) {
            $this->run->setMaxCount($maxCount);
        }
        $timeEnd = null;
        if (null !== $duration) {
            $this->run->setDuration($duration);
        }
        $this->run->setHostname(gethostname());
        $this->run->setPid(getmypid());
        $this->run->setProcessed(0);
        if ($this->runManager) {
            $this->runManager->persist($this->run);
            $this->runManager->flush();
        }
    }

    protected function runStop($start)
    {
        $end = microtime(true);
        $endTime = \DateTime::createFromFormat('U.u', $end);
        $this->run->setEndedAt($endTime);
        $this->run->setElapsed($end - $start);
        if ($this->runManager) {
            $this->runManager->remove($this->run);
            $this->runManager->flush();
        }
        $this->log('info', 'Ended with '.$this->run->getProcessed().' job(s) processed over '.strval($this->run->getElapsed()).' seconds.');
    }

    protected function reportJob(Job $job)
    {
        if (Job::STATUS_ERROR == $job->getStatus()) {
            $message = "Error with job id: {$job->getId()}\n".$job->getMessage();
            $this->log('error', $message);
        }

        $message = "Finished job id: {$job->getId()} in {$job->getElapsed()} seconds\n";
        $this->log('info', $message);
    }
}
