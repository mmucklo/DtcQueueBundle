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
                'Consume the args as a single JSON-encoded array'
            )
            ->addOption(
                'php-args',
                'p',
                InputOption::VALUE_NONE,
                'Consume the args as a single PHP-serialized array'
            )
            ->addOption( // For 5.0 this should become the default
                'interpret-args',
                null,
                InputOption::VALUE_NONE,
                '(beta) Make a best guess as to the type of the argument passed in (DEFAULT for future releases - also note the "interpretation" may change in upcoming releases).'
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
                'Argument(s) for invoking worker method'
            )
            ->setDescription('Create a job - for expert users')
            ->setHelp($this->getHelpMessage())
            ->setName(self::$defaultName)
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
                'first parameter', // argv[0] (string)
                null, // argv[1] (null)
                3, // argv[2] (int)
                [ // argv[3] (array)
                    'fourth',
                    'param',
                    'is',
                    'an',
                    'array',
                ],
            ])
        );
        $helpMessage .= PHP_EOL;
        $helpMessage .= '';

        return $helpMessage;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $jobManager = $container->get('dtc_queue.manager.job');
        $workerManager = $container->get('dtc_queue.manager.worker');

        $workerName = $input->getArgument('worker_name');
        $methodName = $input->getArgument('method');

        $args = $this->getArgs($input);

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

    protected function getArgs(InputInterface $input)
    {
        $args = $input->getArgument('args');
        $jsonArgs = $input->getOption('json-args');
        $phpArgs = $input->getOption('php-args');
        $interpretArgs = $input->getOption('interpret-args');
        $this->validateJsonArgs($jsonArgs, $phpArgs, $interpretArgs);

        if ($jsonArgs) {
            return $this->decodeJsonArgs($args);
        }
        if ($phpArgs) {
            return $this->decodePHPArgs($args);
        }
        if ($interpretArgs) {
            return $this->interpretArgs($args);
        }

        return $args;
    }

    protected function validateJsonArgs($jsonArgs, $phpArgs, $interpretArgs)
    {
        if ($jsonArgs) {
            if ($phpArgs || $interpretArgs) {
                throw new \InvalidArgumentException('Should not have both JSON args plus another type of args');
            }
        }

        $this->validatePHPArgs($phpArgs, $interpretArgs);
    }

    protected function validatePHPArgs($phpArgs, $interpretArgs)
    {
        if ($phpArgs) {
            if ($interpretArgs) {
                throw new \InvalidArgumentException('Should not have both PHP args plus another type of args');
            }
        }
    }

    protected function interpretArgs($args)
    {
        if (null === $args || 0 == count($args)) {
            return $args;
        }

        $finalArgs = [];

        foreach ($args as $arg) {
            $finalArgs[] = $this->booleanInterpretation($arg);
        }

        return $finalArgs;
    }

    private function booleanInterpretation($arg)
    {
        if ('true' === $arg || 'TRUE' === $arg) {
            return true;
        }
        if ('false' === $arg || 'FALSE' === $arg) {
            return false;
        }

        return $this->integerInterpretation($arg);
    }

    private function integerInterpretation($arg)
    {
        if (ctype_digit($arg)) {
            $intArg = intval($arg);
            if (strval($intArg) === $arg) {
                return $intArg;
            }
            // Must be a super-long number
            return $arg;
        }

        return $this->floatInterpretation($arg);
    }

    private function floatInterpretation($arg)
    {
        if (is_numeric($arg)) {
            $floatArg = floatval($arg);

            return $floatArg;
        }

        return $this->nullInterpretation($arg);
    }

    private function nullInterpretation($arg)
    {
        if ('null' === $arg || 'NULL' === $arg) {
            return null;
        }

        return $arg;
    }

    protected function decodeJsonArgs($jsonArgs)
    {
        if (1 !== count($jsonArgs)) {
            throw new \InvalidArgumentException('args should be a single string containing a JSON-encoded array when using --json-args');
        }
        $args = json_decode($jsonArgs[0], true);
        if (null === $args) {
            throw new \InvalidArgumentException('unable to decode JSON-encoded args: '.$jsonArgs[0]);
        }

        return $this->testArgs('JSON', $args);
    }

    /**
     * @param string $type
     * @param array  $args
     *
     * @return mixed
     */
    protected function testArgs($type, $args)
    {
        if (!is_array($args)) {
            throw new \InvalidArgumentException('unable to decode '.$type.'-encoded args into an array.');
        }
        if (array_values($args) !== $args) {
            throw new \InvalidArgumentException('Expecting numerically-indexed array in '.$type.' arguments');
        }

        return $args;
    }

    /**
     * @param string $phpArgs
     *
     * @return mixed
     */
    protected function decodePHPArgs($phpArgs)
    {
        if (1 !== count($phpArgs)) {
            throw new \InvalidArgumentException('args should be a single string containing a PHP-encoded array when using --php-args');
        }
        $args = unserialize($phpArgs[0]);

        return $this->testArgs('PHP', $args);
    }
}
