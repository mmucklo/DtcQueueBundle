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
        ->addOption('period', null, InputOption::VALUE_REQUIRED, 'Set the polling period in seconds', 1)
        ->setDescription('Start up a job in queue')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $period = $input->getOption('period');
        $workerManager = $container->get('dtc_queue.worker_manager');
        $workerName = $input->getArgument('worker_name');
        $methodName = $input->getArgument('method');

        while (true) {
            try {
                $job = $workerManager->run($workerName, $methodName);

                if ($job) {
                    $output->writeln("Finished job id: {$job->getId()}");
                }
                else {
                    // No Job to run... should we output?
                }

                sleep($period);
            } catch (\Exception $e) {
                if ($msg = $e->getMessage()) {
                    $output->writeln('<error>[error]</error> '.$msg);
                }
            }
        }
    }
}
