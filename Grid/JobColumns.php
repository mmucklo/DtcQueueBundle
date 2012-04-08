<?php
namespace Dtc\QueueBundle\Grid;

class JobColumns
    extends \ArrayObject
{
    public function __construct(Twig_Environment $twig, GlobalVariables $globals = null)
    {
        $template = $twig->loadTemplate('AscPlatformBundle:Admin\\Cobrand:grid.html.twig');
        $columns = array();

        $env = array(
                'types' => Cobrand::getTypes(),
                'app' => $globals
        );

        $column = new TwigBlockGridColumn('name', 'Name', $template, $env);
        $columns[] = $column;

        parent::__construct($columns);
    }
}
