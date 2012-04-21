<?php
namespace Dtc\QueueBundle\Command;

use Asc\PlatformBundle\Documents\Profile\UserProfile;
use Asc\PlatformBundle\Documents\UserAuth;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

class RunCommand
    extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
        ->setName('dtc:queue_worker:run')
        ->addArgument('worker_name', InputArgument::OPTIONAL, 'Name of worker')
        ->addArgument('method', InputArgument::OPTIONAL, 'DI method of worker')
        ->addOption('threads', 't', InputOption::VALUE_REQUIRED, 'Total number of simultaneous threads', 1)
        ->setDescription('Start up a job in queue')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $workerManager = $container->get('dtc_queue.worker_manager');
        $workerName = $input->getArgument('worker_name');
        $methodName = $input->getArgument('method');
        $threads = $input->getOption('threads');
        $logger = $container->get('monolog.logger.dtc_queue');
        $lockFile = $container->getParameter('dtc_queue.lock_file');

        // Check to see if there are other instances
        $processCount = intval(shell_exec  ('ps -ef | grep dtc:queue_worker:run | grep -vc grep'));

        $processCount = 0;
        if (file_exists($lockFile))
        {
            $processCount = intval(file_get_contents($lockFile));
        }

        // Exit if total process running is less than threads count
        if ($processCount >= $threads) {
            $logger->debug("Total of {$processCount} >= {$threads} running, exiting...");
            exit();
        }

        file_put_contents($lockFile, ++$processCount);

        set_time_limit(3600);    // Set an hour timeout

        try {
            $logger->debug("Staring up a new job...");
            $job = $workerManager->run($workerName, $methodName);

            if ($job) {
                $output->writeln("Finished job id: {$job->getId()}");
            }
            else {
                $output->writeln("No job to run... sleeping");
                sleep(15);        // Sleep for 10 seconds when out of job
            }
        } catch (\Exception $e) {
            if ($msg = $e->getMessage()) {
                $output->writeln('<error>[error]</error> '.$msg);
            }

            // Seem like job had some error...
        }

        $logger->debug("Total process via lock file: " . file_get_contents($lockFile));
        $logger->debug("Finished a new job");
        file_put_contents($lockFile, --$processCount);
    }
}
