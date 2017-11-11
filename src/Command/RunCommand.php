<?php

namespace Dtc\QueueBundle\Command;

use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Run\Loop;
use Dtc\QueueBundle\Util\Util;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Kernel;

class RunCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $nanoSleep = null;
        if (class_exists('Symfony\Component\HttpKernel\Kernel') && Kernel::VERSION_ID >= 30000) {
            $nanoSleep = 's';
        }

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
                        $nanoSleep,
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $start = microtime(true);
        $container = $this->getContainer();
        $loop = $container->get('dtc_queue.run.loop');
        $loop->setOutput($output);
        $workerName = $input->getArgument('worker_name');
        $methodName = $input->getArgument('method');
        $maxCount = $input->getOption('max_count');
        $duration = $input->getOption('duration');
        $processTimeout = $input->getOption('timeout');
        $nanoSleep = $input->getOption('nano_sleep');
        $loggerService = $input->getOption('logger');

        $this->setLoggerService($loop, $loggerService);

        $maxCount = Util::validateIntNull('max_count', $maxCount, 32);
        $duration = Util::validateIntNull('duration', $duration, 32);
        $nanoSleep = Util::validateIntNull('nano_sleep', $nanoSleep, 63);
        $processTimeout = Util::validateIntNull('timeout', $processTimeout, 32);
        $loop->checkMaxCountDuration($maxCount, $duration, $processTimeout);

        // Check to see if there are other instances
        set_time_limit($processTimeout); // Set timeout on the process

        if ($jobId = $input->getOption('id')) {
            return $loop->runJobById($start, $jobId); // Run a single job
        }

        return $loop->runLoop($start, $workerName, $methodName, $maxCount, $duration, $nanoSleep);
    }

    protected function setLoggerService(Loop $loop, $loggerService)
    {
        if (!$loggerService) {
            return;
        }

        $container = $this->getContainer();
        if (!$container->has($loggerService)) {
            return;
        }

        $logger = $container->get($loggerService);
        if (!$logger instanceof LoggerInterface) {
            throw new \Exception("$loggerService must be instance of Psr\\Log\\LoggerInterface");
        }
        $loop->setLogger($logger);
    }
}
