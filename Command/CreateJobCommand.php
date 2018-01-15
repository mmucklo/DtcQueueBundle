<?php

namespace Dtc\QueueBundle\Command;

use Dtc\QueueBundle\Documents\Job;
use Dtc\QueueBundle\Exception\WorkerNotRegisteredException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateJobCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('dtc:queue:create_job')
            ->addArgument('worker_name', InputArgument::REQUIRED, 'Name of worker', null)
            ->addArgument('method', InputArgument::REQUIRED, 'Method of worker to invoke', null)
            ->addArgument('args', InputArgument::IS_ARRAY, 'Argument(s) for invoking worker method')
            ->setDescription('Create a job - for expert users');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $jobManager = $container->get('dtc_queue.manager.job');
        $workerManager = $container->get('dtc_queue.manager.worker');

        $workerName = $input->getArgument('worker_name');
        $methodName = $input->getArgument('method');
        $args = $input->getArgument('args');

        $worker = $workerManager->getWorker($workerName);

        if (!$worker) {
            throw new WorkerNotRegisteredException("Worker `{$workerName}` is not registered.");
        }

        $when = \Dtc\QueueBundle\Util\Util::getMicrotimeDateTime();
        $batch = true;
        $priority = 1;

        $jobClass = $worker->getJobManager()->getJobClass();
        $job = new $jobClass($worker, $batch, $priority, $when);
        $job->setMethod($methodName);
        $job->setArgs($args);

        $jobManager->save($job);
    }
}
