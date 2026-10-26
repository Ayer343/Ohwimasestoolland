{{-- resources/views/admin/inquiries/show.blade.php --}}
@extends('layouts.app')

@section('title', 'Inquiry Details — Admin')

@php
    use App\Models\UnitInquiry;

    $statusColor = match ($inquiry->status) {
        UnitInquiry::STATUS_NEW     => 'primary',
        UnitInquiry::STATUS_READ    => 'info',
        UnitInquiry::STATUS_REPLIED => 'success',
        UnitInquiry::STATUS_CLOSED  => 'secondary',
        UnitInquiry::STATUS_SPAM    => 'danger',
        default                     => 'secondary',
    };
    $statusLabel = ucfirst(str_replace('_', ' ', $inquiry->status ?? 'unknown'));

    $unit     = $inquiry->unit;
    $property = $unit?->property;
    $landlord = $inquiry->landlord;

    $canReply   = $inquiry->status !== UnitInquiry::STATUS_SPAM;
    $canEmail   = !empty($inquiry->email);
    $canClose   = $inquiry->status !== UnitInquiry::STATUS_CLOSED;
    $canSpam    = $inquiry->status !== UnitInquiry::STATUS_SPAM;
@endphp

@section('content')
<div class="max-w-4xl mx-auto py-8 px-4 space-y-4">

    {{-- BACK + STATUS --}}
    <div class="flex items-center justify-between flex-wrap gap-2">
        <a href="{{ route('admin.inquiries.index') }}"
           class="inline-flex items-center text-sm" style="color: var(--text-secondary);">
            <i class="fas fa-arrow-left mr-2"></i> Back to inquiries
        </a>
        <span class="px-3 py-1 rounded-full text-xs font-medium"
              style="background: rgba(var(--{{ $statusColor }}-rgb), 0.15); color: var(--{{ $statusColor }});">
            {{ $statusLabel }}
        </span>
    </div>

    {{-- FLASH --}}
    @if(session('success'))
        <div class="p-4 rounded-lg flex items-center"
             style="background-color: rgba(var(--success-rgb), 0.1); border-left: 4px solid var(--success);">
            <i class="fas fa-check-circle mr-3" style="color: var(--success);"></i>
            <span style="color: var(--text-primary);">{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 rounded-lg flex items-center"
             style="background-color: rgba(var(--danger-rgb), 0.1); border-left: 4px solid var(--danger);">
            <i class="fas fa-times-circle mr-3" style="color: var(--danger);"></i>
            <span style="color: var(--text-primary);">{{ session('error') }}</span>
        </div>
    @endif
    @if($errors->any())
        <div class="p-4 rounded-lg"
             style="background-color: rgba(var(--danger-rgb), 0.1); border-left: 4px solid var(--danger);">
            <ul class="text-sm list-disc pl-6" style="color: var(--text-primary);">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- GUEST + CONTACT --}}
    <div class="card p-6">
        <h3 class="text-xs uppercase tracking-wide mb-3" style="color: var(--text-secondary);">Guest</h3>
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0"
                 style="background: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                <i class="fas fa-user"></i>
            </div>
            <div class="flex-1 min-w-0">
                <h2 class="font-semibold text-lg truncate" style="color: var(--text-primary);">
                    {{ $inquiry->name ?? 'Anonymous' }}
                </h2>
                <div class="mt-1 space-y-1 text-sm" style="color: var(--text-secondary);">
                    @if($inquiry->phone)
                        <p><i class="fas fa-phone mr-2" style="color: var(--primary);"></i>{{ $inquiry->phone }}</p>
                    @endif
                    @if($inquiry->email)
                        <p><i class="fas fa-envelope mr-2" style="color: var(--primary);"></i>{{ $inquiry->email }}</p>
                    @endif
                    <p>
                        <i class="fas fa-clock mr-2" style="color: var(--primary);"></i>
                        {{ optional($inquiry->created_at)->format('M d, Y · g:i A') }}
                        <span class="opacity-70">({{ optional($inquiry->created_at)->diffForHumans() }})</span>
                    </p>
                    @if($inquiry->user)
                        <p>
                            <i class="fas fa-id-badge mr-2" style="color: var(--info);"></i>
                            Registered user: {{ $inquiry->user->name }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- UNIT / LANDLORD --}}
    <div class="card p-6">
        <h3 class="text-xs uppercase tracking-wide mb-3" style="color: var(--text-secondary);">Property</h3>
        <p class="font-medium" style="color: var(--text-primary);">
            <i class="fas fa-building mr-2" style="color: var(--primary);"></i>
            {{ $property?->property_name ?? 'Unknown property' }}
        </p>
        @if($unit)
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Unit: {{ $unit->unit_name ?? $unit->unit_number }}
                @if($unit->monthly_rent)
                    · {{ $unit->rent_formatted ?? '₵' . number_format($unit->monthly_rent, 2) }}/month
                @endif
            </p>
        @endif
        @if($landlord)
            <p class="text-sm mt-2" style="color: var(--text-secondary);">
                <i class="fas fa-user-tie mr-2" style="color: var(--primary);"></i>
                Landlord: <strong style="color: var(--text-primary);">{{ $landlord->name }}</strong>
                @if($landlord->phone ?? $landlord->email ?? null)
                    <span class="ml-2 text-xs">
                        ({{ $landlord->phone ?? $landlord->email }})
                    </span>
                @endif
            </p>
        @endif
    </div>

    {{-- MESSAGE --}}
    <div class="card p-6">
        <h3 class="text-xs uppercase tracking-wide mb-3" style="color: var(--text-secondary);">Message</h3>
        @if(!empty($inquiry->message))
            <p class="whitespace-pre-line text-sm leading-relaxed" style="color: var(--text-primary);">
                {{ $inquiry->message }}
            </p>
        @else
            <p class="text-sm italic" style="color: var(--text-secondary);">No message was included.</p>
        @endif
    </div>

    {{-- EXISTING REPLY --}}
    @if($inquiry->reply_message)
        <div class="card p-6" style="border-left: 4px solid var(--success);">
            <h3 class="text-xs uppercase tracking-wide mb-3" style="color: var(--text-secondary);">
                Reply
                @if($inquiry->replied_at)
                    · {{ optional($inquiry->replied_at)->format('M d, Y · g:i A') }}
                @endif
                @if($inquiry->replied_by_role)
                    · by {{ ucfirst($inquiry->replied_by_role) }}
                @endif
            </h3>
            <p class="whitespace-pre-line text-sm leading-relaxed" style="color: var(--text-primary);">
                {{ $inquiry->reply_message }}
            </p>
        </div>
    @endif

    {{-- REPLY FORM --}}
    @if($canReply)
        <div class="card p-6">
            <h3 class="text-xs uppercase tracking-wide mb-3" style="color: var(--text-secondary);">
                {{ $inquiry->reply_message ? 'Send another reply' : 'Reply to guest' }}
            </h3>
            <form method="POST" action="{{ route('admin.inquiries.reply', $inquiry->uuid) }}">
                @csrf
                <textarea name="reply_message" rows="4" required minlength="5" maxlength="5000"
                          class="form-input w-full px-3 py-2 rounded-lg mb-3"
                          placeholder="Write a reply to the guest…">{{ old('reply_message') }}</textarea>

                @if($canEmail)
                    <label class="flex items-center gap-2 cursor-pointer text-sm mb-3"
                           style="color: var(--text-primary);">
                        <input type="hidden" name="also_email" value="0">
                        <input type="checkbox" name="also_email" value="1"
                               @checked(old('also_email')) class="w-4 h-4">
                        Also email this reply to <strong>{{ $inquiry->email }}</strong>
                    </label>
                @endif

                <div class="flex gap-2 justify-end">
                    <button type="submit"
                            class="btn-primary px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center">
                        <i class="fas fa-paper-plane mr-2"></i> Send Reply
                    </button>
                </div>
            </form>
        </div>
    @else
        <div class="card p-6"
             style="background: rgba(var(--danger-rgb), 0.05); border-left: 4px solid var(--danger);">
            <p class="text-sm" style="color: var(--text-primary);">
                <i class="fas fa-ban mr-2" style="color: var(--danger);"></i>
                This inquiry is marked as spam. Unmark it before replying.
            </p>
            <form method="POST"
                  action="{{ route('admin.inquiries.status', $inquiry->uuid) }}"
                  class="mt-3">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ UnitInquiry::STATUS_NEW }}">
                <button type="submit"
                        class="btn-secondary px-3 py-1.5 rounded-lg text-xs font-medium">
                    <i class="fas fa-undo mr-1"></i> Unmark as spam
                </button>
            </form>
        </div>
    @endif

    {{-- ACTIONS --}}
    <div class="card p-6">
        <h3 class="text-xs uppercase tracking-wide mb-3" style="color: var(--text-secondary);">Actions</h3>
        <div class="flex flex-wrap gap-2">
            @if($canClose)
                <form method="POST" action="{{ route('admin.inquiries.status', $inquiry->uuid) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ UnitInquiry::STATUS_CLOSED }}">
                    <button type="submit"
                            class="btn-secondary px-3 py-1.5 rounded-lg text-sm inline-flex items-center">
                        <i class="fas fa-check mr-1"></i> Mark as closed
                    </button>
                </form>
            @endif

            @if($canSpam)
                <form method="POST" action="{{ route('admin.inquiries.status', $inquiry->uuid) }}"
                      onsubmit="return confirm('Mark this inquiry as spam?');">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ UnitInquiry::STATUS_SPAM }}">
                    <button type="submit"
                            class="px-3 py-1.5 rounded-lg text-sm inline-flex items-center"
                            style="background: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-ban mr-1"></i> Mark as spam
                    </button>
                </form>
            @endif

            <form method="POST" action="{{ route('admin.inquiries.destroy', $inquiry->uuid) }}"
                  onsubmit="return confirm('Permanently delete this inquiry?');">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="px-3 py-1.5 rounded-lg text-sm inline-flex items-center"
                        style="background: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                    <i class="fas fa-trash-alt mr-1"></i> Delete
                </button>
            </form>
        </div>
    </div>

    {{-- RELATED INQUIRIES --}}
    @if($relatedInquiries->isNotEmpty())
        <div class="card p-6">
            <h3 class="text-xs uppercase tracking-wide mb-3" style="color: var(--text-secondary);">
                Other inquiries about this unit ({{ $relatedInquiries->count() }})
            </h3>
            <div class="space-y-2">
                @foreach($relatedInquiries as $rel)
                    @php
                        $relColor = match ($rel->status) {
                            UnitInquiry::STATUS_NEW     => 'primary',
                            UnitInquiry::STATUS_READ    => 'info',
                            UnitInquiry::STATUS_REPLIED => 'success',
                            UnitInquiry::STATUS_CLOSED  => 'secondary',
                            UnitInquiry::STATUS_SPAM    => 'danger',
                            default                     => 'secondary',
                        };
                    @endphp
                    <a href="{{ route('admin.inquiries.show', $rel->uuid) }}"
                       class="flex items-center justify-between p-3 rounded-lg hover:shadow-sm"
                       style="background: var(--bg-secondary);">
                        <div class="min-w-0">
                            <span class="font-medium text-sm" style="color: var(--text-primary);">
                                {{ $rel->name ?? 'Anonymous' }}
                            </span>
                            <span class="text-xs ml-2" style="color: var(--text-secondary);">
                                · {{ optional($rel->created_at)->diffForHumans() }}
                            </span>
                        </div>
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                              style="background: rgba(var(--{{ $relColor }}-rgb), 0.15); color: var(--{{ $relColor }});">
                            {{ ucfirst($rel->status) }}
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- CHANNEL DELIVERY --}}
    @if($inquiry->channels_sent || $inquiry->channels_failed)
        <div class="card p-6">
            <h3 class="text-xs uppercase tracking-wide mb-3" style="color: var(--text-secondary);">
                Delivery
            </h3>
            @if($inquiry->channels_sent)
                <p class="text-sm mb-1" style="color: var(--text-primary);">
                    <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                    Delivered via:
                    @foreach((array) $inquiry->channels_sent as $ch)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium mr-1"
                              style="background: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            {{ $ch }}
                        </span>
                    @endforeach
                </p>
            @endif
            @if($inquiry->channels_failed)
                <p class="text-sm" style="color: var(--text-primary);">
                    <i class="fas fa-times-circle mr-1" style="color: var(--danger);"></i>
                    Failed on:
                    @foreach((array) $inquiry->channels_failed as $ch)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium mr-1"
                              style="background: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                            {{ $ch }}
                        </span>
                    @endforeach
                </p>
            @endif
        </div>
    @endif
</div>
@endsection