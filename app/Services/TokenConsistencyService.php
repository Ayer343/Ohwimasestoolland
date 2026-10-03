<?php

namespace App\Services;

use App\Models\AgentInvitation;
use App\Models\AgentInvitationLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TokenConsistencyService
{
    protected $invitationService;

    public function __construct(AgentInvitationService $invitationService)
    {
        $this->invitationService = $invitationService;
    }

    /**
     * ✅ COMPREHENSIVE: Scan all invitations for token inconsistencies
     */
    public function scanForTokenInconsistencies($planId = null): array
    {
        try {
            $query = AgentInvitation::query();
            
            if ($planId) {
                $query->where('plan_id', $planId);
            }

            $invitations = $query->get();
            $inconsistencies = [];
            $healthyCount = 0;

            foreach ($invitations as $invitation) {
                $analysis = $this->analyzeInvitationToken($invitation);
                
                if ($analysis['has_issues']) {
                    $inconsistencies[] = $analysis;
                } else {
                    $healthyCount++;
                }
            }

            return [
                'success' => true,
                'total_invitations' => $invitations->count(),
                'healthy_count' => $healthyCount,
                'inconsistency_count' => count($inconsistencies),
                'inconsistencies' => $inconsistencies,
                'summary' => [
                    'critical' => count(array_filter($inconsistencies, fn($i) => $i['severity'] === 'critical')),
                    'warning' => count(array_filter($inconsistencies, fn($i) => $i['severity'] === 'warning')),
                    'info' => count(array_filter($inconsistencies, fn($i) => $i['severity'] === 'info')),
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Token inconsistency scan failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Scan failed: ' . $e->getMessage(),
                'total_invitations' => 0,
                'healthy_count' => 0,
                'inconsistency_count' => 0,
                'inconsistencies' => []
            ];
        }
    }

    /**
     * ✅ ANALYZE: Deep analysis of invitation token health
     */
    public function analyzeInvitationToken(AgentInvitation $invitation): array
    {
        try {
            $issues = [];
            $severity = 'healthy';
            
            // Check if token exists
            if (empty($invitation->token)) {
                $issues[] = 'Missing token';
                $severity = 'critical';
            }
            
            // Check token length
            if (strlen($invitation->token) !== 64) {
                $issues[] = 'Invalid token length: ' . strlen($invitation->token);
                $severity = 'critical';
            }
            
            // Check metadata consistency
            $metadata = $invitation->metadata ?? [];
            if (isset($metadata['master_token']) && $metadata['master_token'] !== $invitation->token) {
                $issues[] = 'Metadata master_token mismatch';
                $severity = 'critical';
            }
            
            // Check channel tracking
            $channelsSent = $metadata['channels_sent'] ?? [];
            foreach ($channelsSent as $channel => $details) {
                if (isset($details['token_used']) && $details['token_used'] !== $invitation->token) {
                    $issues[] = "Channel {$channel} used different token";
                    $severity = 'critical';
                }
            }
            
            // Generate invitation URL for testing
            $invitationUrl = route('agent.invitations.accept', ['token' => $invitation->token]);
            $urlToken = basename(parse_url($invitationUrl, PHP_URL_PATH));
            
            if ($urlToken !== $invitation->token) {
                $issues[] = 'URL token mismatch';
                $severity = 'critical';
            }

            return [
                'invitation_id' => $invitation->id,
                'plan_id' => $invitation->plan_id,
                'agent_id' => $invitation->agent_id,
                'status' => $invitation->status,
                'token' => $invitation->token,
                'token_length' => strlen($invitation->token),
                'has_issues' => !empty($issues),
                'severity' => $severity,
                'issues' => $issues,
                'invitation_url' => $invitationUrl,
                'url_token_match' => $urlToken === $invitation->token,
                'metadata_consistent' => !isset($metadata['master_token']) || $metadata['master_token'] === $invitation->token,
                'channels_consistent' => $this->checkChannelsConsistency($channelsSent, $invitation->token),
                'analysis_timestamp' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('Token analysis failed for invitation: ' . $invitation->id);
            
            return [
                'invitation_id' => $invitation->id,
                'has_issues' => true,
                'severity' => 'critical',
                'issues' => ['Analysis failed: ' . $e->getMessage()],
                'analysis_failed' => true
            ];
        }
    }

    /**
     * ✅ CHECK: Channel consistency
     */
    private function checkChannelsConsistency(array $channelsSent, string $expectedToken): bool
    {
        foreach ($channelsSent as $channel => $details) {
            if (isset($details['token_used']) && $details['token_used'] !== $expectedToken) {
                return false;
            }
        }
        return true;
    }

    /**
     * ✅ BULK REPAIR: Fix all detected token inconsistencies
     */
    public function bulkRepairTokenInconsistencies(array $inconsistencies): array
    {
        $results = [
            'repaired' => 0,
            'failed' => 0,
            'skipped' => 0,
            'details' => []
        ];

        foreach ($inconsistencies as $inconsistency) {
            if (!$inconsistency['has_issues'] || $inconsistency['severity'] === 'healthy') {
                $results['skipped']++;
                continue;
            }

            // For critical issues with clear token, attempt repair
            if ($inconsistency['severity'] === 'critical' && !empty($inconsistency['token'])) {
                $repairResult = $this->attemptAutomaticRepair($inconsistency);
                
                if ($repairResult['success']) {
                    $results['repaired']++;
                    $results['details'][] = [
                        'invitation_id' => $inconsistency['invitation_id'],
                        'status' => 'repaired',
                        'action' => $repairResult['action']
                    ];
                } else {
                    $results['failed']++;
                    $results['details'][] = [
                        'invitation_id' => $inconsistency['invitation_id'],
                        'status' => 'failed',
                        'error' => $repairResult['message']
                    ];
                }
            } else {
                $results['skipped']++;
                $results['details'][] = [
                    'invitation_id' => $inconsistency['invitation_id'],
                    'status' => 'skipped',
                    'reason' => 'Not critical or no clear repair path'
                ];
            }
        }

        return $results;
    }

    /**
     * ✅ ATTEMPT: Automatic repair based on analysis
     */
    private function attemptAutomaticRepair(array $inconsistency): array
    {
        try {
            $invitationId = $inconsistency['invitation_id'];
            
            // If we have metadata with master_token, use that
            if (isset($inconsistency['metadata_consistent']) && !$inconsistency['metadata_consistent']) {
                $invitation = AgentInvitation::find($invitationId);
                $metadata = $invitation->metadata ?? [];
                
                if (isset($metadata['master_token'])) {
                    $repairResult = $this->invitationService->repairTokenInconsistency(
                        $invitationId, 
                        $metadata['master_token']
                    );
                    
                    return [
                        'success' => $repairResult['success'],
                        'action' => 'restored_from_metadata',
                        'message' => $repairResult['message']
                    ];
                }
            }
            
            // If URL token doesn't match, but we have a valid token
            if (isset($inconsistency['url_token_match']) && !$inconsistency['url_token_match']) {
                // The token in database might be correct, just ensure URL works
                $invitation = AgentInvitation::find($invitationId);
                $currentToken = $invitation->token;
                
                if (strlen($currentToken) === 64) {
                    // Token looks valid, log the issue but don't change
                    Log::warning('URL token mismatch but DB token appears valid', [
                        'invitation_id' => $invitationId,
                        'db_token' => $currentToken
                    ]);
                    
                    return [
                        'success' => true,
                        'action' => 'verified_db_token',
                        'message' => 'Database token appears valid, URL generation issue noted'
                    ];
                }
            }
            
            // Last resort: generate new token
            $newToken = Str::random(64);
            $repairResult = $this->invitationService->repairTokenInconsistency($invitationId, $newToken);
            
            return [
                'success' => $repairResult['success'],
                'action' => 'generated_new_token',
                'message' => $repairResult['message']
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'action' => 'repair_failed',
                'message' => 'Repair attempt failed: ' . $e->getMessage()
            ];
        }
    }
}