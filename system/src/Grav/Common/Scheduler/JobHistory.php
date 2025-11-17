<?php

/**
 * @package    Grav\Common\Scheduler
 * 
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Scheduler;

use DateTime;
use RocketTheme\Toolbox\File\JsonFile;

/**
 * Job History Manager
 * 
 * Provides comprehensive job execution history, logging, and analytics
 * 
 * @package Grav\Common\Scheduler
 */
class JobHistory
{
    /** @var string */
    protected $historyPath;
    
    /** @var int */
    protected $retentionDays = 30;
    
    /** @var int */
    protected $maxOutputLength = 5000;
    
    /**
     * Constructor
     * 
     * @param string $historyPath
     * @param int $retentionDays
     */
    public function __construct(string $historyPath, int $retentionDays = 30)
    {
        $this->historyPath = $historyPath;
        $this->retentionDays = $retentionDays;
        
        // Ensure history directory exists
        if (!is_dir($this->historyPath)) {
            mkdir($this->historyPath, 0755, true);
        }
    }
    
    /**
     * Log job execution
     * 
     * @param Job $job
     * @param array $metadata Additional metadata to store
     * @return string Log entry ID
     */
    public function logExecution(Job $job, array $metadata = []): string
    {
        $entryId = uniqid($job->getId() . '_', true);
        $timestamp = new DateTime();
        
        $entry = [
            'id' => $entryId,
            'job_id' => $job->getId(),
            'command' => is_string($job->getCommand()) ? $job->getCommand() : 'Closure',
            'arguments' => method_exists($job, 'getRawArguments') ? $job->getRawArguments() : $job->getArguments(),
            'executed_at' => $timestamp->format('c'),
            'timestamp' => $timestamp->getTimestamp(),
            'success' => $job->isSuccessful(),
            'output' => $this->captureOutput($job),
            'execution_time' => method_exists($job, 'getExecutionTime') ? $job->getExecutionTime() : null,
            'retry_count' => method_exists($job, 'getRetryCount') ? $job->getRetryCount() : 0,
            'priority' => method_exists($job, 'getPriority') ? $job->getPriority() : 'normal',
            'tags' => method_exists($job, 'getTags') ? $job->getTags() : [],
            'metadata' => array_merge(
                method_exists($job, 'getMetadata') ? $job->getMetadata() : [],
                $metadata
            ),
        ];
        
        // Store in daily file
        $this->storeEntry($entry);
        
        // Also store in job-specific history
        $this->storeJobHistory($job->getId(), $entry);
        
        return $entryId;
    }
    
    /**
     * Capture job output with length limit
     * 
     * @param Job $job
     * @return array
     */
    protected function captureOutput(Job $job): array
    {
        $output = $job->getOutput();
        $truncated = false;
        
        if (strlen($output) > $this->maxOutputLength) {
            $output = substr($output, 0, $this->maxOutputLength);
            $truncated = true;
        }
        
        return [
            'content' => $output,
            'truncated' => $truncated,
            'length' => strlen($job->getOutput()),
        ];
    }
    
    /**
     * Store entry in daily log file
     * 
     * @param array $entry
     * @return void
     */
    protected function storeEntry(array $entry): void
    {
        $date = date('Y-m-d');
        $filename = $this->historyPath . '/' . $date . '.json';
        
        $jsonFile = JsonFile::instance($filename);
        $entries = $jsonFile->content() ?: [];
        $entries[] = $entry;
        $jsonFile->save($entries);
    }
    
    /**
     * Store job-specific history
     * 
     * @param string $jobId
     * @param array $entry
     * @return void
     */
    protected function storeJobHistory(string $jobId, array $entry): void
    {
        $jobDir = $this->historyPath . '/jobs';
        if (!is_dir($jobDir)) {
            mkdir($jobDir, 0755, true);
        }
        
        $filename = $jobDir . '/' . $jobId . '.json';
        $jsonFile = JsonFile::instance($filename);
        $history = $jsonFile->content() ?: [];
        
        // Keep only last 100 executions per job
        $history[] = $entry;
        if (count($history) > 100) {
            $history = array_slice($history, -100);
        }
        
        $jsonFile->save($history);
    }
    
    /**
     * Get job history
     * 
     * @param string $jobId
     * @param int $limit
     * @return array
     */
    public function getJobHistory(string $jobId, int $limit = 50): array
    {
        $filename = $this->historyPath . '/jobs/' . $jobId . '.json';
        if (!file_exists($filename)) {
            return [];
        }
        
        $jsonFile = JsonFile::instance($filename);
        $history = $jsonFile->content() ?: [];
        
        // Return most recent first
        $history = array_reverse($history);
        
        if ($limit > 0) {
            $history = array_slice($history, 0, $limit);
        }
        
        return $history;
    }
    
    /**
     * Get history for a date range
     * 
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param string|null $jobId Filter by job ID
     * @return array
     */
    public function getHistoryRange(DateTime $startDate, DateTime $endDate, ?string $jobId = null): array
    {
        $history = [];
        $current = clone $startDate;
        
        while ($current <= $endDate) {
            $filename = $this->historyPath . '/' . $current->format('Y-m-d') . '.json';
            if (file_exists($filename)) {
                $jsonFile = JsonFile::instance($filename);
                $entries = $jsonFile->content() ?: [];
                
                foreach ($entries as $entry) {
                    if ($jobId === null || $entry['job_id'] === $jobId) {
                        $history[] = $entry;
                    }
                }
            }
            
            $current->modify('+1 day');
        }
        
        return $history;
    }
    
    /**
     * Get job statistics
     * 
     * @param string $jobId
     * @param int $days Number of days to analyze
     * @return array
     */
    public function getJobStatistics(string $jobId, int $days = 7): array
    {
        $startDate = new DateTime("-{$days} days");
        $endDate = new DateTime('now');
        
        $history = $this->getHistoryRange($startDate, $endDate, $jobId);
        
        if (empty($history)) {
            return [
                'total_runs' => 0,
                'successful_runs' => 0,
                'failed_runs' => 0,
                'success_rate' => 0,
                'average_execution_time' => 0,
                'last_run' => null,
                'last_success' => null,
                'last_failure' => null,
            ];
        }
        
        $totalRuns = count($history);
        $successfulRuns = 0;
        $executionTimes = [];
        $lastRun = null;
        $lastSuccess = null;
        $lastFailure = null;
        
        foreach ($history as $entry) {
            if ($entry['success']) {
                $successfulRuns++;
                if (!$lastSuccess || $entry['timestamp'] > $lastSuccess['timestamp']) {
                    $lastSuccess = $entry;
                }
            } else {
                if (!$lastFailure || $entry['timestamp'] > $lastFailure['timestamp']) {
                    $lastFailure = $entry;
                }
            }
            
            if (!$lastRun || $entry['timestamp'] > $lastRun['timestamp']) {
                $lastRun = $entry;
            }
            
            if (isset($entry['execution_time']) && $entry['execution_time'] > 0) {
                $executionTimes[] = $entry['execution_time'];
            }
        }
        
        return [
            'total_runs' => $totalRuns,
            'successful_runs' => $successfulRuns,
            'failed_runs' => $totalRuns - $successfulRuns,
            'success_rate' => $totalRuns > 0 ? round(($successfulRuns / $totalRuns) * 100, 2) : 0,
            'average_execution_time' => !empty($executionTimes) ? round(array_sum($executionTimes) / count($executionTimes), 3) : 0,
            'last_run' => $lastRun,
            'last_success' => $lastSuccess,
            'last_failure' => $lastFailure,
        ];
    }
    
    /**
     * Get global statistics
     * 
     * @param int $days
     * @return array
     */
    public function getGlobalStatistics(int $days = 7): array
    {
        $startDate = new DateTime("-{$days} days");
        $endDate = new DateTime('now');
        
        $history = $this->getHistoryRange($startDate, $endDate);
        
        $jobStats = [];
        foreach ($history as $entry) {
            $jobId = $entry['job_id'];
            if (!isset($jobStats[$jobId])) {
                $jobStats[$jobId] = [
                    'runs' => 0,
                    'success' => 0,
                    'failed' => 0,
                ];
            }
            
            $jobStats[$jobId]['runs']++;
            if ($entry['success']) {
                $jobStats[$jobId]['success']++;
            } else {
                $jobStats[$jobId]['failed']++;
            }
        }
        
        return [
            'total_executions' => count($history),
            'unique_jobs' => count($jobStats),
            'job_statistics' => $jobStats,
            'period_days' => $days,
            'from_date' => $startDate->format('Y-m-d'),
            'to_date' => $endDate->format('Y-m-d'),
        ];
    }
    
    /**
     * Search history
     * 
     * @param array $criteria
     * @return array
     */
    public function searchHistory(array $criteria): array
    {
        $results = [];
        
        // Determine date range
        $startDate = isset($criteria['start_date']) ? new DateTime($criteria['start_date']) : new DateTime('-7 days');
        $endDate = isset($criteria['end_date']) ? new DateTime($criteria['end_date']) : new DateTime('now');
        
        $history = $this->getHistoryRange($startDate, $endDate, $criteria['job_id'] ?? null);
        
        foreach ($history as $entry) {
            $match = true;
            
            // Filter by success status
            if (isset($criteria['success']) && $entry['success'] !== $criteria['success']) {
                $match = false;
            }
            
            // Filter by output content
            if (isset($criteria['output_contains']) && 
                stripos($entry['output']['content'], $criteria['output_contains']) === false) {
                $match = false;
            }
            
            // Filter by tags
            if (isset($criteria['tags']) && is_array($criteria['tags'])) {
                $entryTags = $entry['tags'] ?? [];
                if (empty(array_intersect($criteria['tags'], $entryTags))) {
                    $match = false;
                }
            }
            
            if ($match) {
                $results[] = $entry;
            }
        }
        
        // Sort results
        if (isset($criteria['sort_by'])) {
            usort($results, function($a, $b) use ($criteria) {
                $field = $criteria['sort_by'];
                $order = $criteria['sort_order'] ?? 'desc';
                
                $aVal = $a[$field] ?? 0;
                $bVal = $b[$field] ?? 0;
                
                if ($order === 'asc') {
                    return $aVal <=> $bVal;
                } else {
                    return $bVal <=> $aVal;
                }
            });
        }
        
        // Limit results
        if (isset($criteria['limit'])) {
            $results = array_slice($results, 0, $criteria['limit']);
        }
        
        return $results;
    }
    
    /**
     * Clean old history files
     * 
     * @return int Number of files deleted
     */
    public function cleanOldHistory(): int
    {
        $deleted = 0;
        $cutoffDate = new DateTime("-{$this->retentionDays} days");
        
        $files = glob($this->historyPath . '/*.json');
        foreach ($files as $file) {
            $filename = basename($file, '.json');
            // Check if filename is a date
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filename)) {
                $fileDate = new DateTime($filename);
                if ($fileDate < $cutoffDate) {
                    unlink($file);
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
    
    /**
     * Export history to CSV
     * 
     * @param array $history
     * @param string $filename
     * @return bool
     */
    public function exportToCsv(array $history, string $filename): bool
    {
        $handle = fopen($filename, 'w');
        if (!$handle) {
            return false;
        }
        
        // Write headers
        fputcsv($handle, [
            'Job ID',
            'Executed At',
            'Success',
            'Execution Time',
            'Output Length',
            'Retry Count',
            'Priority',
            'Tags',
        ]);
        
        // Write data
        foreach ($history as $entry) {
            fputcsv($handle, [
                $entry['job_id'],
                $entry['executed_at'],
                $entry['success'] ? 'Yes' : 'No',
                $entry['execution_time'] ?? '',
                $entry['output']['length'] ?? 0,
                $entry['retry_count'] ?? 0,
                $entry['priority'] ?? 'normal',
                implode(', ', $entry['tags'] ?? []),
            ]);
        }
        
        fclose($handle);
        return true;
    }
}