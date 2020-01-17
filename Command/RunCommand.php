<?php

namespace Dtc\QueueBundle\Command;

use Dtc\QueueBundle\Exception\ClassNotSubclassException;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Run\Loop;
use Dtc\QueueBundle\Util\Util;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Kernel;

class RunCommand extends Command
{
    protected $loggerPrivate = false;
    protected $nanoSleepOption = null;

    /** @var Loop */
    private $runLoop;

    protected function symfonyDetect()
    {
        $this->nanoSleepOption = null;
        if (class_exists('Symfony\Component\HttpKernel\Kernel')) {
            if (Kernel::VERSION_ID >= 30000) {
                $this->nanoSleepOption = 's';
            }
            if (Kernel::VERSION_ID >= 30400) {
                $this->loggerPrivate = true;
            }
        }
    }

    protected function configure()
    {
        $this->symfonyDetect();
        $options = [
            new InputArgument('worker-name', InputArgument::OPTIONAL, 'Name of worker', null),
            new InputArgument('method', InputArgument::OPTIONAL, 'DI method of worker', null),
            new InputOption(
                'id',
                'i',
                InputOption::VALUE_REQUIRED,
                'Id of Job to run',
                null
            ),
            new InputOption(
                'max-count',
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
                'nano-sleep',
                $this->nanoSleepOption,
                InputOption::VALUE_REQUIRED,
                'If using duration, this is the time to sleep when there\'s no jobs in nanoseconds',
                500000000
            ),
            new InputOption(
                'disable-gc',
                null,
                InputOption::VALUE_NONE,
                'Disable garbage collection'
            ),
        ];

        // Symfony 4 and Symfony 3.4 out-of-the-box makes the logger private
        if (!$this->loggerPrivate) {
            $options[] =
                new InputOption(
                    'logger',
                    'l',
                    InputOption::VALUE_REQUIRED,
                    'Log using the logger service specified, or output to console if null (or an invalid logger service id) is passed in'
                );
        }

        $this
            ->setName('dtc:queue:run')
            ->setDefinition($options)
            ->setDescription('Start up a job in queue');
    }

    public function setRunLoop($runLoop)
    {
        $this->runLoop = $runLoop;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $start = microtime(true);
        // @TODO: move this to dependency injection.
        $this->runLoop->setOutput($output);
        $workerName = $input->getArgument('worker-name');
        $methodName = $input->getArgument('method');
        $maxCount = $input->getOption('max-count');
        $duration = $input->getOption('duration');
        $processTimeout = $input->getOption('timeout');
        $nanoSleep = $input->getOption('nano-sleep');
        $loggerService = !$this->loggerPrivate ? $input->getOption('logger', null) : null;
        $disableGc = $input->getOption('disable-gc', false);
        $this->setGc($disableGc);

        $this->setLoggerService($this->runLoop, $loggerService);

        $maxCount = Util::validateIntNull('max_count', $maxCount, 32);
        $duration = Util::validateIntNull('duration', $duration, 32);
        $nanoSleep = Util::validateIntNull('nano_sleep', $nanoSleep, 63);
        $processTimeout = Util::validateIntNull('timeout', $processTimeout, 32);
        $this->runLoop->checkMaxCountDuration($maxCount, $duration, $processTimeout);

        // Check to see if there are other instances
        set_time_limit($processTimeout); // Set timeout on the process

        if ($jobId = $input->getOption('id')) {
            return $this->runLoop->runJobById($start, $jobId); // Run a single job
        }

        return $this->runLoop->runLoop($start, $workerName, $methodName, $maxCount, $duration, $nanoSleep);
    }

    /**
     * @param bool $disableGc
     */
    protected function setGc($disableGc)
    {
        if ($disableGc) {
            if (gc_enabled()) {
                gc_disable();
            }

            return;
        }

        if (!gc_enabled()) {
            gc_enable();
        }
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
            throw new ClassNotSubclassException("$loggerService must be instance of Psr\\Log\\LoggerInterface");
        }
        $loop->setLogger($logger);
    }
}
