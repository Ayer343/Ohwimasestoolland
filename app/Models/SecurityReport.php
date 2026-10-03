<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SecurityReport extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'report_number',
        'title',
        'description',
        'security_post_id',
        'security_schedule_id',
        'reported_by',
        'assigned_to',
        'verified_by',
        'closed_by',
        'report_type',
        'category',
        'priority',
        'status',
        'incident_date',
        'report_date',
        'location',
        'digital_address',
        'personnel_involved',
        'witnesses',
        'impact_assessment',
        'immediate_actions',
        'recommendations',
        'resolution_details',
        'verified_at',
        'verification_notes',
        'resolved_at',
        'closed_at',
        'closure_notes',
        'evidence_photos',
        'evidence_documents',
        'requires_followup',
        'followup_date',
        'is_confidential',
        'notify_stakeholders',
        'notified_stakeholders',
        'severity_level',
        'estimated_damage',
        'personnel_injured',
        'personnel_deceased',
        'civilians_affected',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'incident_date' => 'datetime',
        'report_date' => 'datetime',
        'verified_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'followup_date' => 'datetime',
        'personnel_involved' => 'array',
        'witnesses' => 'array',
        'evidence_photos' => 'array',
        'evidence_documents' => 'array',
        'notified_stakeholders' => 'array',
        'metadata' => 'array',
        'requires_followup' => 'boolean',
        'is_confidential' => 'boolean',
        'notify_stakeholders' => 'boolean',
        'estimated_damage' => 'decimal:2',
        'severity_level' => 'integer',
        'personnel_injured' => 'integer',
        'personnel_deceased' => 'integer',
        'civilians_affected' => 'integer',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'incident_date',
        'report_date',
        'verified_at',
        'resolved_at',
        'closed_at',
        'followup_date',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($report) {
            if (empty($report->report_number)) {
                $report->report_number = 'SR-' . date('Ymd') . '-' . strtoupper(uniqid());
            }
        });
    }

    // ==================== CONSTANTS ====================

    // Report Types
    const TYPE_INCIDENT = 'incident';
    const TYPE_DAILY_LOG = 'daily_log';
    const TYPE_INSPECTION = 'inspection';
    const TYPE_EQUIPMENT_CHECK = 'equipment_check';
    const TYPE_MAINTENANCE = 'maintenance';
    const TYPE_AUDIT = 'audit';
    const TYPE_PATROL = 'patrol';

    // Categories
    const CATEGORY_SECURITY_BREACH = 'security_breach';
    const CATEGORY_UNAUTHORIZED_ENTRY = 'unauthorized_entry';
    const CATEGORY_EQUIPMENT_FAILURE = 'equipment_failure';
    const CATEGORY_SAFETY_HAZARD = 'safety_hazard';
    const CATEGORY_THEFT = 'theft';
    const CATEGORY_VANDALISM = 'vandalism';
    const CATEGORY_FIRE = 'fire';
    const CATEGORY_MEDICAL = 'medical';
    const CATEGORY_NATURAL_DISASTER = 'natural_disaster';
    const CATEGORY_OTHER = 'other';

    // Priorities
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_CRITICAL = 'critical';

    // Statuses
    const STATUS_PENDING = 'pending';
    const STATUS_UNDER_INVESTIGATION = 'under_investigation';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_CLOSED = 'closed';
    const STATUS_CANCELLED = 'cancelled';

    // ==================== SCOPES ====================

    /**
     * Scope for pending reports
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for active reports (not closed or cancelled)
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_CLOSED, self::STATUS_CANCELLED]);
    }

    /**
     * Scope for high priority reports
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', [self::PRIORITY_HIGH, self::PRIORITY_CRITICAL]);
    }

    /**
     * Scope for reports requiring followup
     */
    public function scopeRequiresFollowup($query)
    {
        return $query->where('requires_followup', true)
                     ->where(function ($q) {
                         $q->whereNull('followup_date')
                           ->orWhere('followup_date', '>=', now());
                     });
    }

    /**
     * Scope for reports by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('report_type', $type);
    }

    /**
     * Scope for reports in date range
     */
    public function scopeDateRange($query, $startDate, $endDate = null)
    {
        if (!$endDate) {
            $endDate = $startDate;
        }
        
        return $query->whereDate('incident_date', '>=', $startDate)
                     ->whereDate('incident_date', '<=', $endDate);
    }

    /**
     * Scope for reports at specific post
     */
    public function scopeAtPost($query, $postId)
    {
        return $query->where('security_post_id', $postId);
    }

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the security post associated with the report
     */
    public function securityPost()
    {
        return $this->belongsTo(SecurityPost::class);
    }

    /**
     * Get the security schedule associated with the report
     */
    public function securitySchedule()
    {
        return $this->belongsTo(SecuritySchedule::class);
    }

    /**
     * Get the user who reported the incident
     */
    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * Get the user assigned to investigate
     */
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who verified the report
     */
    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Get the user who closed the report
     */
    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Get personnel involved (users)
     */
    public function involvedPersonnel()
    {
        return $this->belongsToMany(User::class, 'security_report_personnel', 'security_report_id', 'user_id')
                    ->withTimestamps();
    }

    // ==================== ACCESSORS ====================

    /**
     * Get report type label
     */
    public function getReportTypeLabelAttribute()
    {
        return $this->getReportTypeLabels()[$this->report_type] ?? ucfirst($this->report_type);
    }

    /**
     * Get category label
     */
    public function getCategoryLabelAttribute()
    {
        return $this->getCategoryLabels()[$this->category] ?? ucfirst($this->category);
    }

    /**
     * Get priority label
     */
    public function getPriorityLabelAttribute()
    {
        return $this->getPriorityLabels()[$this->priority] ?? ucfirst($this->priority);
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute()
    {
        return $this->getStatusLabels()[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Get priority color
     */
    public function getPriorityColorAttribute()
    {
        $colors = [
            self::PRIORITY_LOW => 'success',
            self::PRIORITY_MEDIUM => 'warning',
            self::PRIORITY_HIGH => 'danger',
            self::PRIORITY_CRITICAL => 'purple',
        ];
        
        return $colors[$this->priority] ?? 'secondary';
    }

    /**
     * Get status color
     */
    public function getStatusColorAttribute()
    {
        $colors = [
            self::STATUS_PENDING => 'warning',
            self::STATUS_UNDER_INVESTIGATION => 'info',
            self::STATUS_RESOLVED => 'success',
            self::STATUS_CLOSED => 'secondary',
            self::STATUS_CANCELLED => 'danger',
        ];
        
        return $colors[$this->status] ?? 'secondary';
    }

    /**
     * Get incident date in readable format
     */
    public function getIncidentDateFormattedAttribute()
    {
        return $this->incident_date ? $this->incident_date->format('M j, Y g:i A') : 'N/A';
    }

    /**
     * Get report date in readable format
     */
    public function getReportDateFormattedAttribute()
    {
        return $this->created_at->format('M j, Y g:i A');
    }

    /**
     * Get time elapsed since incident
     */
    public function getTimeElapsedAttribute()
    {
        if (!$this->incident_date) {
            return 'N/A';
        }
        
        return $this->incident_date->diffForHumans();
    }

    /**
     * Get resolution time (if resolved)
     */
    public function getResolutionTimeAttribute()
    {
        if (!$this->resolved_at || !$this->incident_date) {
            return null;
        }
        
        return $this->incident_date->diff($this->resolved_at);
    }

    /**
     * Check if report is overdue (pending for more than 24 hours)
     */
    public function getIsOverdueAttribute()
    {
        return $this->status === self::STATUS_PENDING && 
               $this->created_at->diffInHours(now()) > 24;
    }

    /**
     * Check if report requires immediate attention
     */
    public function getRequiresImmediateAttentionAttribute()
    {
        return in_array($this->priority, [self::PRIORITY_HIGH, self::PRIORITY_CRITICAL]) &&
               in_array($this->status, [self::STATUS_PENDING, self::STATUS_UNDER_INVESTIGATION]);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Get all report types with labels
     */
    public static function getReportTypes()
    {
        return [
            self::TYPE_INCIDENT => 'Security Incident',
            self::TYPE_DAILY_LOG => 'Daily Security Log',
            self::TYPE_INSPECTION => 'Security Inspection',
            self::TYPE_EQUIPMENT_CHECK => 'Equipment Check',
            self::TYPE_MAINTENANCE => 'Maintenance Report',
            self::TYPE_AUDIT => 'Security Audit',
            self::TYPE_PATROL => 'Patrol Report',
        ];
    }

    /**
     * Get all categories with labels
     */
    public static function getCategories()
    {
        return [
            self::CATEGORY_SECURITY_BREACH => 'Security Breach',
            self::CATEGORY_UNAUTHORIZED_ENTRY => 'Unauthorized Entry',
            self::CATEGORY_EQUIPMENT_FAILURE => 'Equipment Failure',
            self::CATEGORY_SAFETY_HAZARD => 'Safety Hazard',
            self::CATEGORY_THEFT => 'Theft',
            self::CATEGORY_VANDALISM => 'Vandalism',
            self::CATEGORY_FIRE => 'Fire Incident',
            self::CATEGORY_MEDICAL => 'Medical Emergency',
            self::CATEGORY_NATURAL_DISASTER => 'Natural Disaster',
            self::CATEGORY_OTHER => 'Other',
        ];
    }

    /**
     * Get all priorities with labels
     */
    public static function getPriorities()
    {
        return [
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_MEDIUM => 'Medium',
            self::PRIORITY_HIGH => 'High',
            self::PRIORITY_CRITICAL => 'Critical',
        ];
    }

    /**
     * Get all statuses with labels
     */
    public static function getStatuses()
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_UNDER_INVESTIGATION => 'Under Investigation',
            self::STATUS_RESOLVED => 'Resolved',
            self::STATUS_CLOSED => 'Closed',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /**
     * Get report type labels
     */
    private function getReportTypeLabels()
    {
        return self::getReportTypes();
    }

    /**
     * Get category labels
     */
    private function getCategoryLabels()
    {
        return self::getCategories();
    }

    /**
     * Get priority labels
     */
    private function getPriorityLabels()
    {
        return self::getPriorities();
    }

    /**
     * Get status labels
     */
    private function getStatusLabels()
    {
        return self::getStatuses();
    }

    /**
     * Mark report as verified
     */
    public function markAsVerified($userId, $notes = null)
    {
        $this->update([
            'verified_by' => $userId,
            'verified_at' => now(),
            'verification_notes' => $notes,
        ]);
        
        return $this;
    }

    /**
     * Mark report as resolved
     */
    public function markAsResolved($resolutionDetails = null)
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'resolved_at' => now(),
            'resolution_details' => $resolutionDetails,
        ]);
        
        return $this;
    }

    /**
     * Mark report as closed
     */
    public function markAsClosed($userId, $notes = null)
    {
        $this->update([
            'status' => self::STATUS_CLOSED,
            'closed_by' => $userId,
            'closed_at' => now(),
            'closure_notes' => $notes,
        ]);
        
        return $this;
    }

    /**
     * Assign report to user
     */
    public function assignTo($userId)
    {
        $this->update([
            'assigned_to' => $userId,
            'status' => self::STATUS_UNDER_INVESTIGATION,
        ]);
        
        return $this;
    }

    /**
     * Add evidence photo
     */
    public function addEvidencePhoto($photoPath)
    {
        $photos = $this->evidence_photos ?? [];
        $photos[] = $photoPath;
        
        $this->update(['evidence_photos' => $photos]);
        
        return $this;
    }

    /**
     * Add evidence document
     */
    public function addEvidenceDocument($documentPath)
    {
        $documents = $this->evidence_documents ?? [];
        $documents[] = $documentPath;
        
        $this->update(['evidence_documents' => $documents]);
        
        return $this;
    }

    /**
     * Add personnel involved
     */
    public function addPersonnelInvolved($userId)
    {
        $personnel = $this->personnel_involved ?? [];
        if (!in_array($userId, $personnel)) {
            $personnel[] = $userId;
            $this->update(['personnel_involved' => $personnel]);
        }
        
        return $this;
    }

    /**
     * Check if user can view report (confidentiality check)
     */
    public function canView($user)
    {
        // If not confidential, anyone can view
        if (!$this->is_confidential) {
            return true;
        }
        
        // User is admin or super admin
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }
        
        // User is assigned to the report
        if ($this->assigned_to === $user->id) {
            return true;
        }
        
        // User reported the incident
        if ($this->reported_by === $user->id) {
            return true;
        }
        
        // User is involved personnel
        if (in_array($user->id, $this->personnel_involved ?? [])) {
            return true;
        }
        
        return false;
    }

    /**
     * Generate report statistics
     */
    public static function generateStatistics($startDate = null, $endDate = null)
    {
        $query = self::query();
        
        if ($startDate && $endDate) {
            $query->whereBetween('incident_date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->whereDate('incident_date', '>=', $startDate);
        }
        
        $total = $query->count();
        $resolved = $query->where('status', self::STATUS_RESOLVED)->count();
        $pending = $query->where('status', self::STATUS_PENDING)->count();
        $investigation = $query->where('status', self::STATUS_UNDER_INVESTIGATION)->count();
        $closed = $query->where('status', self::STATUS_CLOSED)->count();
        
        // By priority
        $low = $query->where('priority', self::PRIORITY_LOW)->count();
        $medium = $query->where('priority', self::PRIORITY_MEDIUM)->count();
        $high = $query->where('priority', self::PRIORITY_HIGH)->count();
        $critical = $query->where('priority', self::PRIORITY_CRITICAL)->count();
        
        // By type
        $types = [];
        foreach (self::getReportTypes() as $type => $label) {
            $types[$type] = $query->where('report_type', $type)->count();
        }
        
        // Average resolution time
        $resolvedReports = self::whereNotNull('resolved_at')->whereNotNull('incident_date');
        if ($startDate && $endDate) {
            $resolvedReports->whereBetween('incident_date', [$startDate, $endDate]);
        }
        
        $avgResolutionHours = $resolvedReports->get()
            ->avg(function ($report) {
                return $report->incident_date->diffInHours($report->resolved_at);
            });
        
        return [
            'total' => $total,
            'resolved' => $resolved,
            'pending' => $pending,
            'under_investigation' => $investigation,
            'closed' => $closed,
            'by_priority' => [
                'low' => $low,
                'medium' => $medium,
                'high' => $high,
                'critical' => $critical,
            ],
            'by_type' => $types,
            'avg_resolution_hours' => round($avgResolutionHours ?? 0, 2),
            'resolution_rate' => $total > 0 ? round(($resolved / $total) * 100, 2) : 0,
        ];
    }
}