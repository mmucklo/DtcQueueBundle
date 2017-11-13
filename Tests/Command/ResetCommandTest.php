<?php

namespace Dtc\QueueBundle\Tests\Command;

use Dtc\QueueBundle\Command\ResetCommand;
use PHPUnit\Framework\TestCase;

class ResetCommandTest extends TestCase
{
    use CommandTrait;

    public function testResetCommand()
    {
        $this->runResetCommand([], 'resetErroneousJobs');
        $this->runResetCommand([], 'resetStalledJobs');
    }

    protected function runResetCommand($params, $call, $expectedResult = 0)
    {
        return $this->runStubCommand(ResetCommand::class, $params, $call, $expectedResult);
    }
}
