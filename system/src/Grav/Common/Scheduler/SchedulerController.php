<?php

/**
 * @package    Grav\Common\Scheduler
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Scheduler;

use Grav\Common\Grav;
use Grav\Common\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Scheduler Controller for handling HTTP endpoints
 * 
 * @package Grav\Common\Scheduler
 */
class SchedulerController
{
    /** @var Grav */
    protected $grav;
    
    /** @var ModernScheduler */
    protected $scheduler;
    
    /**
     * SchedulerController constructor
     * 
     * @param Grav $grav
     */
    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
        
        // Get scheduler instance
        $scheduler = $grav['scheduler'];
        if ($scheduler instanceof ModernScheduler) {
            $this->scheduler = $scheduler;
        } else {
            // Create ModernScheduler instance if not already
            $this->scheduler = new ModernScheduler();
        }
    }
    
    /**
     * Handle health check endpoint
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function health(ServerRequestInterface $request): ResponseInterface
    {
        $config = $this->grav['config']->get('scheduler.modern', []);
        
        // Check if health endpoint is enabled
        if (!($config['health']['enabled'] ?? true)) {
            return $this->jsonResponse(['error' => 'Health check disabled'], 403);
        }
        
        // Get health status
        $health = $this->scheduler->getHealthStatus();
        
        return $this->jsonResponse($health);
    }
    
    /**
     * Handle webhook trigger endpoint
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function webhook(ServerRequestInterface $request): ResponseInterface
    {
        $config = $this->grav['config']->get('scheduler.modern', []);
        
        // Check if webhook is enabled
        if (!($config['webhook']['enabled'] ?? false)) {
            return $this->jsonResponse(['error' => 'Webhook triggers disabled'], 403);
        }
        
        // Get authorization header
        $authHeader = $request->getHeaderLine('Authorization');
        $token = null;
        
        if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }
        
        // Get query parameters
        $params = $request->getQueryParams();
        $jobId = $params['job'] ?? null;
        
        // Process webhook
        $result = $this->scheduler->processWebhookTrigger($token, $jobId);
        
        $statusCode = $result['success'] ? 200 : 400;
        return $this->jsonResponse($result, $statusCode);
    }
    
    /**
     * Handle statistics endpoint
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function statistics(ServerRequestInterface $request): ResponseInterface
    {
        // Check if user is admin
        $user = $this->grav['user'] ?? null;
        if (!$user || !$user->authorize('admin.super')) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }
        
        $stats = $this->scheduler->getStatistics();
        
        return $this->jsonResponse($stats);
    }
    
    /**
     * Handle admin AJAX requests for scheduler status
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function adminStatus(ServerRequestInterface $request): ResponseInterface
    {
        // Check if user is admin
        $user = $this->grav['user'] ?? null;
        if (!$user || !$user->authorize('admin.scheduler')) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }
        
        $health = $this->scheduler->getHealthStatus();
        
        // Format for admin display
        $response = [
            'health' => $this->formatHealthStatus($health),
            'triggers' => $this->formatTriggers($health['trigger_methods'] ?? [])
        ];
        
        return $this->jsonResponse($response);
    }
    
    /**
     * Format health status for display
     * 
     * @param array $health
     * @return string
     */
    protected function formatHealthStatus(array $health): string
    {
        $status = $health['status'] ?? 'unknown';
        $lastRun = $health['last_run'] ?? null;
        $queueSize = $health['queue_size'] ?? 0;
        $failedJobs = $health['failed_jobs_24h'] ?? 0;
        $jobsDue = $health['jobs_due'] ?? 0;
        $message = $health['message'] ?? '';
        
        $statusBadge = match($status) {
            'healthy' => '<span class="badge badge-success">Healthy</span>',
            'warning' => '<span class="badge badge-warning">Warning</span>',
            'critical' => '<span class="badge badge-danger">Critical</span>',
            default => '<span class="badge badge-secondary">Unknown</span>'
        };
        
        $html = '<div class="scheduler-health">';
        $html .= '<p>Status: ' . $statusBadge;
        if ($message) {
            $html .= ' - ' . htmlspecialchars($message);
        }
        $html .= '</p>';
        
        if ($lastRun) {
            $lastRunTime = new \DateTime($lastRun);
            $now = new \DateTime();
            $diff = $now->diff($lastRunTime);
            
            $timeAgo = '';
            if ($diff->d > 0) {
                $timeAgo = $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
            } elseif ($diff->h > 0) {
                $timeAgo = $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
            } elseif ($diff->i > 0) {
                $timeAgo = $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
            } else {
                $timeAgo = 'Less than a minute ago';
            }
            
            $html .= '<p>Last Run: <strong>' . $timeAgo . '</strong></p>';
        } else {
            $html .= '<p>Last Run: <strong>Never</strong></p>';
        }
        
        $html .= '<p>Jobs Due: <strong>' . $jobsDue . '</strong></p>';
        $html .= '<p>Queue Size: <strong>' . $queueSize . '</strong></p>';
        
        if ($failedJobs > 0) {
            $html .= '<p class="text-danger">Failed Jobs (24h): <strong>' . $failedJobs . '</strong></p>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Format triggers for display
     * 
     * @param array $triggers
     * @return string
     */
    protected function formatTriggers(array $triggers): string
    {
        if (empty($triggers)) {
            return '<div class="alert alert-warning">No active triggers detected. Please set up cron, systemd, or webhook triggers.</div>';
        }
        
        $html = '<div class="scheduler-triggers">';
        $html .= '<ul class="list-unstyled">';
        
        foreach ($triggers as $trigger) {
            $icon = match($trigger) {
                'cron' => '⏰',
                'systemd' => '⚙️',
                'webhook' => '🔗',
                'external' => '🌐',
                default => '•'
            };
            
            $label = match($trigger) {
                'cron' => 'Cron Job',
                'systemd' => 'Systemd Timer',
                'webhook' => 'Webhook Triggers',
                'external' => 'External Triggers',
                default => ucfirst($trigger)
            };
            
            $html .= '<li>' . $icon . ' <strong>' . $label . '</strong> <span class="badge badge-success">Active</span></li>';
        }
        
        $html .= '</ul>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Create JSON response
     * 
     * @param array $data
     * @param int $statusCode
     * @return ResponseInterface
     */
    protected function jsonResponse(array $data, int $statusCode = 200): ResponseInterface
    {
        $response = $this->grav['response'] ?? new \Nyholm\Psr7\Response();
        
        $response = $response->withStatus($statusCode)
                            ->withHeader('Content-Type', 'application/json');
        
        $body = $response->getBody();
        $body->write(json_encode($data));
        
        return $response;
    }
}