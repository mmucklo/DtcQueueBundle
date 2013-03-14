<?php
namespace Dtc\QueueBundle\Command;

use Dtc\QueueBundle\Documents\Job;

use Asc\PlatformBundle\Documents\Profile\UserProfile;
use Asc\PlatformBundle\Documents\UserAuth;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateJobCommand
    extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
        ->setName('dtc:queue_worker:create_job')
        ->addArgument('worker_name', InputArgument::REQUIRED, 'Name of worker', null)
        ->addArgument('method', InputArgument::REQUIRED, 'Method of worker to invoke', null)
        ->addArgument('args', InputArgument::IS_ARRAY, 'Argument(s) for invoking worker method')
        ->setDescription('Create a job - for expert users')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $jobManager = $container->get('dtc_queue.job_manager');
        $workerManager = $container->get('dtc_queue.worker_manager');

        $workerName = $input->getArgument('worker_name');
        $methodName = $input->getArgument('method');
        $args = $input->getArgument('args');

        $worker = $workerManager->getWorker($workerName);

        if (!$worker) {
            throw new \Exception("Worker `{$workerName}` is not registered.");
        }

        $when = new \DateTime();
        $batch = true;
        $priority = 1;

        $job = new Job($worker, $when, $batch, $priority);
        $job->method = $method;
        $job->args = $args;
        $job->createdAt = $when;
        $job->updatedAt = $when;

        ve($job);
        $jobManager->save($job);
        v($job);
        $output->writeln("Total jobs: {$count}");
    }
}
