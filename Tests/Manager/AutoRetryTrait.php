<?php

namespace Dtc\QueueBundle\Tests\Manager;

use Dtc\QueueBundle\Model\RetryableJob;
use Dtc\QueueBundle\Model\BaseJob;

trait AutoRetryTrait
{
    public function testAutoRetryOnFailure()
    {
        $this->drain();

        /** @var \Dtc\QueueBundle\ODM\JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;
        $jobManager->setAutoRetryOnFailure(false);
        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $job = self::$jobManager->getJob();
        $job->setMaxRetries(1);
        $job->setMaxFailures(1);
        $job->setStatus(BaseJob::STATUS_FAILURE);
        $jobManager->saveHistory($job);
        self::assertEquals(RetryableJob::STATUS_MAX_FAILURES, $job->getStatus());

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $job = self::$jobManager->getJob();
        $job->setMaxRetries(1);
        $job->setMaxFailures(2);
        $job->setStatus(BaseJob::STATUS_FAILURE);
        $jobManager->saveHistory($job);
        self::assertEquals(RetryableJob::STATUS_FAILURE, $job->getStatus());

        $jobManager->setAutoRetryOnFailure(true);
        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $job = self::$jobManager->getJob();
        $job->setMaxRetries(1);
        $job->setMaxFailures(2);
        $job->setStatus(BaseJob::STATUS_FAILURE);
        $jobManager->saveHistory($job);
        self::assertEquals(RetryableJob::STATUS_NEW, $job->getStatus());

        $job = self::$jobManager->getJob();
        $job->setStatus(BaseJob::STATUS_FAILURE);
        $jobManager->saveHistory($job);
        self::assertEquals(RetryableJob::STATUS_MAX_FAILURES, $job->getStatus());

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $job = self::$jobManager->getJob();
        $job->setMaxRetries(1);
        $job->setMaxFailures(3);
        $job->setStatus(BaseJob::STATUS_FAILURE);
        $jobManager->saveHistory($job);
        self::assertEquals(RetryableJob::STATUS_NEW, $job->getStatus());

        $job = self::$jobManager->getJob();
        $job->setStatus(BaseJob::STATUS_FAILURE);
        $jobManager->saveHistory($job);
        self::assertEquals(RetryableJob::STATUS_MAX_RETRIES, $job->getStatus());

        $jobManager->setAutoRetryOnFailure(false);
        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $job = self::$jobManager->getJob();
        $job->setMaxRetries(1);
        $job->setMaxFailures(1);
        $job->setStatus(BaseJob::STATUS_FAILURE);
        $jobManager->saveHistory($job);
        self::assertEquals(RetryableJob::STATUS_MAX_FAILURES, $job->getStatus());
    }

    public function testAutoRetryOnException()
    {
        $this->drain();
        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;
        $jobManager->setAutoRetryOnFailure(false);
        $jobManager->setAutoRetryOnException(false);
        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $job = self::$jobManager->getJob();
        $job->setMaxRetries(1);
        $job->setMaxExceptions(1);
        $job->setStatus(BaseJob::STATUS_EXCEPTION);
        $jobManager->saveHistory($job);
        self::assertEquals(RetryableJob::STATUS_MAX_EXCEPTIONS, $job->getStatus());

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $job = self::$jobManager->getJob();
        $job->setMaxRetries(1);
        $job->setMaxExceptions(2);
        $job->setStatus(BaseJob::STATUS_EXCEPTION);
        $jobManager->saveHistory($job);
        self::assertEquals(RetryableJob::STATUS_EXCEPTION, $job->getStatus());

        $jobManager->setAutoRetryOnException(true);
        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $job = self::$jobManager->getJob();
        $job->setMaxRetries(1);
        $job->setMaxExceptions(2);
        $job->setStatus(BaseJob::STATUS_EXCEPTION);
        $jobManager->saveHistory($job);
        self::assertEquals(RetryableJob::STATUS_NEW, $job->getStatus());

        $job = self::$jobManager->getJob();
        $job->setStatus(BaseJob::STATUS_EXCEPTION);
        $jobManager->saveHistory($job);
        self::assertEquals(RetryableJob::STATUS_MAX_EXCEPTIONS, $job->getStatus());

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $job = self::$jobManager->getJob();
        $job->setMaxRetries(1);
        $job->setMaxExceptions(3);
        $job->setStatus(BaseJob::STATUS_EXCEPTION);
        $jobManager->saveHistory($job);
        self::assertEquals(RetryableJob::STATUS_NEW, $job->getStatus());

        $job = self::$jobManager->getJob();
        $job->setStatus(BaseJob::STATUS_EXCEPTION);
        $jobManager->saveHistory($job);
        self::assertEquals(RetryableJob::STATUS_MAX_RETRIES, $job->getStatus());

        $jobManager->setAutoRetryOnFailure(false);
        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $job = self::$jobManager->getJob();
        $job->setMaxRetries(1);
        $job->setMaxExceptions(1);
        $job->setStatus(BaseJob::STATUS_EXCEPTION);
        $jobManager->saveHistory($job);
        self::assertEquals(RetryableJob::STATUS_MAX_EXCEPTIONS, $job->getStatus());
    }
}
