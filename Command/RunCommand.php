<?php
namespace Dtc\QueueBundle\Command;

use Dtc\QueueBundle\Model\Job;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

class RunCommand
    extends ContainerAwareCommand
{
    protected $logger;

    protected function configure()
    {
        $this
            ->setName('dtc:queue_worker:run')
            ->setDefinition(
                array(
                    new InputArgument('worker_name', InputArgument::OPTIONAL, 'Name of worker', null),
                    new InputArgument('method', InputArgument::OPTIONAL, 'DI method of worker', null),
                    new inputOption('job_id', 'i', InputOption::VALUE_REQUIRED,
                        'Id of Job to run', null),
                    new inputOption('total_jobs', 't', InputOption::VALUE_REQUIRED,
                        'Total number of job to work on before exiting', 1),
                    new inputOption('timeout', 'to', InputOption::VALUE_REQUIRED,
                        'Process timeout in seconds', 3600),
                    new inputOption('id', null, null, 'Id of a single job to run')
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $this->logger = $container->get('monolog.logger.dtc_queue');

        $workerManager = $container->get('dtc_queue.worker_manager');
        $workerName = $input->getArgument('worker_name');
        $methodName = $input->getArgument('method');
        $totalJobs = $input->getOption('total_jobs', 1);
        $processTimeout = $input->getOption('timeout', 3600);

        // Check to see if there are other instances
        set_time_limit($processTimeout); // Set an hour timeout

        if ($jobId = $input->getOption('job_id')) {
            return $this->runJobById($jobId);   // Run a single job
        }

        try {
            $this->logger->info('Staring up a new job...');

            $currentJob = 1;

            do {
                $job = $workerManager->run($workerName, $methodName);

                if ($job) {
                    $this->reportJob($job);
                    $currentJob++;
                } else {
                    $this->logger->info("No job to run... sleeping");
                    sleep(15); // Sleep for 10 seconds when out of job
                }
            } while ($currentJob <= $totalJobs);
        } catch (\Exception $e) {
            // Uncaught error: possibly with QueueBundle itself
            $this->logger->critical($e->getMessage(), $e);
        }
    }

    protected function reportJob(Job $job)
    {
        if ($job->getStatus() == Job::STATUS_ERROR) {
            $message = "Error with job id: {$job->getId()}\n" . $job->getMessage();
            $this->logger->error($message);
        }

        $message = "Finished job id: {$job->getId()} in {$job->getElapsed()} seconds\n";
        $this->logger->info($message);
    }
}
