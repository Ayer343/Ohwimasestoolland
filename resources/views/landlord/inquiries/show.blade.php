{{-- resources/views/landlord/inquiries/show.blade.php --}}
@extends('layouts.landlord')

@section('title', 'Inquiry Details')

@php
    use App\Models\UnitInquiry;

    $statusColors = [
        UnitInquiry::STATUS_NEW     => 'primary',
        UnitInquiry::STATUS_READ    => 'info',
        UnitInquiry::STATUS_REPLIED => 'success',
        UnitInquiry::STATUS_CLOSED  => 'secondary',
        UnitInquiry::STATUS_SPAM    => 'danger',
    ];
    $statusColor = $statusColors[$inquiry->status] ?? 'secondary';
    $statusLabel = ucfirst(str_replace('_', ' ', $inquiry->status ?? 'unknown'));

    $unit        = $inquiry->unit;
    $property    = $unit?->property;
    $guestName   = $inquiry->user->name ?? $inquiry->name ?? 'Anonymous';
    $email       = $inquiry->user->email ?? $inquiry->email ?? null;
    $phone       = $inquiry->user->phone ?? $inquiry->phone ?? null;

    $isSpam      = $inquiry->status === UnitInquiry::STATUS_SPAM;
    $canReply    = !$isSpam;
    $canClose    = !in_array($inquiry->status, [UnitInquiry::STATUS_CLOSED, UnitInquiry::STATUS_SPAM], true);
    $canSpam     = !$isSpam;
    $canReopen   = in_array($inquiry->status, [UnitInquiry::STATUS_CLOSED, UnitInquiry::STATUS_SPAM], true);

    $relatedInquiries = $relatedInquiries ?? collect();
@endphp

@section('content')
<div class="max-w-3xl mx-auto py-8 px-4 space-y-4">

    {{-- ==================== HEADER ==================== --}}
    <div class="flex items-center justify-between flex-wrap gap-2">
        <a href="{{ route('landlord.inquiries.index') }}"
           class="inline-flex items-center text-sm"
           style="color: var(--text-secondary);">
            <i class="fas fa-arrow-left mr-2"></i> Back to inquiries
        </a>

        <div class="flex items-center gap-2 flex-wrap">
            <span class="px-3 py-1 rounded-full text-xs font-medium"
                  style="background: rgba(var(--{{ $statusColor }}-rgb), 0.15);
                         color: var(--{{ $statusColor }});">
                @if($inquiry->status === UnitInquiry::STATUS_NEW)<i class="fas fa-envelope mr-1"></i>
                @elseif($inquiry->status === UnitInquiry::STATUS_READ)<i class="fas fa-envelope-open mr-1"></i>
                @elseif($inquiry->status === UnitInquiry::STATUS_REPLIED)<i class="fas fa-reply mr-1"></i>
                @elseif($inquiry->status === UnitInquiry::STATUS_CLOSED)<i class="fas fa-check-circle mr-1"></i>
                @elseif($inquiry->status === UnitInquiry::STATUS_SPAM)<i class="fas fa-ban mr-1"></i>
                @endif
                {{ $statusLabel }}
            </span>

            @if($inquiry->replied_at)
                <span class="text-xs px-2 py-1 rounded-full"
                      style="background: rgba(var(--success-rgb), 0.1); color: var(--success);">
                    <i class="fas fa-reply mr-1"></i>
                    Replied {{ optional($inquiry->replied_at)->diffForHumans() }}
                </span>
            @endif
        </div>
    </div>

    {{-- ==================== FLASH ==================== --}}
    @if(session('success'))
        <div class="p-4 rounded-lg flex items-start"
             style="background: rgba(var(--success-rgb), 0.1); border-left: 4px solid var(--success);">
            <i class="fas fa-check-circle mr-2 mt-0.5" style="color: var(--success);"></i>
            <span class="text-sm" style="color: var(--text-primary);">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 rounded-lg flex items-start"
             style="background: rgba(var(--danger-rgb), 0.1); border-left: 4px solid var(--danger);">
            <i class="fas fa-exclamation-circle mr-2 mt-0.5" style="color: var(--danger);"></i>
            <span class="text-sm" style="color: var(--text-primary);">{{ session('error') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-lg"
             style="background: rgba(var(--danger-rgb), 0.1); border-left: 4px solid var(--danger);">
            <p class="font-semibold text-sm mb-1" style="color: var(--danger);">
                <i class="fas fa-exclamation-triangle mr-2"></i>Please fix the following:
            </p>
            <ul class="list-disc list-inside text-sm" style="color: var(--text-primary);">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ==================== GUEST CARD ==================== --}}
    <div class="card p-6">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0"
                 style="background: rgba(var(--primary-rgb), 0.1);">
                <i class="fas fa-user" style="color: var(--primary);"></i>
            </div>
            <div class="flex-1 min-w-0">
                <h2 class="font-semibold text-lg truncate" style="color: var(--text-primary);">
                    {{ $guestName }}
                </h2>

                <div class="mt-1 space-y-1 text-sm" style="color: var(--text-secondary);">
                    @if($email)
                        <p>
                            <i class="fas fa-envelope mr-2" style="color: var(--primary);"></i>
                            <a href="mailto:{{ $email }}" class="hover:underline" style="color: inherit;">
                                {{ $email }}
                            </a>
                        </p>
                    @endif
                    @if($phone)
                        <p>
                            <i class="fas fa-phone mr-2" style="color: var(--primary);"></i>
                            <a href="tel:{{ $phone }}" class="hover:underline" style="color: inherit;">
                                {{ $phone }}
                            </a>
                        </p>
                    @endif
                    <p>
                        <i class="fas fa-clock mr-2" style="color: var(--primary);"></i>
                        {{ optional($inquiry->created_at)->format('M d, Y · g:i A') }}
                        <span style="opacity: 0.7;">
                            ({{ optional($inquiry->created_at)->diffForHumans() }})
                        </span>
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

    {{-- ==================== PROPERTY / UNIT ==================== --}}
    <div class="card p-6">
        <h3 class="text-xs uppercase tracking-wide mb-3" style="color: var(--text-secondary);">
            About
        </h3>
        <p class="font-medium" style="color: var(--text-primary);">
            <i class="fas fa-building mr-2" style="color: var(--primary);"></i>
            {{ $property?->property_name ?? 'Property' }}
        </p>
        @if($unit)
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                <i class="fas fa-home mr-2" style="color: var(--primary);"></i>
                Unit: {{ $unit->unit_name ?? $unit->unit_number }}
                @if($unit->monthly_rent)
                    · {{ $unit->rent_formatted ?? '₵' . number_format($unit->monthly_rent, 2) }}/month
                @endif
            </p>
        @endif
    </div>

    {{-- ==================== MESSAGE ==================== --}}
    <div class="card p-6">
        <h3 class="text-xs uppercase tracking-wide mb-3" style="color: var(--text-secondary);">
            Message
        </h3>
        @if(!empty($inquiry->message))
            <p class="whitespace-pre-line text-sm leading-relaxed" style="color: var(--text-primary);">
                {{ $inquiry->message }}
            </p>
        @else
            <p class="text-sm italic" style="color: var(--text-secondary);">
                No message was included with this inquiry.
            </p>
        @endif
    </div>

    {{-- ==================== EXISTING REPLY ==================== --}}
    @if($inquiry->reply_message)
        <div class="card p-6" style="border-left: 4px solid var(--success);">
            <h3 class="text-xs uppercase tracking-wide mb-3 flex items-center justify-between flex-wrap gap-2"
                style="color: var(--text-secondary);">
                <span>
                    <i class="fas fa-reply mr-1" style="color: var(--success);"></i>
                    Your Reply
                </span>
                <span class="text-[10px]">
                    @if($inquiry->replied_at)
                        {{ optional($inquiry->replied_at)->format('M d, Y · g:i A') }}
                    @endif
                    @if($inquiry->replied_by_role)
                        · by {{ ucfirst($inquiry->replied_by_role) }}
                    @endif
                </span>
            </h3>
            <p class="whitespace-pre-line text-sm leading-relaxed" style="color: var(--text-primary);">
                {{ $inquiry->reply_message }}
            </p>
        </div>
    @endif

    {{-- ==================== REPLY FORM ==================== --}}
    @if($canReply)
        <div class="card p-6">
            <h3 class="text-xs uppercase tracking-wide mb-3" style="color: var(--text-secondary);">
                <i class="fas fa-paper-plane mr-1" style="color: var(--primary);"></i>
                {{ $inquiry->reply_message ? 'Send another reply' : 'Reply to guest' }}
            </h3>

            <form method="POST" action="{{ route('landlord.inquiries.reply', $inquiry->uuid) }}">
                @csrf

                <textarea name="reply_message"
                          rows="4"
                          required
                          minlength="5"
                          maxlength="5000"
                          class="w-full px-3 py-2 rounded-lg border mb-3"
                          style="background: var(--bg-primary);
                                 color: var(--text-primary);
                                 border-color: var(--border-color);"
                          placeholder="Write a reply to the guest…">{{ old('reply_message') }}</textarea>

                @if($email)
                    <label class="flex items-center gap-2 cursor-pointer text-sm mb-3"
                           style="color: var(--text-primary);">
                        <input type="hidden" name="also_email" value="0">
                        <input type="checkbox" name="also_email" value="1"
                               @checked(old('also_email')) class="w-4 h-4">
                        Also email this reply to <strong>{{ $email }}</strong>
                    </label>
                @endif

                <div class="flex items-center gap-2 justify-end flex-wrap">
                    @if($email)
                        <a href="mailto:{{ $email }}?subject={{ urlencode('Re: Your inquiry about ' . ($property?->property_name ?? 'our property')) }}"
                           class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                           style="background: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                            <i class="fas fa-envelope mr-2"></i> Open in email
                        </a>
                    @endif

                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                            style="background: var(--primary); color: white;">
                        <i class="fas fa-paper-plane mr-2"></i> Send Reply
                    </button>
                </div>
            </form>
        </div>
    @else
        {{-- Spam guard --}}
        <div class="card p-6"
             style="background: rgba(var(--danger-rgb), 0.05); border-left: 4px solid var(--danger);">
            <p class="text-sm mb-3" style="color: var(--text-primary);">
                <i class="fas fa-ban mr-2" style="color: var(--danger);"></i>
                This inquiry is flagged as spam. Unmark it before replying.
            </p>
            <form method="POST"
                  action="{{ route('landlord.inquiries.status', $inquiry->uuid) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ UnitInquiry::STATUS_NEW }}">
                <button type="submit"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium inline-flex items-center"
                        style="background: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                    <i class="fas fa-undo mr-1"></i> Unmark as spam
                </button>
            </form>
        </div>
    @endif

    {{-- ==================== CONTACT QUICK ACTIONS ==================== --}}
    @if($phone)
        <div class="card p-6">
            <h3 class="text-xs uppercase tracking-wide mb-3" style="color: var(--text-secondary);">
                Quick contact
            </h3>
            <div class="flex flex-wrap gap-2">
                <a href="tel:{{ $phone }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background: rgba(var(--primary-rgb), 0.08); color: var(--primary);">
                    <i class="fas fa-phone mr-2"></i> Call {{ $phone }}
                </a>
                <a href="https://wa.me/{{ preg_replace('/\D/', '', $phone) }}"
                   target="_blank" rel="noopener"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background: rgba(var(--success-rgb), 0.1); color: var(--success);">
                    <i class="fab fa-whatsapp mr-2"></i> WhatsApp
                </a>
            </div>
        </div>
    @endif

    {{-- ==================== STATUS ACTIONS ==================== --}}
    <div class="card p-6">
        <h3 class="text-xs uppercase tracking-wide mb-3" style="color: var(--text-secondary);">
            <i class="fas fa-cog mr-1" style="color: var(--primary);"></i>
            Actions
        </h3>

        <div class="flex flex-wrap gap-2">

            @if($canClose)
                <form method="POST" action="{{ route('landlord.inquiries.status', $inquiry->uuid) }}"
                      onsubmit="return confirm('Mark this inquiry as closed?');">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ UnitInquiry::STATUS_CLOSED }}">
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                            style="background: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                        <i class="fas fa-check mr-2"></i> Close inquiry
                    </button>
                </form>
            @endif

            @if($canReopen)
                <form method="POST" action="{{ route('landlord.inquiries.status', $inquiry->uuid) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ UnitInquiry::STATUS_NEW }}">
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                            style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-undo mr-2"></i> Reopen
                    </button>
                </form>
            @endif

            @if($canSpam)
                <form method="POST" action="{{ route('landlord.inquiries.status', $inquiry->uuid) }}"
                      onsubmit="return confirm('Mark this inquiry as spam? You can unmark it later.');">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ UnitInquiry::STATUS_SPAM }}">
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                            style="background: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-ban mr-2"></i> Mark as spam
                    </button>
                </form>
            @endif

            <form method="POST" action="{{ route('landlord.inquiries.destroy', $inquiry->uuid) }}"
                  onsubmit="return confirm('Permanently delete this inquiry?\n\nThis cannot be undone.');"
                  class="ml-auto">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                        style="background: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                    <i class="fas fa-trash-alt mr-2"></i> Delete
                </button>
            </form>
        </div>
    </div>

    {{-- ==================== RELATED INQUIRIES ==================== --}}
    @if($relatedInquiries->isNotEmpty())
        <div class="card p-6">
            <h3 class="text-xs uppercase tracking-wide mb-3" style="color: var(--text-secondary);">
                <i class="fas fa-layer-group mr-1" style="color: var(--info);"></i>
                Other inquiries about this unit ({{ $relatedInquiries->count() }})
            </h3>
            <div class="space-y-2">
                @foreach($relatedInquiries as $rel)
                    @php
                        $relColor = $statusColors[$rel->status] ?? 'secondary';
                    @endphp
                    <a href="{{ route('landlord.inquiries.show', $rel->uuid) }}"
                       class="flex items-center justify-between p-3 rounded-lg hover:shadow-sm transition-all"
                       style="background: var(--bg-secondary);">
                        <div class="min-w-0 flex-1">
                            <span class="font-medium text-sm truncate"
                                  style="color: var(--text-primary);">
                                {{ $rel->name ?? 'Anonymous' }}
                            </span>
                            <span class="text-xs ml-2" style="color: var(--text-secondary);">
                                · {{ optional($rel->created_at)->diffForHumans() }}
                            </span>
                        </div>
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium flex-shrink-0"
                              style="background: rgba(var(--{{ $relColor }}-rgb), 0.15);
                                     color: var(--{{ $relColor }});">
                            {{ ucfirst($rel->status) }}
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ==================== DELIVERY CHANNELS ==================== --}}
    @if($inquiry->channels_sent || $inquiry->channels_failed)
        <div class="card p-6">
            <h3 class="text-xs uppercase tracking-wide mb-3" style="color: var(--text-secondary);">
                <i class="fas fa-paper-plane mr-1" style="color: var(--primary);"></i>
                Delivery
            </h3>
            @if($inquiry->channels_sent)
                <p class="text-sm mb-1 flex items-center flex-wrap gap-1"
                   style="color: var(--text-primary);">
                    <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                    Delivered via:
                    @foreach((array) $inquiry->channels_sent as $ch)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                              style="background: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            {{ $ch }}
                        </span>
                    @endforeach
                </p>
            @endif
            @if($inquiry->channels_failed)
                <p class="text-sm flex items-center flex-wrap gap-1"
                   style="color: var(--text-primary);">
                    <i class="fas fa-times-circle mr-1" style="color: var(--danger);"></i>
                    Failed on:
                    @foreach((array) $inquiry->channels_failed as $ch)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
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