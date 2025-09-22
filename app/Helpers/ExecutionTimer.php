<?php

namespace App\Helpers;

class ExecutionTimer
{
    /**
     * The start time of the timer.
     *
     * @var float
     */
    private $startTime;

    /**
     * The end time of the timer.
     *
     * @var float
     */
    private $endTime;

    /**
     * Start the timer.
     *
     * @return void
     */
    public function start()
    {
        $this->startTime = microtime(true);
    }

    /**
     * Stop the timer.
     *
     * @return void
     */
    public function stop()
    {
        $this->endTime = microtime(true);
    }

    /**
     * Get the execution time in seconds.
     *
     * @return float
     */
    public function getExecutionTime()
    {
        return $this->endTime - $this->startTime;
    }
}