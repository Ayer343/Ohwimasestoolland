{{-- resources/views/developer/tools/logs.blade.php --}}
@php
    $pageTitle = 'System Logs';

    // Defensive defaults — the route may or may not pass these
    $logFile    = $logFile    ?? null;
    $logContent = $logContent ?? null;
    $logFiles   = $logFiles   ?? collect();
    $logLines   = $logLines   ?? 200;
    $logSize    = $logSize    ?? null;

    // If the route passed only a $file path, try to load its tail here
    if ($logContent === null && $logFile) {
        try {
            $fullPath = \Illuminate\Support\Facades\Storage::exists($logFile)
                ? \Illuminate\Support\Facades\Storage::path($logFile)
                : base_path($logFile);

            if (file_exists($fullPath) && is_readable($fullPath)) {
                $logSize = filesize($fullPath);
                // Read last N lines efficiently
                $handle = @fopen($fullPath, 'rb');
                if ($handle) {
                    $buffer = '';
                    $chunkSize = 4096;
                    $maxBytes = 512 * 1024; // cap at 512 KB tail
                    fseek($handle, 0, SEEK_END);
                    $pos = ftell($handle);
                    $read = 0;
                    while ($pos > 0 && $read < $maxBytes) {
                        $step = min($chunkSize, $pos);
                        $pos -= $step;
                        fseek($handle, $pos);
                        $buffer = fread($handle, $step) . $buffer;
                        $read += $step;
                    }
                    fclose($handle);
                    $lines      = explode("\n", $buffer);
                    $lines      = array_slice($lines, -$logLines);
                    $logContent = implode("\n", $lines);
                }
            }
        } catch (\Throwable $e) {
            $logContent = null;
        }
    }

    $hasContent = !empty($logContent);
@endphp

@extends('layouts.dev')

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- Header --}}
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-3">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-file-alt text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-file-alt mr-2" style="color: var(--primary);"></i>
                        System Logs
                    </h2>
                    <div class="text-sm flex items-center mt-1 flex-wrap gap-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle"></i>
                        <span>Inspect the latest application logs</span>
                        @if($logFile)
                            <span class="mx-1">•</span>
                            <i class="fas fa-file"></i>
                            <span class="font-mono">{{ $logFile }}</span>
                        @endif
                        @if($logSize !== null)
                            <span class="mx-1">•</span>
                            <i class="fas fa-database"></i>
                            <span>{{ number_format($logSize / 1024, 1) }} KB</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                <div class="flex items-center space-x-2 mt-2 flex-wrap gap-2">
                    <a href="{{ route('developer.tools.dashboard') }}"
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-arrow-left mr-1"></i> Tools Dashboard
                    </a>
                    <a href="{{ route('developer.tools.system-logs') }}"
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-sync mr-1"></i> Refresh
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Navigation --}}
    <div class="card p-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center space-x-3 flex-wrap gap-2">
                <a href="{{ route('developer.dashboard') }}"
                   class="inline-flex items-center text-sm font-medium"
                   style="color: var(--primary);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>

                <a href="{{ route('developer.tools.dashboard') }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-tools mr-1"></i> Tools
                </a>

                <a href="{{ route('developer.tools.system-logs') }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-list mr-1"></i> Log List
                </a>

                <form method="POST" action="{{ route('developer.tools.clear-system-logs') }}"
                      onsubmit="return confirm('Are you sure you want to clear the logs? This cannot be undone.')"
                      class="inline">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                            style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                        <i class="fas fa-trash mr-1"></i> Clear Logs
                    </button>
                </form>
            </div>

            <div class="flex items-center gap-2">
                <label class="text-xs" style="color: var(--text-secondary);">Show last</label>
                <select id="lineCount"
                        class="form-select text-xs rounded-lg border p-1.5"
                        style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                        onchange="filterLogLines(this.value)">
                    @foreach([50, 100, 200, 500, 1000] as $n)
                        <option value="{{ $n }}" {{ $logLines == $n ? 'selected' : '' }}>{{ $n }} lines</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Log Content --}}
    <div class="card">
        <div class="p-6">
            @if($hasContent)
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-terminal mr-2" style="color: var(--info);"></i> Log Output
                    </h3>
                    <div class="flex gap-2">
                        <button type="button"
                                onclick="copyLogContent()"
                                class="px-3 py-1 rounded-lg text-xs font-medium"
                                style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                            <i class="fas fa-copy mr-1"></i> Copy
                        </button>
                        <button type="button"
                                onclick="downloadLogContent()"
                                class="px-3 py-1 rounded-lg text-xs font-medium"
                                style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                            <i class="fas fa-download mr-1"></i> Download
                        </button>
                    </div>
                </div>

                {{-- Log lines with severity highlighting --}}
                <div class="log-viewer"
                     style="background-color: #1e1e1e; border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; max-height: 600px; overflow-y: auto; font-family: 'SFMono-Regular', Consolas, monospace; font-size: 12px; line-height: 1.5;">
                    @foreach(explode("\n", $logContent) as $index => $line)
                        @php
                            $lineClass = 'log-line';
                            if (stripos($line, '.ERROR') !== false)      $lineClass .= ' log-error';
                            elseif (stripos($line, '.WARNING') !== false) $lineClass .= ' log-warning';
                            elseif (stripos($line, '.INFO') !== false)    $lineClass .= ' log-info';
                            elseif (stripos($line, '.DEBUG') !== false)   $lineClass .= ' log-debug';
                        @endphp
                        <div class="{{ $lineClass }}"
                             style="white-space: pre-wrap; word-break: break-all; padding: 1px 4px; border-radius: 2px;">
                            <span style="color: #858585; user-select: none; margin-right: 8px;">{{ $index + 1 }}</span>{{ $line }}
                        </div>
                    @endforeach
                </div>

                <p class="text-xs mt-3" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i>
                    Showing last {{ $logLines }} lines. Older entries are not displayed.
                </p>
            @else
                <div class="text-center py-12">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full mb-4"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-file-alt text-3xl"></i>
                    </div>
                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                        No Log Content
                    </h4>
                    <p class="text-sm max-w-md mx-auto" style="color: var(--text-secondary);">
                        @if($logFile)
                            The file at <code>{{ $logFile }}</code> could not be read.
                            It may not exist, or the web server may not have permission to read it.
                        @else
                            No log file was specified. Return to the tools dashboard to view available log routes.
                        @endif
                    </p>
                    <div class="mt-4 flex justify-center gap-2 flex-wrap">
                        <a href="{{ route('developer.tools.dashboard') }}"
                           class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                           style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                            <i class="fas fa-tools mr-2"></i> Tools Dashboard
                        </a>
                        <a href="{{ route('developer.tools.system-logs') }}"
                           class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                            <i class="fas fa-list mr-2"></i> View Logs List
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Toast Container --}}
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@section('scripts')
<script>
function filterLogLines(count) {
    const url = new URL(window.location.href);
    url.searchParams.set('lines', count);
    window.location.href = url.toString();
}

function copyLogContent() {
    const viewer = document.querySelector('.log-viewer');
    if (!viewer) return;

    navigator.clipboard.writeText(viewer.innerText)
        .then(() => showToast('Log content copied to clipboard', 'success'))
        .catch(err => showToast('Failed to copy: ' + err.message, 'error'));
}

function downloadLogContent() {
    const viewer = document.querySelector('.log-viewer');
    if (!viewer) return;

    const blob = new Blob([viewer.innerText], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'logs-' + new Date().toISOString().slice(0, 19).replace(/[T:]/g, '-') + '.txt';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    showToast('Log file downloaded', 'success');
}

function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(container);
    }

    const colors = {
        success: { bg: 'bg-green-100',  text: 'text-green-800',  icon: 'fa-check-circle' },
        error:   { bg: 'bg-red-100',    text: 'text-red-800',    icon: 'fa-exclamation-circle' },
        warning: { bg: 'bg-yellow-100', text: 'text-yellow-800', icon: 'fa-exclamation-triangle' },
        info:    { bg: 'bg-blue-100',   text: 'text-blue-800',   icon: 'fa-info-circle' },
    };
    const cfg = colors[type] || colors.info;

    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${cfg.bg} ${cfg.text}`;

    const messageEl = document.createElement('span');
    messageEl.className = 'text-sm font-medium flex-1';
    messageEl.innerHTML = `<i class="fas ${cfg.icon} mr-2"></i>${message}`;

    const closeBtn = document.createElement('button');
    closeBtn.className = 'ml-4 hover:opacity-70';
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.onclick = () => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    };

    toast.appendChild(messageEl);
    toast.appendChild(closeBtn);
    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 10);

    setTimeout(() => {
        if (toast.parentNode === container) {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

document.addEventListener('DOMContentLoaded', function () {
    @if(session('success'))
        showToast("{{ session('success') }}", 'success');
    @endif
    @if(session('error'))
        showToast("{{ session('error') }}", 'error');
    @endif
});
</script>

<style>
.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06) !important;
}

.form-select {
    background-color: var(--card-bg) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 8px !important;
}

.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    transform: translateY(-1px);
}

/* Log viewer line classes */
.log-viewer {
    color: #d4d4d4;
    background-color: #1e1e1e;
}

.log-line { display: block; }
.log-error   { color: #f48771; background-color: rgba(244, 135, 113, 0.08); }
.log-warning { color: #dcdcaa; background-color: rgba(220, 220, 170, 0.05); }
.log-info    { color: #9cdcfe; }
.log-debug   { color: #808080; }

.log-viewer::-webkit-scrollbar { width: 10px; }
.log-viewer::-webkit-scrollbar-track { background: #252526; }
.log-viewer::-webkit-scrollbar-thumb { background: #424242; border-radius: 5px; }
.log-viewer::-webkit-scrollbar-thumb:hover { background: #555; }

@media (max-width: 768px) {
    .grid.grid-cols-1.lg\:grid-cols-3 { grid-template-columns: 1fr; }
    .card .p-6 { padding: 1rem !important; }
    .text-2xl { font-size: 1.25rem !important; }
}
</style>
@endsection