<?php

namespace Dtc\QueueBundle\Controller;

use Dtc\QueueBundle\Doctrine\DoctrineJobManager;
use Dtc\QueueBundle\Exception\UnsupportedException;
use Dtc\QueueBundle\Manager\JobManagerInterface;
use Dtc\QueueBundle\Manager\RunManager;
use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\Worker;
use Dtc\QueueBundle\Util\IntervalTrait;
use Dtc\QueueBundle\Util\Util;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QueueActionController extends Controller
{
    use IntervalTrait;
    /**
     * @Route("/archive", name="dtc_queue_archive_non_running")
     * @Route("/archive-waiting", name="dtc_queue_archive_waiting")
     * @Route("/delete-archived", name="dtc_queue_delete_archived")
     * @Method({"POST"})
     *
     * @throws UnsupportedException
     * @throws \Exception
     */
    public function archiveAction(Request $request)
    {
        /** @var JobManagerInterface $jobManager */
        $jobManager = $this->get('dtc_queue.manager.job');
        $route = $request->get('_route');
        if ($route === 'dtc_queue_archive_waiting') {
            return $this->streamResults($jobManager, $request, 'archiveWaitingJobs');
        }

        if ($route === 'dtc_queue_archive_non_running') {
            return $this->streamResults($jobManager, $request, 'archiveNonRunning');
        }

        return $this->streamResults($jobManager, $request, 'pruneArchivedJobs');
    }

    /**
     * @Route("/reset-stalled", name="dtc_queue_reset_stalled")
     * @Route("/prune-stalled", name="dtc_queue_prune_stalled")
     *
     * @param Request $request
     *
     * @return StreamedResponse
     *
     * @throws UnsupportedException
     */
    public function resetStalledAction(Request $request)
    {
        /** @var JobManagerInterface $jobManager */
        $jobManager = $this->get('dtc_queue.manager.job');
        $route = $request->get('_route');
        if ($route === 'dtc_queue_reset_stalled') {
            return $this->streamResults($jobManager, $request, 'resetStalledJobs');
        }
        return $this->streamResults($jobManager, $request, 'pruneStalledJobs');
    }


    /**
     * @Route("/prune-expired", name="dtc_queue_prune_expired")
     *
     * @param Request $request
     *
     * @return StreamedResponse
     *
     * @throws UnsupportedException
     */
    public function pruneExpiredAction(Request $request)
    {
        /** @var JobManagerInterface $jobManager */
        $jobManager = $this->get('dtc_queue.manager.job');
        return $this->streamResults($jobManager, $request, 'pruneExpiredJobs');
    }

    /**
     * @Route("/prune-stalled-runs", name="dtc_queue_pruned_stalled_runs")
     * @param Request $request
     * @return StreamedResponse
     * @throws UnsupportedException
     */
    public function pruneStalledRuns(Request $request)
    {
        /** @var RunManager $runManager */
        $runManager = $this->get('dtc_queue.manager.run');
        return $this->streamResults($runManager, $request, 'pruneStalledRuns');
    }

    /**
     * @param JobManagerInterface|RunManager $manager
     * @param Request $request
     * @param $functionName
     *
     * @return StreamedResponse
     *
     * @throws UnsupportedException
     */
    protected function streamResults($manager, Request $request, $functionName)
    {
        $streamingResponse = new StreamedResponse($this->getStreamFunction($manager, $request, $functionName));
        $streamingResponse->headers->set('Content-Type', 'application/x-ndjson');
        $streamingResponse->headers->set('X-Accel-Buffering', 'no');

        return $streamingResponse;
    }

    /**
     * @param Request $request
     * @return string|null
     * @throws UnsupportedException
     * @throws \Exception
     */
    private function getOlderThanDate(Request $request) {
        $amount = $request->get('olderThanAmount');
        $type = $request->get('olderThanType');

        $olderThanDate = null;
        if ($amount && $type) {
            $interval = $this->getInterval($type, intval($amount));
            $olderThan = Util::getMicrotimeDateTime();
            return $olderThan->sub($interval);
        }
        return null;
    }

    /**
     * @param JobManagerInterface|RunManager $manager
     * @param Request $request
     * @param string $functionName
     *
     * @throws \Exception
     * @throws UnsupportedException
     *
     * @return \Closure
     */
    protected function getStreamFunction($manager, Request $request, $functionName)
    {
        $workerName = $request->get('workerName');
        $methodName = $request->get('method');
        $olderThanDate = $this->getOlderThanDate($request);
        $total = null;
        $callback = function ($count, $totalCount) use (&$total) {
            if (isset($totalCount) && null === $total) {
                $total = $totalCount;
                echo json_encode(['total' => $total]);
                echo "\n";
                flush();

                return;
            }
            echo json_encode(['count' => $count]);
            echo "\n";
            flush();
        };

        return function () use ($manager, $callback, $workerName, $methodName, $functionName, $olderThanDate) {
            if ($olderThanDate) {
                $manager->$functionName($olderThanDate, $callback);
                return;
            }
            $manager->$functionName($workerName, $methodName, $callback);
        };
    }
}