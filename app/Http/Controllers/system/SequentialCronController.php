<?php

namespace App\Http\Controllers\system;

use App\Http\Controllers\Controller;
use App\Models\CronJobStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class SequentialCronController extends Controller
{
    /**
     * Execute all cron jobs sequentially
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function executeAllJobs(Request $request)
    {
        try {
            // Check if any job is currently running
            if (CronJobStatus::hasRunningJobs()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'A cron job is already running. Please wait for it to complete.',
                    'running_job' => CronJobStatus::where('status', 'running')->first()
                ], 423); // HTTP 423 Locked
            }

            // Reset all jobs to idle if requested
            if ($request->get('reset') === 'true') {
                CronJobStatus::resetAllJobs();
                Log::info('All cron jobs reset to idle status');
            }

            // Get all active jobs in execution order
            $jobs = CronJobStatus::getActiveJobsOrdered();
            
            if ($jobs->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No active cron jobs found'
                ], 404);
            }

            $results = [];
            $totalJobs = $jobs->count();
            $completedJobs = 0;
            $failedJobs = 0;

            foreach ($jobs as $job) {
                Log::info("Starting cron job: {$job->job_name}");
                
                // Mark job as started
                $job->markAsStarted();
                
                try {
                    // Execute the job using Artisan command (more reliable than HTTP calls)
                    $result = $this->executeJob($job);
                    
                    if ($result['success']) {
                        $job->markAsCompleted($result['output']);
                        $completedJobs++;
                        Log::info("Completed cron job: {$job->job_name}");
                    } else {
                        $job->markAsFailed($result['error']);
                        $failedJobs++;
                        Log::error("Failed cron job: {$job->job_name} - {$result['error']}");
                        
                        // Decide whether to continue or stop based on configuration
                        if (!$this->shouldContinueOnFailure($job)) {
                            break;
                        }
                    }
                    
                    $results[] = [
                        'job_name' => $job->job_name,
                        'status' => $job->status,
                        'execution_order' => $job->execution_order,
                        'started_at' => $job->started_at,
                        'completed_at' => $job->completed_at,
                        'error_message' => $job->error_message
                    ];
                    
                } catch (Exception $e) {
                    $job->markAsFailed($e->getMessage());
                    $failedJobs++;
                    Log::error("Exception in cron job: {$job->job_name} - {$e->getMessage()}");
                    
                    $results[] = [
                        'job_name' => $job->job_name,
                        'status' => 'failed',
                        'execution_order' => $job->execution_order,
                        'started_at' => $job->started_at,
                        'completed_at' => now(),
                        'error_message' => $e->getMessage()
                    ];
                    
                    if (!$this->shouldContinueOnFailure($job)) {
                        break;
                    }
                }
            }

            return response()->json([
                'status' => 'completed',
                'message' => "Sequential cron execution finished. Completed: {$completedJobs}/{$totalJobs}, Failed: {$failedJobs}",
                'summary' => [
                    'total_jobs' => $totalJobs,
                    'completed_jobs' => $completedJobs,
                    'failed_jobs' => $failedJobs,
                    'success_rate' => $totalJobs > 0 ? round(($completedJobs / $totalJobs) * 100, 2) : 0
                ],
                'results' => $results,
                'execution_time' => now()
            ]);

        } catch (Exception $e) {
            Log::error('Sequential cron execution failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Sequential cron execution failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Execute a single cron job
     * 
     * @param CronJobStatus $job
     * @return array
     */
    private function executeJob(CronJobStatus $job)
    {
        try {
            if ($job->artisan_command) {
                // Use Artisan command (preferred method)
                $exitCode = Artisan::call($job->artisan_command);
                $output = Artisan::output();
                
                return [
                    'success' => $exitCode === 0,
                    'output' => $output,
                    'error' => $exitCode !== 0 ? "Command failed with exit code: {$exitCode}" : null
                ];
            } else {
                // Fallback to HTTP call
                $response = Http::timeout(300) // 5 minutes timeout
                    ->get($job->endpoint_url);
                
                if ($response->successful()) {
                    return [
                        'success' => true,
                        'output' => $response->body(),
                        'error' => null
                    ];
                } else {
                    return [
                        'success' => false,
                        'output' => null,
                        'error' => "HTTP {$response->status()}: {$response->body()}"
                    ];
                }
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'output' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Determine if execution should continue on job failure
     * 
     * @param CronJobStatus $job
     * @return bool
     */
    private function shouldContinueOnFailure(CronJobStatus $job)
    {
        // For now, continue on all failures
        // You can customize this logic based on job importance
        return true;
    }

    /**
     * Get status of all cron jobs
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getJobsStatus()
    {
        $jobs = CronJobStatus::orderBy('execution_order')->get();
        $runningJob = CronJobStatus::where('status', 'running')->first();
        
        return response()->json([
            'status' => 'success',
            'has_running_jobs' => CronJobStatus::hasRunningJobs(),
            'running_job' => $runningJob ? $runningJob->job_name : null,
            'jobs' => $jobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'job_name' => $job->job_name,
                    'status' => $job->status,
                    'execution_order' => $job->execution_order,
                    'started_at' => $job->started_at,
                    'completed_at' => $job->completed_at,
                    'error_message' => $job->error_message,
                    'retry_count' => $job->retry_count,
                    'is_active' => $job->is_active
                ];
            })
        ]);
    }

    /**
     * Reset all cron jobs to idle status
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetAllJobs()
    {
        try {
            CronJobStatus::resetAllJobs();
            
            return response()->json([
                'status' => 'success',
                'message' => 'All cron jobs have been reset to idle status'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reset cron jobs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Execute a single job by name (for testing purposes)
     * 
     * @param Request $request
     * @param string $jobName
     * @return \Illuminate\Http\JsonResponse
     */
    public function executeSingleJob(Request $request, $jobName)
    {
        try {
            $job = CronJobStatus::where('job_name', $jobName)
                ->where('is_active', true)
                ->first();
            
            if (!$job) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Job '{$jobName}' not found or not active"
                ], 404);
            }

            if ($job->status === 'running') {
                return response()->json([
                    'status' => 'error',
                    'message' => "Job '{$jobName}' is already running"
                ], 423);
            }

            $job->markAsStarted();
            $result = $this->executeJob($job);
            
            if ($result['success']) {
                $job->markAsCompleted($result['output']);
            } else {
                $job->markAsFailed($result['error']);
            }

            return response()->json([
                'status' => $result['success'] ? 'completed' : 'failed',
                'job_name' => $job->job_name,
                'started_at' => $job->started_at,
                'completed_at' => $job->completed_at,
                'output' => $result['output'],
                'error_message' => $job->error_message
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to execute job: ' . $e->getMessage()
            ], 500);
        }
    }
}