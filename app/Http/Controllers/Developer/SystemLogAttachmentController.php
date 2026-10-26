<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\SystemLogAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SystemLogAttachmentController extends Controller
{
    private const USER_TYPE_SUPER_ADMIN = 0;
    private const USER_TYPE_DEVELOPER   = 5;

    private function canManageLogs(): bool
    {
        $user = Auth::user();
        return $user && in_array((int) $user->type, [self::USER_TYPE_SUPER_ADMIN, self::USER_TYPE_DEVELOPER], true);
    }

    public function download($log, $attachment, Request $request)
    {
        if (! $this->canManageLogs()) {
            Log::warning('System log attachment access denied', [
                'user_id'       => Auth::id(),
                'user_type'     => Auth::user()->type ?? null,
                'log_id'        => $log,
                'attachment_id' => $attachment,
                'ip'            => $request->ip(),
            ]);
            abort(403, 'Unauthorized to access system log attachments.');
        }

        $att = SystemLogAttachment::where('system_log_id', $log)
            ->where('id', $attachment)
            ->firstOrFail();

        $disk = $att->disk ?? config('filesystems.log_attachments_disk', 'local');

        if (! $att->path || ! Storage::disk($disk)->exists($att->path)) {
            abort(404, 'Attachment file not found.');
        }

        $absolutePath = Storage::disk($disk)->path($att->path);

        return response()->download(
            $absolutePath,
            $att->original_filename,
            [
                'Content-Type'           => $att->mime_type ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control'          => 'private, no-store',
            ]
        );
    }
}