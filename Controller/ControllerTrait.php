<?php

namespace Dtc\QueueBundle\Controller;

use Dtc\QueueBundle\Exception\UnsupportedException;
use Dtc\QueueBundle\Model\Worker;

trait ControllerTrait
{
    protected function validateJobTimingManager()
    {
        if ($this->container->hasParameter('dtc_queue.manager.job_timing')) {
            $this->validateManagerType('dtc_queue.manager.job_timing');
        } elseif ($this->container->hasParameter('dtc_queue.manager.job_timing')) {
            $this->validateManagerType('dtc_queue.manager.run');
        } else {
            $this->validateManagerType('dtc_queue.manager.job');
        }
    }

    protected function validateRunManager()
    {
        if ($this->container->hasParameter('dtc_queue.manager.job_timing')) {
            $this->validateManagerType('dtc_queue.manager.run');
        } else {
            $this->validateManagerType('dtc_queue.manager.job');
        }
    }

    /**
     * @param string $type
     */
    protected function validateManagerType($type)
    {
        $managerType = $this->container->getParameter($type);
        if ('mongodb' !== $managerType && 'orm' != $managerType && 'odm' != $managerType) {
            throw new UnsupportedException("Unsupported manager type: $managerType");
        }
    }

    protected function addCssJs(array &$params)
    {
        $params['css'] = $this->container->getParameter('dtc_grid.theme.css');
        $params['js'] = $this->container->getParameter('dtc_grid.theme.js');
        $jQuery = $this->container->getParameter('dtc_grid.jquery');
        array_unshift($params['js'], $jQuery['url']);
        $params['chartjs'] = $this->container->getParameter('dtc_queue.admin.chartjs');
        $params['queue_js'] = '/bundles/dtcqueue/js/jobs.js?v='.filemtime(__DIR__.'/../Resources/public/js/queue.js');
        $params['trends_js'] = '/bundles/dtcqueue/js/trends.js?v='.filemtime(__DIR__.'/../Resources/public/js/trends.js');
    }

    protected function getWorkersAndMethods()
    {
        $workerManager = $this->container->get('dtc_queue.manager.worker');
        $workers = $workerManager->getWorkers();

        $workerList = [];
        $workersMethods = [];
        foreach ($workers as $workerName => $worker) {
            /* @var Worker $worker */
            $workerList[$workerName] = get_class($worker);
            $reflectionObject = new \ReflectionObject($worker);
            $methods = $reflectionObject->getMethods(\ReflectionMethod::IS_PUBLIC);
            $objectClass = $reflectionObject->getName();
            foreach ($methods as $method) {
                $declaringClass = $method->getDeclaringClass()->getName();
                $methodName = $method->getName();
                if ($declaringClass == $objectClass && $methodName != 'getName' && !$method->isConstructor()) {
                    $workersMethods[$workerName][] = $method->getName();
                }
            }
        }
        return ['workers' => $workerList, 'workers_methods' => $workersMethods];
    }


}
