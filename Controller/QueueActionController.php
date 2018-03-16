<?php

namespace Dtc\QueueBundle\Controller;

use Dtc\QueueBundle\Doctrine\DoctrineJobManager;
use Dtc\QueueBundle\Exception\UnsupportedException;
use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\Worker;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QueueActionController extends Controller
{
    /**
     * @Route("/archive", name="dtc_queue_archive")
     * @Method({"POST"})
     *
     * @throws UnsupportedException
     */
    public function archiveAction(Request $request)
    {
        return $this->streamResults($request, 'archiveAllJobs');
    }

    /**
     * @Route("/reset-stalled", name="dtc_queue_reset_stalled")
     *
     * @param Request $request
     *
     * @return StreamedResponse
     *
     * @throws UnsupportedException
     */
    public function resetStalledAction(Request $request)
    {
        return $this->streamResults($request, 'resetStalledJobs');
    }

    /**
     * @Route("/prune-stalled", name="dtc_queue_prune_stalled")
     *
     * @param Request $request
     *
     * @return StreamedResponse
     *
     * @throws UnsupportedException
     */
    public function pruneStalledAction(Request $request)
    {
        return $this->streamResults($request, 'pruneStalledJobs');
    }

    /**
     * @Route("/delete-archived", name="dtc_queue_delete_archived")
     *
     * @param Request $request
     *
     * @return StreamedResponse
     *
     * @throws UnsupportedException
     */
    public function deleteArchivedAction(Request $request)
    {
        return $this->streamResults($request, 'pruneStalledJobs');
    }

    /**
     * @param Request $request
     * @param $functionName
     *
     * @return StreamedResponse
     *
     * @throws UnsupportedException
     */
    protected function streamResults(Request $request, $functionName)
    {
        $jobManager = $this->get('dtc_queue.manager.job');
        if (!$jobManager instanceof DoctrineJobManager) {
            throw new UnsupportedException('$jobManager must be instance of ' . DoctrineJobManager::class);
        }

        $streamingResponse = new StreamedResponse($this->getStreamFunction($request, $functionName));
        $streamingResponse->headers->set('Content-Type', 'application/x-ndjson');
        $streamingResponse->headers->set('X-Accel-Buffering', 'no');

        return $streamingResponse;
    }

    /**
     * @param Request $request
     * @param string $functionName
     *
     * @return \Closure
     */
    protected function getStreamFunction(Request $request, $functionName)
    {
        $jobManager = $this->get('dtc_queue.manager.job');
        $workerName = $request->get('workerName');
        $methodName = $request->get('method');
        $total = null;
        $callback = function ($count, $totalCount) use (&$total) {
            if (null !== $totalCount && null === $total) {
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

        return function () use ($jobManager, $callback, $workerName, $methodName, $functionName, &$total) {
            switch ($functionName) {
                case 'archiveAllJobs':
                    $total = $jobManager->getWaitingJobCount($workerName, $methodName);
                    echo json_encode(['total' => $total]);
                    echo "\n";
                    flush();
                    if ($total > 0) {
                        $jobManager->archiveJobs($workerName, $methodName, DoctrineJobManager::TYPE_WAITING, $callback);
                    }
                    break;
                default:
                    $jobManager->$functionName($workerName, $methodName, $callback);
                    break;
            }
        };
    }
}