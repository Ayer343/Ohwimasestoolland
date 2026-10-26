<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\UnitInquiryReply;
use App\Models\PropertyUnit;
use App\Models\SystemSetting;
use App\Models\UnitInquiry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminInquiryController extends Controller
{
    // ================================================================
    //  INDEX — paginated, filterable list of every inquiry
    // ================================================================
    public function index(Request $request)
    {
        $query = UnitInquiry::with([
                'unit.property',
                'landlord',
                'user',
            ])
            ->withCount(['unit']);

        // ── Filters
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('landlord_id')) {
            $query->where('landlord_id', (int) $request->input('landlord_id'));
        }

        if ($request->filled('property_unit_id')) {
            $query->where('property_unit_id', (int) $request->input('property_unit_id'));
        }

        if ($request->filled('search')) {
            $term = '%' . trim($request->input('search')) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('phone', 'like', $term)
                  ->orWhere('email', 'like', $term)
                  ->orWhere('message', 'like', $term)
                  ->orWhere('uuid', 'like', $term);
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        $inquiries = $query->latest()->paginate(25)->withQueryString();

        // ── Counts for the summary cards
        $stats = [
            'total'     => UnitInquiry::count(),
            'new'       => UnitInquiry::where('status', UnitInquiry::STATUS_NEW)->count(),
            'read'      => UnitInquiry::where('status', UnitInquiry::STATUS_READ)->count(),
            'replied'   => UnitInquiry::where('status', UnitInquiry::STATUS_REPLIED)->count(),
            'spam'      => UnitInquiry::where('status', UnitInquiry::STATUS_SPAM)->count(),
            'today'     => UnitInquiry::whereDate('created_at', today())->count(),
            'this_week' => UnitInquiry::whereBetween('created_at', [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ])->count(),
        ];

        // ── Filter dropdown data
        $landlords = User::where(function ($q) {
                $q->where('type', User::TYPE_LANDLORD)
                  ->orWhereHas('roles', fn ($r) => $r->where('slug', 'landlord'));
            })
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name']);

        $statuses = [
            UnitInquiry::STATUS_NEW     => 'New',
            UnitInquiry::STATUS_READ    => 'Read',
            UnitInquiry::STATUS_REPLIED => 'Replied',
            UnitInquiry::STATUS_CLOSED  => 'Closed',
            UnitInquiry::STATUS_SPAM    => 'Spam',
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'success'   => true,
                'inquiries' => $inquiries,
                'stats'     => $stats,
            ]);
        }

        return view('admin.inquiries.index', compact(
            'inquiries',
            'stats',
            'landlords',
            'statuses'
        ));
    }

    // ================================================================
    //  SHOW — single inquiry detail (auto-marks read)
    // ================================================================
    public function show(string $uuid)
    {
        $inquiry = UnitInquiry::where('uuid', $uuid)
            ->with([
                'unit.property.landlord',
                'landlord',
                'user',
            ])
            ->firstOrFail();

        // ── Auto-mark as read on first view (admin side)
        //    Uses landlord_viewed_at deliberately — it's the "someone
        //    has looked at this" timestamp shared across admin/landlord.
        if (!$inquiry->landlord_viewed_at) {
            $inquiry->update([
                'landlord_viewed_at' => now(),
                'status'             => UnitInquiry::STATUS_READ,
            ]);
        }

        // ── Landlord contact info for the "Call landlord" panel.
        $landlord = $inquiry->landlord;
        $unit     = $inquiry->unit;

        // ── Related inquiries for the same unit (sanity check — is the
        //    landlord ignoring inquiries? Multiple unanswered rows is a
        //    signal).
        $relatedInquiries = UnitInquiry::where('property_unit_id', $inquiry->property_unit_id)
            ->where('id', '!=', $inquiry->id)
            ->latest()
            ->limit(10)
            ->get(['id', 'uuid', 'name', 'status', 'created_at']);

        // ── System settings for currency / branding
        $systemSettings = SystemSetting::getSettings();

        if (request()->expectsJson()) {
            return response()->json([
                'success'           => true,
                'inquiry'           => $inquiry,
                'related_inquiries' => $relatedInquiries,
            ]);
        }

        return view('admin.inquiries.show', compact(
            'inquiry',
            'landlord',
            'unit',
            'relatedInquiries',
            'systemSettings'
        ));
    }

    // ================================================================
    //  REPLY — send a reply to the guest on the landlord's behalf
    // ================================================================
    public function reply(Request $request, string $uuid)
    {
        $validator = Validator::make($request->all(), [
            'reply_message' => 'required|string|min:5|max:5000',
            'also_email'    => 'nullable|boolean',
        ], [
            'reply_message.required' => 'Please write a reply.',
            'reply_message.min'      => 'The reply is too short.',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'errors'  => $validator->errors(),
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $inquiry = UnitInquiry::where('uuid', $uuid)
            ->with(['unit.property', 'landlord'])
            ->firstOrFail();

        if ($inquiry->status === UnitInquiry::STATUS_SPAM) {
            $msg = 'This inquiry has been flagged as spam. Unmark it before replying.';
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 409)
                : back()->with('error', $msg);
        }

        DB::beginTransaction();

        try {
            $replyText = trim($request->input('reply_message'));

            // Persist the reply on the inquiry row
            $inquiry->update([
                'status'         => UnitInquiry::STATUS_REPLIED,
                'replied_at'     => now(),
                'reply_message'  => $replyText,
                'replied_by'     => auth()->id(),
                'replied_by_role'=> 'admin',
            ]);

            // Optionally forward to the guest's email — only works if
            // they provided one.
            $emailed = false;
            if ($request->boolean('also_email') && !empty($inquiry->email)) {
                try {
                    Mail::to($inquiry->email)
                        ->send(new UnitInquiryReply($inquiry, $replyText, $inquiry->landlord));
                    $emailed = true;
                } catch (\Throwable $e) {
                    Log::warning('AdminInquiry: guest email failed', [
                        'inquiry_id' => $inquiry->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            // Log the admin action for audit
            Log::info('Admin replied to unit inquiry', [
                'inquiry_id'    => $inquiry->id,
                'inquiry_uuid'  => $inquiry->uuid,
                'admin_id'      => auth()->id(),
                'emailed_guest' => $emailed,
            ]);

            DB::commit();

            $msg = 'Reply saved.' . ($emailed ? ' Guest has been emailed.' : '');

            return $request->expectsJson()
                ? response()->json([
                    'success' => true,
                    'message' => $msg,
                    'inquiry' => $inquiry->fresh(['unit', 'landlord']),
                ])
                : back()->with('success', $msg);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('AdminInquiry: reply threw', [
                'inquiry_id' => $inquiry->id,
                'error'      => $e->getMessage(),
            ]);

            $msg = 'Failed to send reply. Please try again.';
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 500)
                : back()->with('error', $msg)->withInput();
        }
    }

    // ================================================================
    //  STATUS — flip between new / read / replied / closed / spam
    // ================================================================
    public function status(Request $request, string $uuid)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:' . implode(',', [
                UnitInquiry::STATUS_NEW,
                UnitInquiry::STATUS_READ,
                UnitInquiry::STATUS_REPLIED,
                UnitInquiry::STATUS_CLOSED,
                UnitInquiry::STATUS_SPAM,
            ]),
        ]);

        if ($validator->fails()) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'errors' => $validator->errors()], 422)
                : back()->withErrors($validator);
        }

        $inquiry = UnitInquiry::where('uuid', $uuid)->firstOrFail();
        $newStatus = $request->input('status');

        $inquiry->update(['status' => $newStatus]);

        // Stamp replied_at only the first time it moves to REPLIED
        if ($newStatus === UnitInquiry::STATUS_REPLIED && !$inquiry->replied_at) {
            $inquiry->update(['replied_at' => now()]);
        }

        Log::info('AdminInquiry: status changed', [
            'inquiry_id' => $inquiry->id,
            'old'        => $inquiry->getOriginal('status'),
            'new'        => $newStatus,
            'admin_id'   => auth()->id(),
        ]);

        return $request->expectsJson()
            ? response()->json([
                'success' => true,
                'message' => "Status set to {$newStatus}.",
                'inquiry' => $inquiry->fresh(),
            ])
            : back()->with('success', "Status set to {$newStatus}.");
    }

    // ================================================================
    //  DESTROY — soft delete (spam cleanup)
    // ================================================================
    public function destroy(string $uuid)
    {
        $inquiry = UnitInquiry::where('uuid', $uuid)->firstOrFail();

        $inquiryId = $inquiry->id;
        $inquiry->delete(); // soft delete

        Log::info('AdminInquiry: soft-deleted', [
            'inquiry_id' => $inquiryId,
            'admin_id'   => auth()->id(),
        ]);

        return request()->expectsJson()
            ? response()->json(['success' => true, 'message' => 'Inquiry deleted.'])
            : redirect()->route('admin.inquiries.index')
                ->with('success', 'Inquiry deleted.');
    }

    // ================================================================
    //  EXPORT — CSV download of the filtered set
    // ================================================================
    public function export(Request $request): StreamedResponse
    {
        $query = UnitInquiry::with(['unit.property', 'landlord']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('landlord_id')) {
            $query->where('landlord_id', (int) $request->input('landlord_id'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        $filename = 'inquiries-' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens the file correctly
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'UUID',
                'Received At',
                'Status',
                'Unit',
                'Property',
                'Landlord',
                'Guest Name',
                'Guest Phone',
                'Guest Email',
                'Message',
                'Reply',
                'Replied At',
                'Channels Sent',
                'Channels Failed',
            ]);

            $query->chunk(500, function ($rows) use ($handle) {
                foreach ($rows as $inquiry) {
                    fputcsv($handle, [
                        $inquiry->uuid,
                        optional($inquiry->created_at)->toDateTimeString(),
                        $inquiry->status,
                        optional($inquiry->unit)->unit_name
                            ?? optional($inquiry->unit)->unit_number
                            ?? '',
                        optional(optional($inquiry->unit)->property)->property_name ?? '',
                        optional($inquiry->landlord)->name ?? '',
                        $inquiry->name,
                        $inquiry->phone,
                        $inquiry->email,
                        Str::limit($inquiry->message, 500),
                        Str::limit((string) $inquiry->reply_message, 500),
                        optional($inquiry->replied_at)->toDateTimeString(),
                        implode('|', (array) $inquiry->channels_sent),
                        implode('|', (array) $inquiry->channels_failed),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}