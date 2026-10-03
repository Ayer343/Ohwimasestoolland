<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class SecurityScheduleTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'security_post_id',
        'security_shift_id',
        'assigned_users',
        'recurrence_pattern',
        'start_date',
        'end_date',
        'is_active',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'assigned_users' => 'array',
        'recurrence_pattern' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function post()
    {
        return $this->belongsTo(SecurityPost::class, 'security_post_id');
    }

    public function shift()
    {
        return $this->belongsTo(SecurityShift::class, 'security_shift_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedUsers()
    {
        return User::whereIn('id', $this->assigned_users ?? [])->get();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCurrent($query)
    {
        return $query->where('start_date', '<=', today())
            ->where(function($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', today());
            });
    }

    // Helper Methods
    public function generateSchedules($startDate = null, $endDate = null)
    {
        $startDate = $startDate ? Carbon::parse($startDate) : Carbon::parse($this->start_date);
        $endDate = $endDate ? Carbon::parse($endDate) : ($this->end_date ? Carbon::parse($this->end_date) : $startDate->copy()->addMonth());

        $schedules = [];
        $pattern = $this->recurrence_pattern ?? ['type' => 'weekly', 'days' => [1, 2, 3, 4, 5]];

        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            if ($this->shouldScheduleOnDate($currentDate, $pattern)) {
                foreach ($this->assigned_users as $userId) {
                    $schedule = SecuritySchedule::firstOrCreate([
                        'security_post_id' => $this->security_post_id,
                        'security_shift_id' => $this->security_shift_id,
                        'security_user_id' => $userId,
                        'assignment_date' => $currentDate->toDateString(),
                    ], [
                        'assigned_by' => $this->created_by,
                        'status' => 'scheduled',
                        'notes' => "Auto-generated from template: {$this->name}",
                    ]);

                    $schedules[] = $schedule;
                }
            }
            $currentDate->addDay();
        }

        return $schedules;
    }

    private function shouldScheduleOnDate(Carbon $date, array $pattern)
    {
        $dayOfWeek = $date->dayOfWeekIso; // 1=Monday, 7=Sunday

        switch ($pattern['type'] ?? 'weekly') {
            case 'daily':
                return true;
            case 'weekly':
                return in_array($dayOfWeek, $pattern['days'] ?? [1, 2, 3, 4, 5]);
            case 'monthly':
                $dayOfMonth = $date->day;
                return in_array($dayOfMonth, $pattern['days'] ?? []);
            case 'custom':
                // Custom logic based on pattern
                return true;
            default:
                return false;
        }
    }

    public function getRecurrenceDescription()
    {
        $pattern = $this->recurrence_pattern ?? ['type' => 'weekly', 'days' => [1, 2, 3, 4, 5]];

        switch ($pattern['type']) {
            case 'daily':
                return 'Every day';
            case 'weekly':
                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                $selectedDays = array_map(function($day) use ($days) {
                    return $days[$day - 1] ?? $day;
                }, $pattern['days'] ?? [1, 2, 3, 4, 5]);
                return 'Every ' . implode(', ', $selectedDays);
            case 'monthly':
                return 'Monthly on day(s): ' . implode(', ', $pattern['days'] ?? []);
            case 'custom':
                return 'Custom schedule';
            default:
                return 'Weekly (Mon-Fri)';
        }
    }

    public function deactivate()
    {
        $this->update(['is_active' => false]);
        return $this;
    }

    public function activate()
    {
        $this->update(['is_active' => true]);
        return $this;
    }
}