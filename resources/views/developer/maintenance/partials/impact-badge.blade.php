@if($impact === 'low')
    <span class="px-2 py-1 text-xs rounded-full impact-low">
        Low Impact
    </span>
@elseif($impact === 'medium')
    <span class="px-2 py-1 text-xs rounded-full impact-medium">
        Medium Impact
    </span>
@elseif($impact === 'high')
    <span class="px-2 py-1 text-xs rounded-full impact-high">
        High Impact
    </span>
@elseif($impact === 'critical')
    <span class="px-2 py-1 text-xs rounded-full impact-critical">
        Critical Impact
    </span>
@endif