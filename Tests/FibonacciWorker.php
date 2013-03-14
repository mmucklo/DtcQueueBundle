<?php
namespace Dtc\QueueBundle\Tests;

use Dtc\QueueBundle\Model\Worker;

class FibonacciWorker
    extends Worker
{
    private $filename;
    public function __construct() {
        $this->filename = '/tmp/fib-result.txt';
        $this->jobClass = 'Dtc\QueueBundle\Model\Job';
    }

    public function fibonacciFile($n) {
        $feb = $this->fibonacci($n);
        file_put_contents($this->filename, "{$n}: {$feb}");
    }


    public function fibonacci($n)
    {
        if($n == 0)
            return 0; //F0
        elseif ($n == 1)
            return 1; //F1
        else
            return $this->fibonacci($n - 1) + $this->fibonacci($n - 2);
    }

    public function getName() {
        return 'fibonacci';
    }

    /**
     * @return the $filename
     */
    public function getFilename()
    {
        return $this->filename;
    }
}
