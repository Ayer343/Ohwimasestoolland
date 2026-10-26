<?php

namespace App\Services;

use App\Models\RotationGroup;
use App\Models\SecuritySchedule;
use App\Models\SecurityShift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RotationService
{
    /**
     * Resolve which members are assigned on a given date for a rotation pattern.
     *
     * @return array<int> user IDs
     */
    public function resolveMembersForDate(
        array $sortedUserIds,
        array $preferenceScores,
        Carbon $date,
        Carbon $startDate,
        string $pattern,
        int $slotsToFill,
        array &$existingAssignments
    ): array {
        $assigned = [];
        $daysDiff = $startDate->diffInDays($date);
        $userCount = count($sortedUserIds);

        if ($userCount === 0) {
            return [];
        }

        $candidates = match ($pattern) {
            'full_swap' => (function () use ($sortedUserIds, $userCount, $daysDiff) {
                $half = (int) ceil($userCount / 2);
                $groupA = array_slice($sortedUserIds, 0, $half);
                $groupB = array_slice($sortedUserIds, $half);
                return (floor($daysDiff / 7) % 2 === 0) ? $groupA : $groupB;
            })(),
            'staggered' => (function () use ($sortedUserIds, $userCount, $daysDiff) {
                $rotateCount = (int) ceil($userCount / 2);
                $startIndex = $daysDiff % $userCount;
                $out = [];
                for ($i = 0; $i < $rotateCount; $i++) {
                    $out[] = $sortedUserIds[($startIndex + $i) % $userCount];
                }
                return $out;
            })(),
            'partial' => (function () use ($sortedUserIds, $preferenceScores) {
                $willing = array_values(array_filter(
                    $sortedUserIds,
                    fn ($id) => ($preferenceScores[$id] ?? 5) >= 7
                ));
                return !empty($willing) ? $willing : $sortedUserIds;
            })(),
            'sequential' => (function () use ($sortedUserIds, $userCount, $daysDiff) {
                $startIndex = $daysDiff % $userCount;
                $out = [];
                for ($i = 0; $i < $userCount; $i++) {
                    $out[] = $sortedUserIds[($startIndex + $i) % $userCount];
                }
                return $out;
            })(),
            'alternating' => (function () use ($preferenceScores, $userCount, $daysDiff) {
                $sorted = array_keys($preferenceScores);
                $half = (int) ceil($userCount / 2);
                $top = array_slice($sorted, 0, $half);
                $bottom = array_slice($sorted, $half);
                return (floor($daysDiff / 7) % 2 === 0) ? $top : $bottom;
            })(),
            'preference_based' => array_keys($preferenceScores),
            default => $sortedUserIds,
        };

        foreach ($candidates as $userId) {
            if (count($assigned) >= $slotsToFill) {
                break;
            }
            $key = $userId . '_' . $date->format('Y-m-d');
            if (!isset($existingAssignments[$key])) {
                $assigned[] = $userId;
                $existingAssignments[$key] = true;
            }
        }

        return $assigned;
    }

    /**
     * Validate that a user can take a shift on a given date (rest + weekly hours).
     */
    public function validateConstraints(User $user, Carbon $date, SecurityShift $shift): bool
    {
        // Rest period: measured from previous shift END to new shift START
        $newStart = $this->combineDateTime($date, $shift->start_time);
        $newEnd = $shift->is_overnight
            ? $newStart->copy()->addDay()->setTimeFromTimeString($shift->end_time)
            : $this->combineDateTime($date, $shift->end_time);

        $previous = SecuritySchedule::query()
            ->where('security_user_id', $user->id)
            ->where('status', '!=', 'cancelled')
            ->where('assignment_date', '<=', $date->toDateString())
            ->with('shift:id,start_time,end_time,is_overnight,duration_hours')
            ->get()
            ->map(function ($s) {
                $start = $this->combineDateTime($s->assignment_date, $s->shift->start_time);
                $end = $s->shift->is_overnight
                    ? $start->copy()->addDay()->setTimeFromTimeString($s->shift->end_time)
                    : $this->combineDateTime($s->assignment_date, $s->shift->end_time);
                return ['start' => $start, 'end' => $end];
            })
            ->filter(fn ($p) => $p['end']->lte($newStart))
            ->sortByDesc(fn ($p) => $p['end']->timestamp)
            ->first();

        if ($previous) {
            $hoursSinceEnd = $previous['end']->diffInHours($newStart);
            if ($hoursSinceEnd < 12) {
                return false;
            }
        }

        // Weekly hours limit
        $weekStart = $date->copy()->startOfWeek();
        $weekEnd = $date->copy()->endOfWeek();

        $weeklyHours = (float) SecuritySchedule::query()
            ->where('security_user_id', $user->id)
            ->whereBetween('assignment_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->where('status', '!=', 'cancelled')
            ->join('security_shifts', 'security_schedules.security_shift_id', '=', 'security_shifts.id')
            ->sum('security_shifts.duration_hours');

        if (($weeklyHours + (float) $shift->duration_hours) > 60) {
            return false;
        }

        return true;
    }

    /**
     * Apply a precomputed rotation sequence to existing schedules.
     * Returns number of schedules mutated.
     */
    public function applyToSchedules(RotationGroup $group, array $sequence, bool $maintainCoverage = true): int
    {
        $applied = 0;

        foreach ($sequence as $day) {
            $date = Carbon::parse($day['date']);

            $existing = SecuritySchedule::query()
                ->where('security_post_id', $group->security_post_id)
                ->where('security_shift_id', $group->security_shift_id)
                ->whereDate('assignment_date', $date)
                ->orderBy('id')
                ->get();

            foreach ($day['assigned_members'] as $index => $userId) {
                if (isset($existing[$index])) {
                    $schedule = $existing[$index];
                    if ((int) $schedule->security_user_id === (int) $userId) {
                        continue;
                    }

                    $rotationData = $schedule->rotation_data ?? [];
                    $rotationData['rotated_from'] = $schedule->security_user_id;
                    $rotationData['rotated_at'] = now()->toIso8601String();
                    $rotationData['rotation_group'] = $group->id;
                    $rotationData['rotation_group_name'] = $group->name;
                    $rotationData['rotation_history'][] = [
                        'id' => uniqid('rot_'),
                        'date' => now()->toIso8601String(),
                        'from_user_id' => $schedule->security_user_id,
                        'to_user_id' => $userId,
                        'reason' => 'group_rotation',
                        'by' => auth()->id(),
                    ];

                    $schedule->update([
                        'security_user_id' => $userId,
                        'rotated_from_user_id' => $schedule->security_user_id,
                        'is_rotated' => true,
                        'rotated_at' => now(),
                        'rotation_data' => $rotationData,
                        'rotation_swap_count' => ((int) ($schedule->rotation_swap_count ?? 0)) + 1,
                    ]);
                    $applied++;
                }
            }

            if ($maintainCoverage) {
                $this->ensureCoverage($group, $date);
            }
        }

        return $applied;
    }

    /**
     * Build a candidate rotation sequence (preview).
     */
    public function buildSequence(
        RotationGroup $group,
        Carbon $startDate,
        Carbon $endDate,
        string $rotationType,
        bool $respectPreferences
    ): array {
        $members = $group->members()->with('user')->get();
        if ($respectPreferences) {
            $members = $members->sortByDesc('preference_score');
        }

        $memberIds = $members->pluck('user_id')->map(fn ($v) => (int) $v)->all();
        $memberCount = count($memberIds);
        if ($memberCount === 0) {
            return ['sequence' => [], 'affected_count' => 0];
        }

        $intervalDays = (int) ($group->rotation_config['interval_days'] ?? 7);
        $pattern = $group->rotation_config['sequence'] ?? ['A', 'B', 'C', 'D'];

        $sequence = [];
        $affected = 0;
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $assigned = match ($rotationType) {
                'partial' => array_slice($memberIds, 0, (int) ceil($memberCount / 2)),
                'staggered' => $this->membersForPattern($memberIds, $pattern),
                'full_swap' => $this->fullSwapMembers($memberIds, $startDate, $current),
                default => $memberIds,
            };

            $sequence[] = [
                'date' => $current->toDateString(),
                'assigned_members' => $assigned,
                'member_count' => count($assigned),
            ];
            $affected += count($assigned);
            $current->addDays($intervalDays);
        }

        return [
            'sequence' => $sequence,
            'affected_count' => $affected,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'rotation_type' => $rotationType,
            'total_days' => $startDate->diffInDays($endDate) + 1,
            'rotation_days' => $intervalDays,
        ];
    }

    // ---------- helpers ----------

    private function ensureCoverage(RotationGroup $group, Carbon $date): void
    {
        $post = $group->post;
        if (!$post) {
            return;
        }

        $required = (int) min($post->max_personnel, $group->shift->required_personnel ?? 1);
        $assignedIds = SecuritySchedule::query()
            ->where('security_post_id', $group->security_post_id)
            ->whereDate('assignment_date', $date)
            ->pluck('security_user_id')
            ->all();

        $needed = max(0, $required - count($assignedIds));
        if ($needed === 0) {
            return;
        }

        $available = $group->members()
            ->whereNotIn('user_id', $assignedIds)
            ->orderByDesc('preference_score')
            ->limit($needed)
            ->get();

        foreach ($available as $member) {
            SecuritySchedule::create([
                'security_post_id' => $group->security_post_id,
                'security_shift_id' => $group->security_shift_id,
                'security_user_id' => $member->user_id,
                'assignment_date' => $date->toDateString(),
                'assigned_by' => auth()->id(),
                'status' => 'scheduled',
                'rotation_group_id' => $group->id,
                'notes' => 'Auto-created to maintain coverage',
                'rotation_data' => [
                    'created_for_coverage' => true,
                    'rotation_group' => $group->id,
                ],
            ]);
        }
    }

    private function membersForPattern(array $memberIds, array $pattern): array
    {
        $half = (int) ceil(count($memberIds) / 2);
        $letter = $pattern[0] ?? 'A';

        return match ($letter) {
            'A' => array_slice($memberIds, 0, $half),
            'B' => array_slice($memberIds, $half),
            'C' => array_slice($memberIds, 0, (int) ceil($half / 2)),
            'D' => array_slice($memberIds, (int) ceil($half / 2)),
            default => $memberIds,
        };
    }

    private function fullSwapMembers(array $memberIds, Carbon $start, Carbon $current): array
    {
        $half = (int) ceil(count($memberIds) / 2);
        $weekIndex = (int) floor($start->diffInDays($current) / 7) % 2;
        return $weekIndex === 0
            ? array_slice($memberIds, 0, $half)
            : array_slice($memberIds, $half);
    }

    private function combineDateTime(Carbon $date, string $time): Carbon
    {
        return $date->copy()->setTimeFromTimeString($time);
    }
}