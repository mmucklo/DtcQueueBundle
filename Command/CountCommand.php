<?php
namespace Dtc\QueueBundle\Command;

use Asc\PlatformBundle\Documents\Profile\UserProfile;
use Asc\PlatformBundle\Documents\UserAuth;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CountCommand
    extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
        ->setName('dtc:queue_worker:count')
        ->addArgument('worker_name', InputArgument::OPTIONAL, 'Name of worker', null)
        ->addArgument('method', InputArgument::OPTIONAL, 'DI method of worker', null)
        ->setDescription('Count total jobs left')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $jobManager = $container->get('dtc_queue.job_manager');
        $workerName = $input->getArgument('worker_name');
        $methodName = $input->getArgument('method');

        $count = $jobManager->getJobCount($workerName, $methodName);
        $output->writeln("Total jobs: {$count}");
    }
}
