<?php
namespace Dtc\QueueBundle\Test;

use Dtc\QueueBundle\Model\Worker;

class FibonacciWorker
    extends Dtc\QueueBundle\Model\Worker
{
    public function processFibonacci($n) {
        $feb = $this->fibonacci($n);
        file_put_conents('/tmp/fib-result.txt', "{$n}: {$feb}");
    }

    public function fibonacci($n)
    {
        if($n == 0)
            return 0; //F0
        elseif ($n == 1)
            return 1; //F1
        else
            return fibonacci($n - 1) + fibonacci($n - 2);
    }

    public function getName() {
        return 'fibonacci';
    }
}
