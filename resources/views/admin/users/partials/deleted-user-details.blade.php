<div class="space-y-4">
    <!-- User Basic Info -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <h4 class="font-semibold mb-2" style="color: var(--text-primary);">Basic Information</h4>
            <div class="space-y-2">
                <div>
                    <span class="text-sm" style="color: var(--text-secondary);">Name:</span>
                    <p style="color: var(--text-primary);">{{ $user->name }}</p>
                </div>
                <div>
                    <span class="text-sm" style="color: var(--text-secondary);">Email:</span>
                    <p style="color: var(--text-primary);">{{ $user->email }}</p>
                </div>
                <div>
                    <span class="text-sm" style="color: var(--text-secondary);">Phone:</span>
                    <p style="color: var(--text-primary);">{{ $user->phone ?? 'Not set' }}</p>
                </div>
            </div>
        </div>
        
        <div>
            <h4 class="font-semibold mb-2" style="color: var(--text-primary);">Account Details</h4>
            <div class="space-y-2">
                <div>
                    <span class="text-sm" style="color: var(--text-secondary);">User Type:</span>
                    <p style="color: var(--text-primary);">{{ $user->type_name }}</p>
                </div>
                <div>
                    <span class="text-sm" style="color: var(--text-secondary);">Status:</span>
                    <p style="color: var(--text-primary);">{{ $user->status }}</p>
                </div>
                <div>
                    <span class="text-sm" style="color: var(--text-secondary);">Created:</span>
                    <p style="color: var(--text-primary);">{{ $user->created_at->format('M j, Y g:i A') }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Deletion Information -->
    <div>
        <h4 class="font-semibold mb-2" style="color: var(--text-primary);">Deletion Information</h4>
        <div class="space-y-2">
            <div>
                <span class="text-sm" style="color: var(--text-secondary);">Deleted At:</span>
                <p style="color: var(--text-primary);">{{ $user->deleted_at->format('M j, Y g:i A') }}</p>
            </div>
            <div>
                <span class="text-sm" style="color: var(--text-secondary);">Days in Trash:</span>
                <p style="color: var(--text-primary);">{{ $user->deleted_at->diffInDays(now()) }} days</p>
            </div>
            @php
                $deletionInfo = $user->metadata['deletion_info'] ?? null;
                $deletedBy = $user->deleted_by_user ?? null;
            @endphp
            @if($deletedBy)
            <div>
                <span class="text-sm" style="color: var(--text-secondary);">Deleted By:</span>
                <p style="color: var(--text-primary);">{{ $deletedBy->name }}</p>
            </div>
            @endif
            @if($deletionInfo && isset($deletionInfo['deletion_reason']))
            <div>
                <span class="text-sm" style="color: var(--text-secondary);">Deletion Reason:</span>
                <p style="color: var(--text-primary);" class="mt-1 p-2 rounded bg-gray-100">{{ $deletionInfo['deletion_reason'] }}</p>
            </div>
            @endif
        </div>
    </div>

    <!-- Critical Relations Warning -->
    @php
        $criticalRelations = app(App\Http\Controllers\UserController::class)->checkCriticalRelations($user);
    @endphp
    @if(!empty($criticalRelations))
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        <strong>Warning:</strong> This user has critical associated data that prevents permanent deletion:
        <ul class="mt-1 mb-0 pl-4">
            @foreach($criticalRelations as $relation)
                <li>{{ $relation }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Original Data Snapshot -->
    @if($deletionInfo && isset($deletionInfo['original_data']))
    <div>
        <h4 class="font-semibold mb-2" style="color: var(--text-primary);">Original Data Snapshot</h4>
        <div class="text-sm bg-gray-50 p-3 rounded border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
            <pre class="whitespace-pre-wrap text-xs">{{ json_encode($deletionInfo['original_data'], JSON_PRETTY_PRINT) }}</pre>
        </div>
    </div>
    @endif
</div>