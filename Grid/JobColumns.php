<?php

namespace Dtc\QueueBundle\Grid;

use Dtc\GridBundle\Grid\Column\TwigBlockGridColumn;
use Twig_Environment;

class JobColumns extends \ArrayObject
{
    public function __construct(Twig_Environment $twig)
    {
        $template = $twig->loadTemplate('DtcQueueBundle:Job:_grid.html.twig');
        $columns = array();

        $env = $twig->getGlobals();

        $column = new TwigBlockGridColumn('id', 'Id', $template, $env);
        $columns[] = $column;

        $column = new TwigBlockGridColumn('status', 'Status', $template, $env);
        $column->setOption('sortable', true);
        $columns[] = $column;

        $column = new TwigBlockGridColumn('worker', 'Worker', $template, $env);
        $column->setOption('sortable', true);
        $columns[] = $column;

        $column = new TwigBlockGridColumn('args', 'Args', $template, $env);
        $columns[] = $column;

        $column = new TwigBlockGridColumn('createdAt', 'Created At', $template, $env);
        $column->setOption('sortable', true);
        $columns[] = $column;

        $column = new TwigBlockGridColumn('runAt', 'Run At', $template, $env);
        $column->setOption('sortable', true);
        $columns[] = $column;

        $column = new TwigBlockGridColumn('runTime', 'Run Time', $template, $env);
        $columns[] = $column;

        $column = new TwigBlockGridColumn('message', 'Message', $template, $env);
        $columns[] = $column;

        parent::__construct($columns);
    }
}
