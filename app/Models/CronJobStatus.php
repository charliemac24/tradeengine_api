<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CronJobStatus extends Model
{
    use HasFactory;

    protected $table = 'cron_job_status';

    protected $fillable = [
        'job_name',
        'endpoint_url',
        'artisan_command',
        'status',
        'execution_order',
        'started_at',
        'completed_at',
        'last_response',
        'error_message',
        'retry_count',
        'max_retries',
        'is_active'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get all active jobs ordered by execution order
     */
    public static function getActiveJobsOrdered()
    {
        return static::where('is_active', true)
            ->orderBy('execution_order')
            ->get();
    }

    /**
     * Check if any job is currently running
     */
    public static function hasRunningJobs()
    {
        return static::where('status', 'running')->exists();
    }

    /**
     * Get the next job to run
     */
    public static function getNextJob()
    {
        return static::where('is_active', true)
            ->where('status', '!=', 'running')
            ->orderBy('execution_order')
            ->first();
    }

    /**
     * Reset all jobs to idle status
     */
    public static function resetAllJobs()
    {
        static::whereIn('status', ['running', 'completed', 'failed'])
            ->update([
                'status' => 'idle',
                'started_at' => null,
                'completed_at' => null,
                'error_message' => null
            ]);
    }

    /**
     * Mark job as started
     */
    public function markAsStarted()
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
            'completed_at' => null,
            'error_message' => null
        ]);
    }

    /**
     * Mark job as completed
     */
    public function markAsCompleted($response = null)
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'last_response' => $response,
            'retry_count' => 0
        ]);
    }

    /**
     * Mark job as failed
     */
    public function markAsFailed($error_message = null)
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $error_message,
            'retry_count' => $this->retry_count + 1
        ]);
    }

    /**
     * Check if job can be retried
     */
    public function canRetry()
    {
        return $this->retry_count < $this->max_retries;
    }
}