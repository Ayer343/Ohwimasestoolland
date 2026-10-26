<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Mail\UnitInquiryReply;
use App\Models\UnitInquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class LandlordInquiryController extends Controller
{
    // ================================================================
    //  INDEX — paginated list of inquiries routed to this landlord
    // ================================================================
    public function index(Request $request)
    {
        $query = UnitInquiry::where('landlord_id', auth()->id())
            ->with(['unit.property', 'user']);

        // ── Filters
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
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

        if ($request->filled('property_unit_id')) {
            $query->where('property_unit_id', (int) $request->input('property_unit_id'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        $inquiries = $query->latest()->paginate(20)->withQueryString();

        // ── Summary counts (landlord-scoped)
        $unreadCount = UnitInquiry::where('landlord_id', auth()->id())
            ->whereNull('landlord_viewed_at')
            ->count();

        $stats = [
            'total'   => UnitInquiry::where('landlord_id', auth()->id())->count(),
            'new'     => UnitInquiry::where('landlord_id', auth()->id())
                ->where('status', UnitInquiry::STATUS_NEW)->count(),
            'replied' => UnitInquiry::where('landlord_id', auth()->id())
                ->where('status', UnitInquiry::STATUS_REPLIED)->count(),
            'unread'  => $unreadCount,
        ];

        // ── Status filter options
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

        return view('landlord.inquiries.index', compact(
            'inquiries',
            'stats',
            'statuses',
            'unreadCount'
        ));
    }

    // ================================================================
    //  SHOW — single inquiry detail
    // ================================================================
    public function show(string $uuid)
    {
        $inquiry = $this->findForLandlord($uuid);

        if (!$inquiry->landlord_viewed_at) {
            $updates = ['landlord_viewed_at' => now()];
            if ($inquiry->status === UnitInquiry::STATUS_NEW) {
                $updates['status'] = UnitInquiry::STATUS_READ;
            }
            $inquiry->update($updates);
        }

        $relatedInquiries = UnitInquiry::where('property_unit_id', $inquiry->property_unit_id)
            ->where('id', '!=', $inquiry->id)
            ->where('landlord_id', auth()->id())
            ->latest()
            ->limit(5)
            ->get(['id', 'uuid', 'name', 'status', 'created_at']);

        if (request()->expectsJson()) {
            return response()->json([
                'success'           => true,
                'inquiry'           => $inquiry,
                'related_inquiries' => $relatedInquiries,
            ]);
        }

        return view('landlord.inquiries.show', compact('inquiry', 'relatedInquiries'));
    }

    // ================================================================
    //  REPLY — send reply to guest
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
            return $request->expectsJson()
                ? response()->json(['success' => false, 'errors' => $validator->errors()], 422)
                : back()->withErrors($validator)->withInput();
        }

        $inquiry = $this->findForLandlord($uuid);

        if ($inquiry->status === UnitInquiry::STATUS_SPAM) {
            $msg = 'This inquiry is flagged as spam. Unmark it before replying.';
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 409)
                : back()->with('error', $msg);
        }

        DB::beginTransaction();

        try {
            $replyText = trim($request->input('reply_message'));

            $inquiry->update([
                'status'          => UnitInquiry::STATUS_REPLIED,
                'replied_at'      => now(),
                'reply_message'   => $replyText,
                'replied_by'      => auth()->id(),
                'replied_by_role' => 'landlord',
            ]);

            $emailed = false;
            if ($request->boolean('also_email') && !empty($inquiry->email)) {
                try {
                    Mail::to($inquiry->email)
                        ->send(new UnitInquiryReply($inquiry, $replyText, auth()->user()));
                    $emailed = true;
                } catch (\Throwable $e) {
                    Log::warning('LandlordInquiry: guest email failed', [
                        'inquiry_id'  => $inquiry->id,
                        'landlord_id' => auth()->id(),
                        'error'       => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Landlord replied to unit inquiry', [
                'inquiry_id'    => $inquiry->id,
                'inquiry_uuid'  => $inquiry->uuid,
                'landlord_id'   => auth()->id(),
                'emailed_guest' => $emailed,
            ]);

            DB::commit();

            $msg = 'Reply sent.' . ($emailed ? ' Guest has been emailed.' : '');

            return $request->expectsJson()
                ? response()->json([
                    'success' => true,
                    'message' => $msg,
                    'inquiry' => $inquiry->fresh(['unit', 'user']),
                ])
                : redirect()
                    ->route('landlord.inquiries.show', $inquiry->uuid)
                    ->with('success', $msg);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('LandlordInquiry: reply threw', [
                'inquiry_id'  => $inquiry->id,
                'landlord_id' => auth()->id(),
                'error'       => $e->getMessage(),
            ]);

            $msg = 'Failed to send reply. Please try again.';
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 500)
                : back()->with('error', $msg)->withInput();
        }
    }

    // ================================================================
    //  STATUS — flip between statuses
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

        $inquiry = $this->findForLandlord($uuid);

        $oldStatus = $inquiry->status;
        $newStatus = $request->input('status');

        $updates = ['status' => $newStatus];

        if ($newStatus === UnitInquiry::STATUS_REPLIED && !$inquiry->replied_at) {
            $updates['replied_at'] = now();
        }

        $inquiry->update($updates);

        Log::info('LandlordInquiry: status changed', [
            'inquiry_id'  => $inquiry->id,
            'landlord_id' => auth()->id(),
            'old'         => $oldStatus,
            'new'         => $newStatus,
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
    //  DESTROY — soft delete
    // ================================================================
    public function destroy(string $uuid)
    {
        $inquiry = $this->findForLandlord($uuid);

        $inquiryId = $inquiry->id;
        $inquiry->delete();

        Log::info('LandlordInquiry: soft-deleted', [
            'inquiry_id'  => $inquiryId,
            'landlord_id' => auth()->id(),
        ]);

        return request()->expectsJson()
            ? response()->json(['success' => true, 'message' => 'Inquiry removed.'])
            : redirect()
                ->route('landlord.inquiries.index')
                ->with('success', 'Inquiry removed.');
    }

    // ================================================================
    //  UNREAD COUNT — badge JSON
    // ================================================================
    public function unreadCount()
    {
        $unread = UnitInquiry::where('landlord_id', auth()->id())
            ->whereNull('landlord_viewed_at')
            ->count();

        $total = UnitInquiry::where('landlord_id', auth()->id())->count();

        return response()->json([
            'unread_count' => $unread,
            'total'        => $total,
        ]);
    }

    // ================================================================
    //  INTERNAL — find inquiry owned by this landlord or 404
    // ================================================================
    private function findForLandlord(string $uuid): UnitInquiry
    {
        return UnitInquiry::where('uuid', $uuid)
            ->where('landlord_id', auth()->id())
            ->with(['unit.property', 'user'])
            ->firstOrFail();
    }
}