<?php

namespace Dtc\QueueBundle\Tests\Manager;

use Dtc\QueueBundle\Manager\RetryableJobManager;

trait RetryableTrait
{
    public function testRetryableJobManager()
    {
        /** @var RetryableJobManager $jobManager */
        $jobManager = self::$jobManager;
        $oldMaxExceptions = $jobManager->getDefaultMaxExceptions();
        $jobManager->setDefaultMaxExceptions(1234);
        self::assertEquals(1234, $jobManager->getDefaultMaxExceptions());
        $jobManager->setDefaultMaxExceptions($oldMaxExceptions);
        self::assertEquals($oldMaxExceptions, $jobManager->getDefaultMaxExceptions());

        $oldMaxFailures = $jobManager->getDefaultMaxFailures();
        $jobManager->setDefaultMaxFailures(3214);
        self::assertEquals(3214, $jobManager->getDefaultMaxFailures());
        $jobManager->setDefaultMaxFailures($oldMaxFailures);
        self::assertEquals($oldMaxFailures, $jobManager->getDefaultMaxFailures());

        $oldMaxRetries = $jobManager->getDefaultMaxRetries();
        $jobManager->setDefaultMaxRetries(3214);
        self::assertEquals(3214, $jobManager->getDefaultMaxRetries());
        $jobManager->setDefaultMaxRetries($oldMaxRetries);
        self::assertEquals($oldMaxRetries, $jobManager->getDefaultMaxRetries());

        $oldRetry = $jobManager->getAutoRetryOnException();
        $jobManager->setAutoRetryOnException(true);
        self::assertTrue($jobManager->getAutoRetryOnException());
        $jobManager->setAutoRetryOnException(false);
        self::assertFalse($jobManager->getAutoRetryOnException());
        $jobManager->setAutoRetryOnException($oldRetry);

        $oldRetry = $jobManager->getAutoRetryOnFailure();
        $jobManager->setAutoRetryOnFailure(true);
        self::assertTrue($jobManager->getAutoRetryOnFailure());
        $jobManager->setAutoRetryOnFailure(false);
        self::assertFalse($jobManager->getAutoRetryOnFailure());
        $jobManager->setAutoRetryOnFailure($oldRetry);
    }
}
