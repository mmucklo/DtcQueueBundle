<?php
namespace Dtc\QueueBundle\Command;

use Symfony\Component\Console\Command\Command;

use Asc\PlatformBundle\Documents\Profile\UserProfile;
use Asc\PlatformBundle\Documents\UserAuth;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand
    extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
        ->setName('dtc:queue_worker.run')
        ->addArgument('worker_name', InputArgument::OPTIONAL, 'DI name of worker')
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

        while (true) {
            try {
                $output->writeln('Checking for job to run...');
                $job = $workerManager->run();

                if ($job) {
                    $output->writeln("Finsihed job id: {$job->getId()}");
                }
                else {
                    // No Job to run... should we output?
                }

                sleep($period);
            } catch (\Exception $e) {
                if ($error != $msg = $e->getMessage()) {
                    $output->writeln('<error>[error]</error> '.$msg);
                    $error = $msg;
                }
            }
        }
    }
}
