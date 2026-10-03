@if($status === 'draft')
    <span class="status-badge status-draft">
        <i class="fas fa-pencil-alt mr-1"></i> Draft
    </span>
@elseif($status === 'scheduled')
    <span class="status-badge status-scheduled">
        <i class="fas fa-clock mr-1"></i> Scheduled
    </span>
@elseif($status === 'in_progress')
    <span class="status-badge status-in_progress">
        <i class="fas fa-play mr-1"></i> In Progress
    </span>
@elseif($status === 'completed')
    <span class="status-badge status-completed">
        <i class="fas fa-check-circle mr-1"></i> Completed
    </span>
@elseif($status === 'cancelled')
    <span class="status-badge status-cancelled">
        <i class="fas fa-times-circle mr-1"></i> Cancelled
    </span>
@endif