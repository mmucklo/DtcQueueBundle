<?php

namespace Dtc\QueueBundle\Command;

use Dtc\QueueBundle\Exception\WorkerNotRegisteredException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateJobCommand extends ContainerAwareCommand
{
    protected static $defaultName = 'dtc:queue:create_job';

    protected function configure()
    {
        $this
            ->addOption(
                'json-args',
                'j',
                InputOption::VALUE_NONE,
                'Support json arguments (using the old arguments as strings is deprecated)'
            )
            ->addArgument(
                'worker_name',
                InputArgument::REQUIRED,
                'Name of worker',
                null
            )
            ->addArgument(
                'method',
                InputArgument::REQUIRED,
                'Method of worker to invoke',
                null
            )
            ->addArgument(
                'args',
                InputArgument::IS_ARRAY,
                'Json encoded argument(s) for invoking worker method'
            )
            ->setDescription('Create a job - for expert users')
            ->setHelp($this->getHelpMessage())
        ;
    }

    private function getHelpMessage()
    {
        $helpMessage = sprintf(
            "%s --json-args %s %s '%s'".PHP_EOL,
            $this->getName(), // command
            'my-worker-name', // worker_name
            'myMethod', // method
            json_encode([ // args
                "first parameter", // argv[0] (string)
                null, // argv[1] (null)
                3, // argv[2] (int)
                [ // argv[3] (array)
                    "fourth",
                    "param",
                    "is",
                    "an",
                    "array",
                ]
            ])
        );
        $helpMessage .= PHP_EOL;
        $helpMessage .= "";
        return $helpMessage;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $jobManager = $container->get('dtc_queue.manager.job');
        $workerManager = $container->get('dtc_queue.manager.worker');

        $jsonArgs = $input->getOption('json-args');
        $workerName = $input->getArgument('worker_name');
        $methodName = $input->getArgument('method');
        $args = $input->getArgument('args');
        if ($jsonArgs) {
            if (1 !== count($args)) {
                throw new \InvalidArgumentException('args should be a single string when using --json-args');
            }
            $args = json_decode($args[0], true);
        } else {
            trigger_error(
                'Not Using --json-args is deprecated as of 4.7.2 and will become the default in 5.x',
                E_USER_DEPRECATED
            );
        }

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
