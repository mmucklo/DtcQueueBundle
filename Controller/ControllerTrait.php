<?php

namespace Dtc\QueueBundle\Controller;

use Dtc\QueueBundle\Exception\UnsupportedException;

trait ControllerTrait
{
    protected function validateJobTimingManager()
    {
        if ($this->container->hasParameter('dtc_queue.job_timing_manager')) {
            $this->validateManagerType('dtc_queue.job_timing_manager');
        } elseif ($this->container->hasParameter('dtc_queue.job_timing_manager')) {
            $this->validateManagerType('dtc_queue.run_manager');
        } else {
            $this->validateManagerType('dtc_queue.default_manager');
        }
    }

    protected function validateRunManager()
    {
        if ($this->container->hasParameter('dtc_queue.job_timing_manager')) {
            $this->validateManagerType('dtc_queue.run_manager');
        } else {
            $this->validateManagerType('dtc_queue.default_manager');
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
    }
}
