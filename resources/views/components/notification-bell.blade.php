{{-- resources/views/components/notification-bell.blade.php --}}
@props(['dropdownPosition' => 'right-0'])

@php
    $unreadCount = auth()->check() ? auth()->user()->unreadNotifications()->count() : 0;
@endphp

<div
    class="relative"
    x-data="notificationBell()"
    x-init="init()"
    @click.away="open = false"
>
    <button
        @click="toggle()"
        class="relative p-2 text-gray-600 hover:text-gray-900 focus:outline-none transition-colors duration-200"
        id="notificationBell"
        style="color: var(--text-secondary);"
        aria-label="Notifications"
        :aria-expanded="open.toString()"
    >
        <i class="fas fa-bell text-xl"></i>
        <span
            id="notificationBadge"
            x-show="unreadCount > 0"
            x-cloak
            class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 text-xs flex items-center justify-center notification-alert-pulse"
            x-text="unreadCount > 9 ? '9+' : unreadCount"
        ></span>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-1"
        class="absolute {{ $dropdownPosition }} mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-50 notification-dropdown"
        style="background-color: var(--card-bg); border-color: var(--border-color); box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);"
    >
        <div class="p-4 border-b border-gray-200" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="font-semibold text-gray-900" style="color: var(--text-primary);">
                    <i class="fas fa-bell mr-2"></i>Notifications
                </h3>
                <button
                    x-show="unreadCount > 0"
                    @click="markAllAsRead()"
                    class="text-sm text-blue-600 hover:text-blue-800 transition-colors duration-200"
                    style="color: var(--primary);"
                >
                    Mark all as read
                </button>
            </div>
        </div>

        <div class="max-h-96 overflow-y-auto" id="notificationList">
            <template x-if="loading">
                <div class="p-4 text-center text-gray-500" style="color: var(--text-secondary);">
                    <i class="fas fa-spinner fa-spin mr-2"></i>
                    Loading notifications...
                </div>
            </template>

            <template x-if="!loading && error">
                <div class="p-4 text-center" style="color: var(--danger);">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <span x-text="error"></span>
                    <button
                        @click="loadNotifications()"
                        class="block mx-auto mt-2 text-xs underline"
                        style="color: var(--primary);"
                    >Try again</button>
                </div>
            </template>

            <template x-if="!loading && !error && notifications.length === 0">
                <div class="p-6 text-center">
                    <i class="fas fa-bell-slash text-gray-300 text-3xl mb-3"></i>
                    <p class="text-gray-500 text-sm" style="color: var(--text-secondary);">No notifications</p>
                </div>
            </template>

            <template x-if="!loading && !error && notifications.length > 0">
                <div>
                    <template x-for="n in notifications" :key="n.id">
                        <div class="notification-item" :class="{ 'unread': n.is_unread }" style="border-color: var(--border-color);">
                            <div class="p-3" :style="n.is_unread ? 'background-color: rgba(var(--primary-rgb), 0.1);' : ''">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 mt-1">
                                        <i :class="n.icon || 'fas fa-bell'" style="color: var(--primary);"></i>
                                    </div>
                                    <div class="ml-3 flex-1">
                                        <div class="flex justify-between items-start gap-2">
                                            <p class="font-medium text-sm" style="color: var(--text-primary);" x-text="n.title"></p>
                                            <template x-if="n.category">
                                                <span class="px-2 py-1 text-xs rounded-full whitespace-nowrap"
                                                      style="background-color: var(--bg-secondary); color: var(--text-secondary);"
                                                      x-text="n.category"></span>
                                            </template>
                                        </div>
                                        <p class="text-sm mt-1" style="color: var(--text-secondary);" x-text="n.message"></p>
                                        <div class="flex justify-between items-center mt-2">
                                            <span class="text-xs" style="color: var(--text-secondary); opacity: 0.7;" x-text="n.time_ago || 'Just now'"></span>
                                            <div class="flex space-x-2">
                                                <template x-if="n.is_unread">
                                                    <button
                                                        @click="markAsRead(n.id)"
                                                        class="text-xs transition-colors duration-200"
                                                        style="color: var(--primary);"
                                                    >Mark as read</button>
                                                </template>
                                                <template x-if="n.action_url">
                                                    <a
                                                        :href="n.action_url"
                                                        class="text-xs transition-colors duration-200"
                                                        style="color: var(--primary);"
                                                    >View</a>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <div class="p-3 border-t border-gray-200" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
            <div class="flex justify-between items-center">
                <a
                    href="{{ route('notifications.index') }}"
                    class="text-sm text-blue-600 hover:text-blue-800 transition-colors duration-200"
                    style="color: var(--primary);"
                >
                    <i class="fas fa-list mr-1"></i>
                    View all
                </a>
                <button
                    x-show="unreadCount > 0"
                    @click="clearAll()"
                    class="text-sm text-red-600 hover:text-red-800 transition-colors duration-200"
                    style="color: var(--danger);"
                >
                    <i class="fas fa-trash-alt mr-1"></i>
                    Clear all
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.notification-alert-pulse {
    animation: pulse 2s infinite;
    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
    font-weight: bold;
}
@keyframes pulse {
    0%   { transform: scale(0.95); box-shadow: 0 0 0 0   rgba(239, 68, 68, 0.7); }
    70%  { transform: scale(1);    box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);   }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0   rgba(239, 68, 68, 0);   }
}
.notification-dropdown { transform-origin: top right; }
.notification-item {
    padding: 0;
    border-bottom: 1px solid var(--border-color);
    transition: background-color 0.2s ease;
}
.notification-item:hover { background-color: rgba(var(--primary-rgb), 0.05); }
.notification-item.unread { background-color: rgba(var(--primary-rgb), 0.1); }
.notification-item:last-child { border-bottom: none; }

@media (max-width: 768px) {
    .notification-dropdown { width: 300px !important; right: -50px !important; }
}

.notification-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    min-width: 300px;
    max-width: 400px;
    background: var(--card-bg, #fff);
    color: var(--text-primary, #111);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    border-left: 4px solid var(--primary);
    overflow: hidden;
    animation: slideIn 0.3s ease-out;
    display: none;
}
.notification-toast.show { display: block; }
.notification-toast.success { border-left-color: var(--success); }
.notification-toast.warning { border-left-color: var(--warning); }
.notification-toast.error   { border-left-color: var(--danger); }

.toast-content { display: flex; align-items: flex-start; padding: 16px; }
.toast-icon    { font-size: 20px; margin-right: 12px; margin-top: 2px; }
.toast-success .toast-icon { color: var(--success); }
.toast-warning .toast-icon { color: var(--warning); }
.toast-error   .toast-icon { color: var(--danger); }
.toast-message { flex: 1; }
.toast-title   { font-weight: 600; color: var(--text-primary); margin-bottom: 4px; }
.toast-body    { color: var(--text-secondary); font-size: 14px; line-height: 1.4; }
.toast-close {
    background: none; border: none; font-size: 20px;
    color: var(--text-secondary); cursor: pointer; padding: 0; margin-left: 12px;
}
.toast-close:hover { color: var(--text-primary); }

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to   { transform: translateX(0);    opacity: 1; }
}
</style>

<script>
/**
 * Notification bell — Alpine component.
 * All URLs come from route() helpers so they always match the router.
 */
function notificationBell() {
    return {
        open: false,
        loading: false,
        error: null,
        notifications: [],
        unreadCount: {{ (int) $unreadCount }},
        pollTimer: null,

        // ✅ All endpoints use api.* names which exist in routes
        urls: {
            recent:      @json(route('api.notifications.recent')),
            unreadCount: @json(route('api.notifications.count')),
            markRead:    @json(route('api.notifications.mark-as-read',    ['id' => '__ID__'])),
            markUnread:  @json(route('api.notifications.mark-as-unread',  ['id' => '__ID__'])),
            markAllRead: @json(route('api.notifications.read-all')),
            destroy:     @json(route('api.notifications.destroy',         ['id' => '__ID__'])),
            clearAll:    @json(route('api.notifications.clear-all')),
        },

        init() {
    this.refreshCount();
    this.startPolling();

    window.addEventListener('focus', () => this.refreshCount());
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            this.stopPolling();
        } else {
            this.refreshCount();
            this.startPolling();
        }
    });

    // ✅ Listen for header button clicks
    window.addEventListener('notifications-reload', () => {
        this.loadNotifications();
        this.refreshCount();
    });
},

        withId(template, id) {
            return template.replace('__ID__', encodeURIComponent(id));
        },

        buildHeaders(extra = {}) {
            return Object.assign({
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            }, extra);
        },

        async fetchJson(url, options = {}) {
            const res = await fetch(url, Object.assign({
                credentials: 'same-origin',
                headers: this.buildHeaders(options.headers || {}),
            }, options));

            if (res.status === 401 || res.status === 419) {
                const err = new Error('Session expired. Please refresh the page.');
                err.status = res.status;
                throw err;
            }

            const text = await res.text();
            let json;
            try {
                json = text ? JSON.parse(text) : {};
            } catch (e) {
                throw new Error('Invalid JSON response (HTTP ' + res.status + ')');
            }

            if (!res.ok) {
                throw new Error(json.message || ('HTTP ' + res.status));
            }
            return json;
        },

        async toggle() {
            this.open = !this.open;
            if (this.open) {
                await this.loadNotifications();
            }
        },

        async loadNotifications() {
            this.loading = true;
            this.error = null;
            try {
                const payload = await this.fetchJson(this.urls.recent + '?limit=8');

                // ✅ Accept both shapes
                const list = payload?.data?.notifications
                          ?? payload?.notifications
                          ?? [];
                this.notifications = Array.isArray(list) ? list : [];

                const unread = payload?.meta?.total_unread
                            ?? payload?.total_unread;
                if (typeof unread === 'number') {
                    this.unreadCount = unread;
                }
            } catch (e) {
                console.error('[notifications] load failed:', e);
                this.error = e.message || 'Failed to load notifications';
                this.notifications = [];
            } finally {
                this.loading = false;
            }
        },

        async refreshCount() {
            try {
                const payload = await this.fetchJson(this.urls.unreadCount);
                this.unreadCount = Number(payload?.unread_count ?? 0) || 0;
            } catch (e) {
                console.warn('[notifications] count refresh failed:', e.message);
            }
        },

        async markAsRead(id) {
            try {
                await this.fetchJson(this.withId(this.urls.markRead, id), { method: 'POST' });
                this.showToast('Success', 'Notification marked as read', 'success');
                await this.loadNotifications();
                await this.refreshCount();
            } catch (e) {
                console.error('[notifications] markAsRead failed:', e);
                this.showToast('Error', e.message || 'Failed to mark as read', 'error');
            }
        },

        async markAllAsRead() {
            try {
                const payload = await this.fetchJson(this.urls.markAllRead, { method: 'POST' });
                this.showToast('Success', payload.message || 'All notifications marked as read', 'success');
                await this.loadNotifications();
                await this.refreshCount();
            } catch (e) {
                console.error('[notifications] markAllAsRead failed:', e);
                this.showToast('Error', e.message || 'Failed to mark all as read', 'error');
            }
        },

        async clearAll() {
            if (!confirm('Are you sure you want to clear all notifications? This cannot be undone.')) {
                return;
            }
            try {
                const payload = await this.fetchJson(this.urls.clearAll, { method: 'DELETE' });
                this.showToast('Success', payload.message || 'All notifications cleared', 'success');
                await this.loadNotifications();
                await this.refreshCount();
            } catch (e) {
                console.error('[notifications] clearAll failed:', e);
                this.showToast('Error', e.message || 'Failed to clear notifications', 'error');
            }
        },

        startPolling() {
            this.stopPolling();
            this.pollTimer = setInterval(() => this.refreshCount(), 30000);
        },

        stopPolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },

        showToast(title, message, type = 'info') {
            let toast = document.getElementById('notification-toast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'notification-toast';
                toast.className = 'notification-toast';
                toast.innerHTML = `
                    <div class="toast-content">
                        <i class="toast-icon"></i>
                        <div class="toast-message">
                            <strong class="toast-title"></strong>
                            <p class="toast-body"></p>
                        </div>
                        <button class="toast-close" type="button" aria-label="Close">&times;</button>
                    </div>`;
                document.body.appendChild(toast);
                toast.querySelector('.toast-close').addEventListener('click', () => toast.remove());
            }

            const iconMap = {
                info:    'fas fa-info-circle',
                success: 'fas fa-check-circle',
                warning: 'fas fa-exclamation-triangle',
                error:   'fas fa-times-circle',
            };

            toast.className = `notification-toast toast-${type} ${type} show`;
            toast.querySelector('.toast-icon').className  = `toast-icon ${iconMap[type] || iconMap.info}`;
            toast.querySelector('.toast-title').textContent = title;
            toast.querySelector('.toast-body').textContent  = message;

            clearTimeout(toast._hideTimer);
            toast._hideTimer = setTimeout(() => toast.remove(), 5000);
        },
    };
}

window.notificationBell = notificationBell;
</script>