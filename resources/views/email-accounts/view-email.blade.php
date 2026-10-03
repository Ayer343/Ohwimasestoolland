{{-- email-accounts/view-email.blade.php --}}
@php
    $user         = auth()->user();
    $isDeveloper  = $user->isDeveloper();
    $isAdmin      = $user->isAdmin();
    $isSuperAdmin = $user->isSuperAdmin();
    $isLandlord   = $user->isLandlord();
    $isTenant     = $user->isTenant();

    if ($isSuperAdmin) {
        $routeNamePrefix = 'super-admin.email-accounts';
        $urlPath         = 'super-admin/email-accounts';
        $layout          = 'layouts.app';
        $pageTitle       = 'View Email - Super Admin';
        $backRoute       = route('super-admin.email-accounts.inbox');
    } elseif ($isDeveloper) {
        $routeNamePrefix = 'developer.email-accounts';
        $urlPath         = 'developer/email-accounts';
        $layout          = 'layouts.dev';
        $pageTitle       = 'View Email - Developer';
        $backRoute       = route('developer.email-accounts.inbox');
    } elseif ($isAdmin) {
        $routeNamePrefix = 'admin.email-accounts';
        $urlPath         = 'admin/email-accounts';
        $layout          = 'layouts.app';
        $pageTitle       = 'View Email - Admin';
        $backRoute       = route('admin.email-accounts.inbox');
    } elseif ($isLandlord) {
        $routeNamePrefix = 'landlord.email-accounts';
        $urlPath         = 'landlord/email-accounts';
        $layout          = 'layouts.landlord';
        $pageTitle       = 'View Email';
        $backRoute       = route('landlord.email-accounts.inbox');
    } elseif ($isTenant) {
        $routeNamePrefix = 'tenant.email-accounts';
        $urlPath         = 'tenant/email-accounts';
        $layout          = 'layouts.tenant';
        $pageTitle       = 'View Email';
        $backRoute       = route('tenant.email-accounts.inbox');
    } else {
        $routeNamePrefix = 'email-accounts';
        $urlPath         = 'email-accounts';
        $layout          = 'layouts.app';
        $pageTitle       = 'View Email';
        $backRoute       = route('email-accounts.inbox');
    }

    $emailAccount = $emailAccount ?? null;
    $email        = $email ?? null;

    if (!$emailAccount || !$email) {
        abort(404);
    }

    // Format a date nicely
    $displayDate = $email->received_at ?? $email->sent_at ?? $email->created_at;
    $dateFormatted = $displayDate ? \Carbon\Carbon::parse($displayDate)->format('D, M j, Y \a\t g:i A') : 'Unknown date';

    // Build a recipient list string
    $recipients = [];
    if (!empty($email->to_email)) $recipients[] = $email->to_email;
    if (!empty($email->cc)) {
        foreach (explode(',', $email->cc) as $cc) {
            $cc = trim($cc);
            if ($cc) $recipients[] = 'CC: ' . $cc;
        }
    }

    // Pick the best body to render
    $bodyHtml = $email->html_body ?: null;
    $bodyText = $email->text_body ?: null;

    if (!$bodyHtml && !$bodyText && !empty($email->body)) {
        // Fallback: if we only have a body string, check if it looks like HTML
        if (preg_match('/<[a-z][\s\S]*>/i', $email->body)) {
            $bodyHtml = $email->body;
        } else {
            $bodyText = $email->body;
        }
    }

    $hasBody = !empty($bodyHtml) || !empty($bodyText);
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-wrap justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border-color: var(--primary);">
                        <i class="fas fa-envelope-open-text text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-envelope-open-text mr-2" style="color: var(--primary);"></i>
                        {{ $pageTitle }}
                    </h2>
                    <div class="text-sm flex flex-wrap items-center mt-1 gap-2" style="color: var(--text-secondary);">
                        <span><i class="fas fa-user-circle mr-1"></i> {{ $emailAccount->email }}</span>
                        @if($emailAccount->is_primary)
                            <span class="text-xs px-1.5 py-0.5 rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                <i class="fas fa-star mr-0.5"></i> Primary
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="text-sm mt-2 sm:mt-0 flex items-center gap-2" style="color: var(--text-secondary);">
                <a href="{{ $backRoute }}"
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Inbox
                </a>
                <button onclick="deleteEmail()"
                        class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <i class="fas fa-trash mr-1"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Email Card -->
    <div class="card">
        <div class="p-6">
            <!-- Subject -->
            <div class="pb-4 border-b" style="border-color: var(--border-color);">
                <h1 class="text-2xl font-semibold mb-2 flex items-start" style="color: var(--text-primary);">
                    @if(!$email->is_read)
                        <span class="inline-block w-2 h-2 rounded-full mt-3 mr-2 flex-shrink-0" style="background-color: var(--primary);"></span>
                    @endif
                    <span>{{ $email->subject ?? '(No Subject)' }}</span>
                </h1>
                <div class="flex items-center gap-3 text-sm flex-wrap" style="color: var(--text-secondary);">
                    <span><i class="fas fa-calendar-alt mr-1"></i> {{ $dateFormatted }}</span>
                    @if($email->has_attachments)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            <i class="fas fa-paperclip mr-1"></i> {{ $email->attachment_count }} attachment(s)
                        </span>
                    @endif
                    @if($email->folder)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                            <i class="fas fa-folder mr-1"></i> {{ $email->folder }}
                        </span>
                    @endif
                </div>
            </div>

            <!-- Sender / Recipients -->
            <div class="py-4 border-b space-y-2" style="border-color: var(--border-color);">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-semibold flex-shrink-0"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                        {{ strtoupper(substr($email->from_name ?? $email->from_email ?? 'U', 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center flex-wrap gap-2">
                            <span class="font-semibold" style="color: var(--text-primary);">
                                {{ $email->from_name ?? $email->from_email ?? 'Unknown Sender' }}
                            </span>
                            @if($email->from_email)
                                <span class="text-xs" style="color: var(--text-secondary);">
                                    &lt;{{ $email->from_email }}&gt;
                                </span>
                            @endif
                        </div>
                        <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                            <i class="fas fa-reply mr-1"></i> To:
                            @if(!empty($recipients))
                                {{ implode(', ', $recipients) }}
                            @else
                                {{ $emailAccount->email }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Body -->
            <div class="py-6">
                @if(!$hasBody)
                    <!-- Body is being lazily fetched or wasn't available -->
                    <div class="text-center py-8">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3"
                             style="background-color: rgba(var(--warning-rgb), 0.1);">
                            <i class="fas fa-exclamation-triangle text-xl" style="color: var(--warning);"></i>
                        </div>
                        <p class="text-sm mb-2" style="color: var(--text-secondary);">
                            This email's body hasn't been downloaded yet.
                        </p>
                        <p class="text-xs" style="color: var(--text-secondary);">
                            Bodies are fetched from the server the first time you open an email. This one may have failed to fetch — try reloading.
                        </p>
                        <button onclick="window.location.reload()"
                                class="mt-4 inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium text-white transition-all hover:scale-105"
                                style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                            <i class="fas fa-sync mr-2"></i> Reload
                        </button>
                    </div>
                @elseif($bodyHtml)
                    <!-- HTML body -->
                    <div id="emailBodyHtml" class="email-body-content" style="color: var(--text-primary);">
                        {!! $bodyHtml !!}
                    </div>
                @else
                    <!-- Plain text body -->
                    <pre id="emailBodyText" class="whitespace-pre-wrap font-sans text-sm leading-relaxed" style="color: var(--text-primary); margin: 0;">{{ $bodyText }}</pre>
                @endif
            </div>

            <!-- Attachments List (metadata only for now) -->
            @if($email->has_attachments && $email->attachment_count > 0)
                <div class="pt-4 border-t" style="border-color: var(--border-color);">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fas fa-paperclip" style="color: var(--primary);"></i>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">
                            Attachments ({{ $email->attachment_count }})
                        </span>
                    </div>
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        Attachment download is not yet available. The email indicates this message has
                        {{ $email->attachment_count }} attachment(s).
                    </p>
                </div>
            @endif
        </div>
    </div>

    <!-- Action Bar -->
    <div class="card">
        <div class="p-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                @if($email->from_email)
                    <a href="{{ route($routeNamePrefix . '.compose') }}?to={{ urlencode($email->from_email) }}&subject={{ urlencode('Re: ' . ($email->subject ?? '')) }}"
                       class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium text-white transition-all hover:scale-105"
                       style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                        <i class="fas fa-reply mr-2"></i> Reply
                    </a>
                    <a href="{{ route($routeNamePrefix . '.compose') }}?subject={{ urlencode('Fwd: ' . ($email->subject ?? '')) }}"
                       class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-all hover:scale-105"
                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-share mr-2"></i> Forward
                    </a>
                @endif

                <button onclick="markUnread()"
                        class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-all hover:scale-105"
                        style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-circle mr-2"></i> Mark Unread
                </button>
            </div>

            <button onclick="deleteEmail()"
                    class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-all hover:scale-105"
                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                <i class="fas fa-trash mr-2"></i> Delete
            </button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70" onclick="hideDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="rounded-lg shadow-xl w-full max-w-md" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center p-5 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-trash mr-2" style="color: var(--danger);"></i> Delete Email
                </h3>
                <button type="button" onclick="hideDeleteModal()" class="transition" style="color: var(--text-secondary);">
                    <i class="fas fa-times hover:text-red-500"></i>
                </button>
            </div>
            <div class="p-5">
                <p class="text-sm" style="color: var(--text-secondary);">
                    Are you sure you want to delete this email? This action cannot be undone.
                </p>
            </div>
            <div class="flex justify-end gap-3 p-5 border-t" style="border-color: var(--border-color);">
                <button type="button" onclick="hideDeleteModal()"
                        class="px-4 py-2 rounded-lg font-medium transition"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                    Cancel
                </button>
                <button type="button" onclick="confirmDelete()"
                        class="px-4 py-2 rounded-lg font-medium text-white transition hover:opacity-90"
                        style="background-color: var(--danger);">
                    <i class="fas fa-trash mr-2"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
const baseUrl     = '/{{ $urlPath }}';
const routePrefix = '{{ $routeNamePrefix }}';
const csrfToken   = '{{ csrf_token() }}';
const accountId   = {{ $emailAccount->id }};
const emailId     = {{ $email->id }};

// ========================================== //
// 🛠️ SHARED API FETCH HELPER                 //
// ========================================== //
async function apiFetch(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken,
            ...(options.headers || {}),
        },
        credentials: 'same-origin',
    });

    const text = await response.text();
    let data;
    try {
        const clean = text.replace(/^\uFEFF/, '').replace(/^\uFFFE/, '');
        data = clean ? JSON.parse(clean) : {};
    } catch (e) {
        console.error('Non-JSON response from', url, ':', text.slice(0, 300));
        throw new Error(`Server returned non-JSON response (HTTP ${response.status})`);
    }

    if (!response.ok) {
        throw new Error(data.message || `HTTP ${response.status}`);
    }

    return data;
}

// ========================================== //
// 🗑️ DELETE EMAIL                             //
// ========================================== //
function deleteEmail() {
    document.getElementById('deleteModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function confirmDelete() {
    hideDeleteModal();

    apiFetch(`${baseUrl}/${accountId}/emails/${emailId}`, { method: 'DELETE' })
        .then(data => {
            if (data.success) {
                showNotification('Email deleted successfully', 'success');
                setTimeout(() => {
                    window.location.href = '{{ $backRoute }}';
                }, 1000);
            } else {
                showNotification(data.message || 'Failed to delete email', 'error');
            }
        })
        .catch(error => {
            console.error('Delete error:', error);
            showNotification('Delete failed: ' + error.message, 'error');
        });
}

// ========================================== //
// 📩 MARK AS UNREAD                           //
// ========================================== //
function markUnread() {
    apiFetch(`${baseUrl}/${accountId}/emails/${emailId}/mark-unread`, { method: 'POST' })
        .then(data => {
            if (data.success) {
                showNotification('Email marked as unread', 'success');
                setTimeout(() => {
                    window.location.href = '{{ $backRoute }}';
                }, 1000);
            } else {
                showNotification(data.message || 'Failed to mark as unread', 'error');
            }
        })
        .catch(error => {
            console.error('Mark unread error:', error);
            showNotification('Failed: ' + error.message, 'error');
        });
}

// ========================================== //
// 💬 NOTIFICATIONS                           //
// ========================================== //
function showNotification(message, type = 'success') {
    document.querySelectorAll('.custom-notification').forEach(n => n.remove());

    const colors = {
        success: { bg: '#22c55e', icon: 'fa-check-circle' },
        error:   { bg: '#ef4444', icon: 'fa-exclamation-circle' },
        warning: { bg: '#f59e0b', icon: 'fa-exclamation-triangle' },
        info:    { bg: '#3b82f6', icon: 'fa-info-circle' }
    };
    const color = colors[type] || colors.info;

    const notification = document.createElement('div');
    notification.className = 'custom-notification fixed top-4 right-4 z-[9999] px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 text-white';
    notification.style.backgroundColor = color.bg;
    notification.style.animation = 'slideInRight 0.3s ease-out';
    notification.style.minWidth = '300px';
    notification.style.maxWidth = '500px';
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${color.icon} mr-2 text-lg"></i>
            <span class="text-sm">${escapeHtml(message)}</span>
            <button onclick="this.closest('.custom-notification').remove()" class="ml-3 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ========================================== //
// ⌨️ KEYBOARD SHORTCUTS                      //
// ========================================== //
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        hideDeleteModal();
    }
    // R to reply
    if (e.key === 'r' && !e.ctrlKey && !e.metaKey) {
        if (!e.target.closest('input, textarea, select') && {{ $email->from_email ? 'true' : 'false' }}) {
            e.preventDefault();
            window.location.href = '{{ route($routeNamePrefix . '.compose') }}?to={{ urlencode($email->from_email ?? '') }}&subject={{ urlencode('Re: ' . ($email->subject ?? '')) }}';
        }
    }
});

// ========================================== //
// 🎨 STYLES                                  //
// ========================================== //
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to   { transform: translateX(0);    opacity: 1; }
    }

    /* Make embedded email HTML behave */
    .email-body-content {
        line-height: 1.6;
        overflow-wrap: break-word;
        word-wrap: break-word;
    }
    .email-body-content img {
        max-width: 100%;
        height: auto;
    }
    .email-body-content table {
        max-width: 100%;
        border-collapse: collapse;
    }
    .email-body-content a {
        color: var(--primary);
        text-decoration: underline;
    }
    .email-body-content blockquote {
        border-left: 3px solid var(--border-color);
        padding-left: 12px;
        margin-left: 0;
        color: var(--text-secondary);
    }
    .email-body-content pre {
        background-color: rgba(var(--secondary-rgb), 0.05);
        padding: 12px;
        border-radius: 8px;
        overflow-x: auto;
    }
`;
document.head.appendChild(style);

console.log('✅ View Email Page Loaded');
console.log('Account ID:', accountId);
console.log('Email ID:', emailId);
</script>
@endsection