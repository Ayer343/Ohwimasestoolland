<?php
// app/Http/Controllers/Sanitation/WorkerController.php

namespace App\Http\Controllers\Sanitation;

use App\Http\Controllers\Controller;
use App\Models\SanitationWorker;
use App\Models\SanitationPersonnel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class WorkerController extends Controller
{
    /**
     * Display a listing of workers.
     */
    public function index(Request $request)
    {
        $query = SanitationWorker::with('supervisor');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('supervisor_id')) {
            $query->where('supervisor_id', $request->supervisor_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $workers = $query->orderBy('created_at', 'desc')->paginate(20);

        // ✅ Supervisors for the filter dropdown — include ALL supervisor-tier roles
        $supervisors = SanitationPersonnel::query()
            ->whereIn('role', SanitationPersonnel::SUPERVISOR_ROLES)
            ->orderBy('first_name')
            ->get();

        return view('sanitation.workers.index', compact('workers', 'supervisors'));
    }

    /**
     * Show the form for creating a new worker.
     */
    public function create()
    {
        // ✅ Active supervisors of any supervisor-tier role
        $supervisors = SanitationPersonnel::query()
            ->whereIn('role', SanitationPersonnel::SUPERVISOR_ROLES)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get();

        return view('sanitation.workers.create', compact('supervisors'));
    }

    /**
     * Store a newly created worker.
     */
    public function store(Request $request)
    {
        // ✅ Single source of truth for the personnel table name
        $personnelTable = SanitationPersonnel::TABLE; // 'sanitation_personnels'
        $personnelKey   = (new SanitationPersonnel)->getKeyName(); // 'id'

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',

            'phone' => [
                'required',
                'string',
                'max:15',
                Rule::unique(SanitationWorker::class === null ? '' : (new SanitationWorker)->getTable(), 'phone'),
            ],

            'email' => [
                'nullable',
                'email',
                Rule::unique((new SanitationWorker)->getTable(), 'email'),
            ],

            // ✅ Was: 'nullable|exists:sanitation_personnel,id'  ← crashed here
            'supervisor_id' => [
                'nullable',
                'integer',
                Rule::exists($personnelTable, $personnelKey)
                    ->where(fn ($q) => $q->where('status', 'active')),
            ],

            'status'             => 'required|in:active,inactive',
            'address'            => 'nullable|string',
            'emergency_contact'  => 'nullable|string',
            'hire_date'          => 'nullable|date',
            'skills'             => 'nullable|array',
            'certifications'     => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $worker = SanitationWorker::create([
                'first_name'        => $request->first_name,
                'last_name'         => $request->last_name,
                'phone'             => $request->phone,
                'email'             => $request->email,
                'supervisor_id'     => $request->supervisor_id,
                'status'            => $request->status,
                'address'           => $request->address,
                'emergency_contact' => $request->emergency_contact,
                'hire_date'         => $request->hire_date,
                'skills'            => $request->skills,
                'certifications'    => $request->certifications,
            ]);

            DB::commit();

            return redirect()->route('sanitation.workers.show', $worker)
                ->with('success', 'Worker created successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create worker: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'input'   => $request->except(['_token', '_method']),
            ]);
            return redirect()->back()
                ->with('error', 'Failed to create worker: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified worker.
     */
    public function show(SanitationWorker $worker)
    {
        $worker->load('supervisor');

        $assignedRequests = $worker->assignedRequests()
            ->with(['property', 'requestedBy'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return view('sanitation.workers.show', compact('worker', 'assignedRequests'));
    }

    /**
     * Show the form for editing the specified worker.
     */
    public function edit(SanitationWorker $worker)
    {
        // ✅ Active supervisors of any supervisor-tier role
        $supervisors = SanitationPersonnel::query()
            ->whereIn('role', SanitationPersonnel::SUPERVISOR_ROLES)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get();

        return view('sanitation.workers.edit', compact('worker', 'supervisors'));
    }

    /**
     * Update the specified worker.
     */
    public function update(Request $request, SanitationWorker $worker)
    {
        // ✅ Single source of truth for the personnel table name
        $personnelTable = SanitationPersonnel::TABLE;
        $personnelKey   = (new SanitationPersonnel)->getKeyName();

        $workerTable = (new SanitationWorker)->getTable();

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',

            'phone' => [
                'required',
                'string',
                'max:15',
                Rule::unique($workerTable, 'phone')->ignore($worker->id),
            ],

            'email' => [
                'nullable',
                'email',
                Rule::unique($workerTable, 'email')->ignore($worker->id),
            ],

            // ✅ Was: 'nullable|exists:sanitation_personnel,id'  ← crashed here
            'supervisor_id' => [
                'nullable',
                'integer',
                Rule::exists($personnelTable, $personnelKey)
                    ->where(fn ($q) => $q->where('status', 'active')),
            ],

            'status'            => 'required|in:active,inactive',
            'address'           => 'nullable|string',
            'emergency_contact' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $worker->update([
                'first_name'        => $request->first_name,
                'last_name'         => $request->last_name,
                'phone'             => $request->phone,
                'email'             => $request->email,
                'supervisor_id'     => $request->supervisor_id,
                'status'            => $request->status,
                'address'           => $request->address,
                'emergency_contact' => $request->emergency_contact,
            ]);

            DB::commit();

            return redirect()->route('sanitation.workers.show', $worker)
                ->with('success', 'Worker updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update worker: ' . $e->getMessage(), [
                'worker_id' => $worker->id,
                'user_id'   => auth()->id(),
                'input'     => $request->except(['_token', '_method']),
            ]);
            return redirect()->back()
                ->with('error', 'Failed to update worker: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified worker.
     */
    public function destroy(SanitationWorker $worker)
    {
        try {
            DB::beginTransaction();

            // Check if worker has active assignments
            $hasActiveAssignments = $worker->assignedRequests()
                ->whereIn('status', ['assigned', 'en_route', 'arrived', 'in_progress'])
                ->exists();

            if ($hasActiveAssignments) {
                DB::rollBack();
                return redirect()->back()
                    ->with('error', 'Cannot delete worker with active assignments.');
            }

            $worker->delete();

            DB::commit();

            return redirect()->route('sanitation.workers.index')
                ->with('success', 'Worker deleted successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete worker: ' . $e->getMessage(), [
                'worker_id' => $worker->id,
                'user_id'   => auth()->id(),
            ]);
            return redirect()->back()
                ->with('error', 'Failed to delete worker: ' . $e->getMessage());
        }
    }
}