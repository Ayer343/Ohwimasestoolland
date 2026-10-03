<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

trait AuditLogger
{
    /**
     * Log an audit event
     *
     * @param string $action
     * @param string $description
     * @param array $metadata
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @return AuditLog
     */
    protected function logAudit(
        string $action,
        string $description,
        array $metadata = [],
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): AuditLog {
        try {
            $user = Auth::user();
            
            $logData = [
                'action' => $action,
                'description' => $description,
                'metadata' => json_encode($metadata),
                'ip_address' => $ipAddress ?? Request::ip(),
                'user_agent' => $userAgent ?? Request::userAgent(),
                'loggable_type' => get_class($user ?? $this),
                'loggable_id' => $user ? $user->id : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // For developer-specific actions
            if ($user && $user->type === 'developer') {
                $logData['loggable_type'] = 'developer';
                $logData['developer_setting_id'] = $user->developerSetting->id ?? null;
            }

            // Create the audit log
            $auditLog = AuditLog::create($logData);

            // Also log to Laravel's default log for backup
            Log::info('Audit Log: ' . $action, [
                'description' => $description,
                'user' => $user ? $user->email : 'system',
                'ip' => $logData['ip_address'],
                'metadata' => $metadata
            ]);

            return $auditLog;

        } catch (\Exception $e) {
            // Fallback to Laravel log if audit table fails
            Log::error('Failed to create audit log: ' . $e->getMessage(), [
                'action' => $action,
                'description' => $description,
                'metadata' => $metadata
            ]);
            
            // Create a minimal audit log record
            return AuditLog::create([
                'action' => $action,
                'description' => 'Error creating audit log: ' . $e->getMessage(),
                'metadata' => json_encode(['error' => true]),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Log billing audit with specific developer context
     *
     * @param mixed $developerSetting
     * @param string $action
     * @param array $metadata
     * @return AuditLog
     */
    protected function logBillingAudit($developerSetting, string $action, array $metadata = []): AuditLog
    {
        $description = $this->getBillingAuditDescription($action);
        
        $metadata = array_merge($metadata, [
            'developer_setting_id' => $developerSetting->id,
            'developer_email' => $developerSetting->developer_email,
            'billing_currency' => $developerSetting->billing_currency,
            'billing_cycle' => $developerSetting->billing_cycle,
        ]);

        return $this->logAudit('billing_' . $action, $description, $metadata);
    }

    /**
     * Get description for billing audit actions
     *
     * @param string $action
     * @return string
     */
    private function getBillingAuditDescription(string $action): string
    {
        $descriptions = [
            'settings_updated' => 'Billing settings updated',
            'invoice_generated' => 'Invoice generated',
            'payment_confirmed' => 'Payment confirmed',
            'payment_received' => 'Payment received',
            'invoice_cancelled' => 'Invoice cancelled',
            'proposal_created' => 'Billing proposal created',
            'proposal_approved' => 'Billing proposal approved',
            'proposal_cancelled' => 'Billing proposal cancelled',
            'reminder_sent' => 'Payment reminder sent',
            'super_admin_request_created' => 'Super Admin payment request created',
            'super_admin_payment_received' => 'Super Admin payment received',
            'custom_invoice_generated' => 'Custom invoice generated',
        ];

        return $descriptions[$action] ?? 'Billing action: ' . $action;
    }

    /**
     * Log system audit (non-user actions)
     *
     * @param string $action
     * @param string $description
     * @param array $metadata
     * @return AuditLog
     */
    protected function logSystemAudit(string $action, string $description, array $metadata = []): AuditLog
    {
        $metadata['system_action'] = true;
        $metadata['executed_by'] = 'system';

        return $this->logAudit('system_' . $action, $description, $metadata);
    }

    /**
     * Log security audit
     *
     * @param string $action
     * @param string $description
     * @param array $metadata
     * @return AuditLog
     */
    protected function logSecurityAudit(string $action, string $description, array $metadata = []): AuditLog
    {
        $metadata['security_event'] = true;
        
        return $this->logAudit('security_' . $action, $description, $metadata);
    }

    /**
     * Log API audit
     *
     * @param string $action
     * @param array $requestData
     * @param array $responseData
     * @param string $apiKey
     * @return AuditLog
     */
    protected function logApiAudit(
        string $action,
        array $requestData = [],
        array $responseData = [],
        string $apiKey = ''
    ): AuditLog {
        // Sanitize sensitive data
        $sanitizedRequest = $this->sanitizeAuditData($requestData);
        $sanitizedResponse = $this->sanitizeAuditData($responseData);

        $metadata = [
            'api_action' => $action,
            'request_data' => $sanitizedRequest,
            'response_data' => $sanitizedResponse,
            'api_key' => substr($apiKey, 0, 8) . '...', // Only show first 8 chars
            'endpoint' => Request::path(),
            'method' => Request::method(),
        ];

        return $this->logAudit('api_' . $action, 'API request processed', $metadata);
    }

    /**
     * Sanitize sensitive data for audit logs
     *
     * @param array $data
     * @return array
     */
    private function sanitizeAuditData(array $data): array
    {
        $sensitiveFields = [
            'password',
            'secret',
            'token',
            'key',
            'cvv',
            'ssn',
            'credit_card',
            'developer_smtp_password',
            'developer_secret_key',
            'payment_mobile_number',
            'payment_account_number',
        ];

        $sanitized = $data;

        foreach ($sanitized as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeAuditData($value);
            } elseif (is_string($value)) {
                foreach ($sensitiveFields as $sensitive) {
                    if (stripos($key, $sensitive) !== false && !empty($value)) {
                        $sanitized[$key] = '[REDACTED]';
                        break;
                    }
                }
            }
        }

        return $sanitized;
    }

    /**
     * Get audit logs for a specific developer
     *
     * @param int $developerSettingId
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getDeveloperAuditLogs(
        int $developerSettingId,
        array $filters = [],
        int $perPage = 20
    ) {
        $query = AuditLog::where('developer_setting_id', $developerSettingId)
            ->orWhere(function ($query) use ($developerSettingId) {
                $query->where('loggable_type', 'developer')
                    ->where('metadata->developer_setting_id', $developerSettingId);
            });

        // Apply filters
        if (!empty($filters['action'])) {
            $query->where('action', 'LIKE', '%' . $filters['action'] . '%');
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['ip_address'])) {
            $query->where('ip_address', $filters['ip_address']);
        }

        return $query->orderBy('created_at', 'desc')
                    ->paginate($perPage);
    }

    /**
     * Get audit summary statistics
     *
     * @param int $developerSettingId
     * @param string $period days, weeks, months
     * @return array
     */
    public function getAuditStatistics(int $developerSettingId, string $period = 'days'): array
    {
        $now = now();
        $startDate = match ($period) {
            'weeks' => $now->subWeek(),
            'months' => $now->subMonth(),
            default => $now->subDay(),
        };

        $logs = AuditLog::where('developer_setting_id', $developerSettingId)
            ->where('created_at', '>=', $startDate)
            ->get();

        $actions = $logs->groupBy('action')->map->count();

        return [
            'total_actions' => $logs->count(),
            'actions_breakdown' => $actions,
            'unique_ips' => $logs->pluck('ip_address')->unique()->count(),
            'period' => $period,
            'start_date' => $startDate->toDateString(),
            'end_date' => $now->toDateString(),
        ];
    }

    /**
     * Export audit logs
     *
     * @param int $developerSettingId
     * @param array $filters
     * @param string $format csv, excel, pdf
     * @return mixed
     */
    public function exportAuditLogs(
        int $developerSettingId,
        array $filters = [],
        string $format = 'csv'
    ) {
        $logs = $this->getDeveloperAuditLogs($developerSettingId, $filters, 1000);

        $data = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                'metadata' => json_encode($log->metadata),
            ];
        });

        return $this->generateExport($data, $format, 'audit_logs_' . date('Y-m-d'));
    }

    /**
     * Generate export file
     *
     * @param \Illuminate\Support\Collection $data
     * @param string $format
     * @param string $filename
     * @return mixed
     */
    private function generateExport($data, string $format, string $filename)
    {
        switch ($format) {
            case 'csv':
                return $this->exportToCsv($data, $filename);
            case 'excel':
                return $this->exportToExcel($data, $filename);
            case 'pdf':
                return $this->exportToPdf($data, $filename);
            default:
                throw new \InvalidArgumentException("Unsupported format: {$format}");
        }
    }

    /**
     * Export to CSV
     *
     * @param \Illuminate\Support\Collection $data
     * @param string $filename
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    private function exportToCsv($data, string $filename)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for Excel compatibility
            fwrite($file, "\xEF\xBB\xBF");
            
            // Write headers
            fputcsv($file, array_keys($data->first() ?? []));
            
            // Write data
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export to Excel (placeholder - implement with Laravel Excel package)
     *
     * @param \Illuminate\Support\Collection $data
     * @param string $filename
     * @return void
     */
    private function exportToExcel($data, string $filename)
    {
        // Implementation would depend on Laravel Excel package
        // For now, redirect to CSV export
        return $this->exportToCsv($data, $filename);
    }

    /**
     * Export to PDF (placeholder - implement with DomPDF package)
     *
     * @param \Illuminate\Support\Collection $data
     * @param string $filename
     * @return void
     */
    private function exportToPdf($data, string $filename)
    {
        // Implementation would depend on DomPDF package
        throw new \Exception('PDF export not implemented. Use CSV format instead.');
    }

    /**
     * Clean up old audit logs
     *
     * @param int $daysToKeep
     * @return int
     */
    public function cleanupOldAuditLogs(int $daysToKeep = 90): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        
        $deletedCount = AuditLog::where('created_at', '<', $cutoffDate)->delete();
        
        // Log the cleanup
        $this->logSystemAudit('audit_cleanup', 'Old audit logs cleaned up', [
            'days_to_keep' => $daysToKeep,
            'cutoff_date' => $cutoffDate->toDateString(),
            'deleted_count' => $deletedCount,
        ]);

        return $deletedCount;
    }
}