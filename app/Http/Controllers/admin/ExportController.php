<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SystemSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    /**
     * Export users to PDF (standalone route)
     */
    public function exportToPdf(Request $request)
    {
        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $query = User::withoutGlobalScopes()
                ->with(['creator'])
                ->orderBy('created_at', 'desc');

            if ($request->filled('type') && $request->type !== 'all') {
                $query->where('type', $request->type);
            }
            if ($request->filled('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }
            if ($request->filled('phone_verified') && $request->phone_verified !== 'all') {
                if ($request->phone_verified === 'verified') {
                    $query->whereNotNull('phone_verified_at');
                } else {
                    $query->whereNull('phone_verified_at');
                }
            }
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('username', 'like', "%{$search}%");
                });
            }

            // Exclude ONLY developers (type = 5)
            $query->where('type', '!=', User::TYPE_DEVELOPER);

            // Only apply date range when both dates are provided
            $startDate = $request->input('start_date');
            $endDate   = $request->input('end_date');

            if (!empty($startDate) && !empty($endDate)) {
                $query->whereBetween('created_at', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay(),
                ]);
            }

            $users = $query->get();

            Log::info('Standalone PDF export query executed', [
                'total_returned' => $users->count(),
                'types_returned' => $users->groupBy('type')->map->count()->toArray(),
                'expected_total' => User::withoutGlobalScopes()->where('type', '!=', User::TYPE_DEVELOPER)->count(),
            ]);

            if ($users->isEmpty()) {
                return back()->with('error', 'No users found matching your criteria.');
            }

            $options = [
                'include_photos'     => $request->boolean('include_photos', false),
                'include_statistics' => $request->boolean('include_statistics', true),
                'filters'            => $request->all(),
            ];

            return $this->exportToPdfPrivate($users, $options);

        } catch (\Throwable $e) {
            Log::error('Failed to export users to PDF: ' . $e->getMessage(), [
                'exported_by' => Auth::id(),
                'trace'       => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Failed to generate PDF export: ' . $e->getMessage());
        }
    }

    /**
     * Export users to CSV or PDF based on request
     */
    public function export(Request $request)
    {
        // ═══════════════════════════════════════════════════════════
        // 🔍 DIAGNOSTIC — log the entry
        // ═══════════════════════════════════════════════════════════
        Log::info('═══ ExportController::export() ENTERED ═══', [
            'file'            => __FILE__,
            'line'            => __LINE__,
            'auth_user_id'    => Auth::id(),
            'request_params'  => $request->all(),
            'request_url'     => $request->fullUrl(),
        ]);

        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        try {
            // ───────────────────────────────────────────────────────
            // VALIDATION
            //
            // ⚠️ KEY FIX: `start_date` and `end_date` MUST use `nullable`
            // (not `sometimes`). The export form always submits them —
            // as empty strings when the user hasn't picked a range.
            // `sometimes` treats "present but empty" as "validate the
            // date rule" → fails. `nullable` allows empty values while
            // still enforcing the `date` rule when a value IS given.
            // ───────────────────────────────────────────────────────
            $validated = $request->validate([
                'export_format'      => 'required|in:csv,pdf',
                'user_types'         => 'sometimes|array',
                'user_types.*'       => 'in:all,' . implode(',', [
                    User::TYPE_SUPER_ADMIN,
                    User::TYPE_ADMIN,
                    User::TYPE_LANDLORD,
                    User::TYPE_TENANT,
                    User::TYPE_FIELD_AGENT,
                    User::TYPE_SECURITY_PERSONNEL,
                    User::TYPE_CONTRACTOR,
                    User::TYPE_SANITATION_PERSONNEL,
                ]),
                'include_photos'     => 'sometimes|boolean',
                'include_statistics' => 'sometimes|boolean',
                'current_filters'    => 'sometimes|boolean',
                'start_date'         => 'nullable|date',
                'end_date'           => 'nullable|date|after_or_equal:start_date',
            ]);

            $selectedTypes       = $validated['user_types'] ?? ['all'];
            $isExportingAllTypes = in_array('all', $selectedTypes, true) || empty($selectedTypes);

            // ───────────────────────────────────────────────────────
            // Base query — WITHOUT global scopes AND with `creator`
            // ───────────────────────────────────────────────────────
            $query = User::withoutGlobalScopes()->with(['creator']);

            // Apply type filter
            if (!$isExportingAllTypes) {
                $rawTypes    = array_values(array_filter($selectedTypes, fn ($v) => $v !== 'all'));
                $intTypes    = array_values(array_filter(array_map('intval', $rawTypes), fn ($v) => $v >= 0));
                $stringTypes = array_values(array_map('strval', $rawTypes));

                $query->where(function ($q) use ($intTypes, $stringTypes) {
                    $q->whereIn('type', $intTypes)
                      ->orWhereIn('type', $stringTypes);
                });
            } else {
                $query->where('type', '!=', User::TYPE_DEVELOPER);
            }

            // Apply "current filters" ONLY when NOT exporting all types
            if (!$isExportingAllTypes
                && $request->boolean('current_filters')
                && $request->filled('type')
                && $request->type !== 'all') {
                $query->where('type', $request->type);
            }

            if (!$isExportingAllTypes
                && $request->boolean('current_filters')
                && $request->filled('status')
                && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if (!$isExportingAllTypes
                && $request->boolean('current_filters')
                && $request->filled('phone_verified')
                && $request->phone_verified !== 'all') {
                if ($request->phone_verified === 'verified') {
                    $query->whereNotNull('phone_verified_at');
                } else {
                    $query->whereNull('phone_verified_at');
                }
            }

            if (!$isExportingAllTypes
                && $request->boolean('current_filters')
                && $request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('username', 'like', "%{$search}%");
                });
            }

            // ───────────────────────────────────────────────────────
            // Date range — apply ONLY when both dates are non-empty
            // ───────────────────────────────────────────────────────
            $startDate = $validated['start_date'] ?? null;
            $endDate   = $validated['end_date'] ?? null;

            if (!empty($startDate) && !empty($endDate)) {
                $query->whereBetween('created_at', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay(),
                ]);

                Log::info('Export: date range filter APPLIED', [
                    'start' => $startDate,
                    'end'   => $endDate,
                ]);
            } else {
                Log::info('Export: no date range filter applied', [
                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                ]);
            }

            // ───────────────────────────────────────────────────────
            // 🔍 DIAGNOSTIC — log the SQL before execution
            // ───────────────────────────────────────────────────────
            Log::info('Export SQL preview', [
                'sql'      => $query->toSql(),
                'bindings' => $query->getBindings(),
            ]);

            $users = $query->orderBy('created_at', 'desc')->get();

            // ───────────────────────────────────────────────────────
            // 🔍 DIAGNOSTIC — log the result counts
            // ───────────────────────────────────────────────────────
            Log::info('Export query result', [
                'requested_types'      => $selectedTypes,
                'is_all_types'         => $isExportingAllTypes,
                'current_filters'      => $request->boolean('current_filters'),
                'total_users_returned' => $users->count(),
                'expected_total'       => User::withoutGlobalScopes()
                                            ->where('type', '!=', User::TYPE_DEVELOPER)
                                            ->count(),
                'types_returned'       => $users->groupBy('type')->map->count()->toArray(),
                'ids_returned'         => $users->pluck('id')->toArray(),
            ]);

            if ($users->isEmpty()) {
                return back()->with('error', 'No users found matching your criteria.');
            }

            $validated['filters'] = $request->all();

            switch ($validated['export_format']) {
                case 'csv':
                    return $this->exportToCsv($users, $validated);
                case 'pdf':
                    return $this->exportToPdfPrivate($users, $validated);
                default:
                    return back()->with('error', 'Invalid export format.');
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            // ═══════════════════════════════════════════════════════
            // 🔍 DIAGNOSTIC — surface validation errors clearly
            // ═══════════════════════════════════════════════════════
            Log::warning('Export validation failed', [
                'errors' => $e->errors(),
                'input'  => $request->all(),
            ]);
            throw $e;

        } catch (\Throwable $e) {
            Log::error('Failed to export users: ' . $e->getMessage(), [
                'exported_by' => Auth::id(),
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Failed to generate export: ' . $e->getMessage());
        }
    }

    /**
     * Export users to CSV format
     */
    private function exportToCsv($users, $options = [])
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $csvData  = $this->generateCsvContent($users);
        $fileName = 'users_export_' . date('Y-m-d_H-i-s') . '.csv';

        Log::info('CSV Export Completed', [
            'file_name'  => $fileName,
            'user_count' => $users->count(),
            'size_bytes' => strlen($csvData),
        ]);

        return response($csvData, 200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"')
            ->header('Content-Length', (string) strlen($csvData))
            ->header('Pragma', 'no-cache')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Expires', '0');
    }

    /**
     * Export users to PDF format
     */
    private function exportToPdfPrivate($users, $options = [])
    {
        $obLevelBefore = ob_get_level();
        $obDump = [];

        if ($obLevelBefore > 0) {
            for ($i = 0; $i < $obLevelBefore; $i++) {
                $contents = ob_get_contents();
                $obDump[] = [
                    'level'   => $i,
                    'length'  => strlen($contents ?: ''),
                    'preview' => substr($contents ?: '', 0, 200),
                ];
            }
        }

        Log::info('PDF Export Started', [
            'user_count'         => $users->count(),
            'include_photos'     => $options['include_photos'] ?? false,
            'include_statistics' => $options['include_statistics'] ?? true,
            'exported_by'        => Auth::id(),
            'ob_level_at_start'  => $obLevelBefore,
            'ob_dump_at_start'   => $obDump,
            'memory_usage'       => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'peak_memory'        => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
        ]);

        $cleared = 0;
        while (ob_get_level() > 0) {
            ob_end_clean();
            $cleared++;
        }

        Log::info('PDF Export — Output buffers cleared', [
            'buffers_cleared' => $cleared,
            'ob_level_now'    => ob_get_level(),
        ]);

        $includePhotos     = $options['include_photos'] ?? false;
        $includeStatistics = $options['include_statistics'] ?? true;
        $filters           = $options['filters'] ?? [];

        try {
            $settings = SystemSetting::getSettings();

            $systemLogoDataUri = null;

            if ($settings && !empty($settings->system_logo)) {
                $logoFile = $settings->system_logo;

                $possiblePaths = [
                    'system/' . $logoFile,
                    'system_logos/' . $logoFile,
                    $logoFile,
                ];

                foreach ($possiblePaths as $relative) {
                    if (Storage::disk('public')->exists($relative)) {
                        try {
                            $bytes = Storage::disk('public')->get($relative);

                            if (!empty($bytes)) {
                                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                                $mime  = $finfo->buffer($bytes) ?: 'image/png';
                                $systemLogoDataUri = 'data:' . $mime . ';base64,' . base64_encode($bytes);
                            }
                        } catch (\Throwable $e) {
                            Log::warning('Failed to embed logo as base64', [
                                'relative' => $relative,
                                'error'    => $e->getMessage(),
                            ]);
                        }
                        break;
                    }
                }
            }

            Log::debug('PDF Export — Logo resolved', [
                'has_settings'    => (bool) $settings,
                'logo_file'       => $settings->system_logo ?? null,
                'logo_embedded'   => !empty($systemLogoDataUri),
                'logo_datauri_len' => $systemLogoDataUri ? strlen($systemLogoDataUri) : 0,
            ]);

            $data = [
                'systemName'        => $settings->system_name       ?? config('app.name', 'Property Pro'),
                'systemShortName'   => $settings->system_short_name ?? config('app.name_short', 'PP'),
                'systemLogo'        => $systemLogoDataUri,
                'systemTagline'     => $settings->system_tagline    ?? null,
                'systemEmail'       => $settings->system_email      ?? config('mail.from.address'),
                'systemPhone'       => $settings->system_phone      ?? null,

                'users'             => $users,
                'includePhotos'     => $includePhotos,
                'includeStatistics' => $includeStatistics,
                'exportDate'        => now()->format('F j, Y g:i A'),
                'exportedBy'        => Auth::user()->name,
                'totalUsers'        => $users->count(),
                'filters'           => $filters,
                'userTypes'         => $users->groupBy('type')->map->count(),
            ];

            foreach ($data['users'] as $user) {
                $user->type_name = $this->getUserTypeName($user->type);
            }

            if ($includePhotos) {
                $photosLoaded = 0;
                $photosMissing = 0;

                foreach ($data['users'] as $user) {
                    $user->photo_base64 = $this->getUserPhotoBase64($user);

                    if (!empty($user->photo_base64)) {
                        $photosLoaded++;
                    } elseif (!empty($user->photo)) {
                        $photosMissing++;
                    }
                }

                Log::info('PDF Export — User photos loaded', [
                    'total_users'    => $data['users']->count(),
                    'photos_loaded'  => $photosLoaded,
                    'photos_missing' => $photosMissing,
                ]);
            } else {
                Log::info('PDF Export — User photos skipped (not requested)');
            }

            if ($includeStatistics) {
                $data['statistics'] = $this->generateExportStatistics($users);
            }

            Log::debug('PDF Data Prepared', [
                'users_count'  => $data['users']->count(),
                'has_users'    => isset($data['users']),
                'system_name'  => $data['systemName'],
                'has_logo'     => !empty($data['systemLogo']),
                'data_keys'    => array_keys($data),
                'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            ]);

            $viewStart = microtime(true);

            try {
                $html = view('admin.users.exports.pdf', $data)->render();
            } catch (\Throwable $e) {
                Log::error('PDF Export — Blade render FAILED', [
                    'error' => $e->getMessage(),
                    'file'  => $e->getFile(),
                    'line'  => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }

            $viewMs = round((microtime(true) - $viewStart) * 1000, 2);

            Log::info('PDF Export — HTML view rendered', [
                'html_length'    => strlen($html),
                'render_time_ms' => $viewMs,
                'html_has_body'  => str_contains($html, '<body'),
                'html_ends_with' => substr($html, -100),
            ]);

            $pdfStart = microtime(true);

            try {
                $pdf = Pdf::loadHTML($html);

                $pdf->setPaper('a4', 'landscape');

                $pdf->setOptions([
                    'defaultFont'          => 'DejaVu Sans',
                    'isHtml5ParserEnabled' => true,
                    'isPhpEnabled'         => false,
                    'isJavascriptEnabled'  => false,
                    'isRemoteEnabled'      => false,
                    'dpi'                  => 150,
                    'chroot'               => public_path(),
                    'margin_top'           => 10,
                    'margin_bottom'        => 15,
                    'margin_left'          => 10,
                    'margin_right'         => 10,
                ]);

                $output = $pdf->output();
            } catch (\Throwable $e) {
                Log::error('PDF Export — DomPDF render FAILED', [
                    'error' => $e->getMessage(),
                    'file'  => $e->getFile(),
                    'line'  => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }

            $pdfMs       = round((microtime(true) - $pdfStart) * 1000, 2);
            $outputSize  = strlen($output);
            $pdfHeader   = bin2hex(substr($output, 0, 8));
            $isValidPdf  = str_starts_with($output, '%PDF-');
            $fileName    = 'users_export_' . date('Y-m-d_H-i-s') . '.pdf';

            Log::info('PDF Export Completed Successfully', [
                'file_name'         => $fileName,
                'user_count'        => $users->count(),
                'system_name'       => $data['systemName'],
                'pdf_size_bytes'    => $outputSize,
                'pdf_size_human'    => round($outputSize / 1024, 2) . ' KB',
                'pdf_header_hex'    => $pdfHeader,
                'pdf_header_ascii'  => substr($output, 0, 8),
                'is_valid_pdf'      => $isValidPdf,
                'render_time_ms'    => $pdfMs,
                'peak_memory'       => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
                'ob_level_at_end'   => ob_get_level(),
            ]);

            if ($outputSize === 0) {
                Log::error('PDF Export — Refusing to send empty PDF', [
                    'html_length' => strlen($html),
                    'user_count'  => $users->count(),
                ]);
                abort(500, 'PDF generation produced empty output.');
            }

            if (!$isValidPdf) {
                Log::error('PDF Export — Refusing to send malformed PDF', [
                    'header_hex' => $pdfHeader,
                    'first_200'  => substr($output, 0, 200),
                ]);
                abort(500, 'PDF generation produced malformed output.');
            }

            Log::info('PDF Export — Dispatching HTTP response', [
                'file_name'      => $fileName,
                'content_length' => $outputSize,
                'content_type'   => 'application/pdf',
            ]);

            return response($output, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Length', (string) $outputSize)
                ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"')
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0')
                ->header('X-Content-Type-Options', 'nosniff')
                ->header('X-Export-Debug', 'size=' . $outputSize);

        } catch (\Throwable $e) {
            Log::error('PDF Generation Failed in exportToPdfPrivate', [
                'error'       => $e->getMessage(),
                'line'        => $e->getLine(),
                'file'        => $e->getFile(),
                'trace'       => $e->getTraceAsString(),
                'users_count' => $users->count(),
                'exported_by' => Auth::id(),
            ]);
            throw $e;
        }
    }

    /**
     * Load a user's photo from storage and return it as a base64 data URI.
     */
    private function getUserPhotoBase64(User $user): ?string
    {
        if (empty($user->photo)) {
            return null;
        }

        $candidates = [
            'users/photos/' . $user->photo,
            $user->photo,
        ];

        $bytes = null;

        foreach ($candidates as $relative) {
            if (Storage::disk('public')->exists($relative)) {
                $bytes = Storage::disk('public')->get($relative);
                if (!empty($bytes)) {
                    break;
                }
            }
        }

        if (empty($bytes) && file_exists(public_path('storage/users/photos/' . $user->photo))) {
            $bytes = @file_get_contents(public_path('storage/users/photos/' . $user->photo));
        }

        if (empty($bytes)) {
            Log::warning('PDF export: user photo file not found', [
                'user_id' => $user->id,
                'photo'   => $user->photo,
            ]);
            return null;
        }

        $mime = 'image/jpeg';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected = finfo_buffer($finfo, $bytes);
            finfo_close($finfo);
            if ($detected) {
                $mime = $detected;
            }
        }

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    /**
     * Get user type name
     */
    private function getUserTypeName($type)
    {
        $types = [
            User::TYPE_SUPER_ADMIN          => 'Super Admin',
            User::TYPE_ADMIN                => 'Admin',
            User::TYPE_LANDLORD             => 'Landlord',
            User::TYPE_TENANT               => 'Tenant',
            User::TYPE_FIELD_AGENT          => 'Field Agent',
            User::TYPE_DEVELOPER            => 'Developer',
            User::TYPE_SECURITY_PERSONNEL   => 'Security Personnel',
            User::TYPE_CONTRACTOR           => 'Contractor',
            User::TYPE_SANITATION_PERSONNEL => 'Sanitation Personnel',
        ];

        return $types[$type] ?? 'User';
    }

    /**
     * Generate CSV content
     */
    private function generateCsvContent($users): string
    {
        $output = "\xEF\xBB\xBF";

        $headers = [
            'ID', 'Full Name', 'Email Address', 'Phone Number', 'Username',
            'User Type', 'Account Status', 'Phone Verified', 'Email Verified',
            'Gender', 'Date of Birth', 'Digital Address', 'Region', 'Location',
            'Profile Photo', 'Created By', 'Registration Date', 'Last Login',
        ];

        $output .= $this->arrayToCsv($headers);

        foreach ($users as $user) {
            $row = [
                $user->id,
                $user->name,
                $user->email,
                $user->phone ?? 'N/A',
                $user->username ?? 'N/A',
                $this->getUserTypeName($user->type),
                ucfirst($user->status),
                $user->phone_verified_at ? 'Yes' : 'No',
                $user->email_verified_at ? 'Yes' : 'No',
                $user->gender ? ucfirst($user->gender) : 'N/A',
                $this->formatDate($user->dob),
                $user->digital_address ?? 'N/A',
                $user->region ?? 'N/A',
                $user->location ?? 'N/A',
                $user->photo ? 'Yes' : 'No',
                $user->creator->name ?? 'System',
                $user->created_at->format('Y-m-d H:i:s'),
                $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : 'Never',
            ];

            $output .= $this->arrayToCsv($row);
        }

        return $output;
    }

    /**
     * Generate export statistics
     */
    private function generateExportStatistics($users): array
    {
        $totalUsers     = $users->count();
        $verifiedPhones = $users->whereNotNull('phone_verified_at')->count();
        $verifiedEmails = $users->whereNotNull('email_verified_at')->count();
        $withPhotos     = $users->whereNotNull('photo')->count();

        $typeBreakdown = $users->groupBy('type')->map(function ($group) use ($users) {
            return [
                'count'      => $group->count(),
                'percentage' => round(($group->count() / max($users->count(), 1)) * 100, 1),
            ];
        });

        $statusBreakdown = $users->groupBy('status')->map(function ($group) use ($users) {
            return [
                'count'      => $group->count(),
                'percentage' => round(($group->count() / max($users->count(), 1)) * 100, 1),
            ];
        });

        return [
            'total_users'      => $totalUsers,
            'verified_phones'  => $verifiedPhones,
            'verified_emails'  => $verifiedEmails,
            'with_photos'      => $withPhotos,
            'type_breakdown'   => $typeBreakdown,
            'status_breakdown' => $statusBreakdown,
            'completion_rate'  => [
                'phone_verification' => $totalUsers > 0 ? round(($verifiedPhones / $totalUsers) * 100, 1) : 0,
                'email_verification' => $totalUsers > 0 ? round(($verifiedEmails / $totalUsers) * 100, 1) : 0,
                'profile_photos'     => $totalUsers > 0 ? round(($withPhotos / $totalUsers) * 100, 1) : 0,
            ],
        ];
    }

    /**
     * Safely format date
     */
    private function formatDate($date): string
    {
        if (empty($date)) {
            return 'N/A';
        }

        try {
            if ($date instanceof \Carbon\Carbon) {
                return $date->format('Y-m-d');
            }

            if (is_string($date)) {
                $formats = ['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'm-d-Y'];

                foreach ($formats as $format) {
                    try {
                        $parsed = \Carbon\Carbon::createFromFormat($format, $date);
                        if ($parsed && $parsed->format($format) === $date) {
                            return $parsed->format('Y-m-d');
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }

                try {
                    return \Carbon\Carbon::parse($date)->format('Y-m-d');
                } catch (\Exception $e) {
                    return $date;
                }
            }

            return (string) $date;

        } catch (\Exception $e) {
            Log::warning('Date formatting failed', [
                'date'  => $date,
                'type'  => gettype($date),
                'error' => $e->getMessage(),
            ]);
            return 'Invalid Date';
        }
    }

    /**
     * Convert array to CSV line
     */
    private function arrayToCsv(array $fields): string
    {
        $output = '';

        foreach ($fields as $key => $value) {
            $value = (string) $value;

            if (strpos($value, ',') !== false ||
                strpos($value, '"') !== false ||
                strpos($value, "\n") !== false) {
                $value = '"' . str_replace('"', '""', $value) . '"';
            }

            $output .= $value;

            if ($key < count($fields) - 1) {
                $output .= ',';
            }
        }

        return $output . "\n";
    }
}