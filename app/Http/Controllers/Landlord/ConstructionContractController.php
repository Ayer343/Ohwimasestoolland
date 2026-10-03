<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\ConstructionContract;
use App\Models\ConstructionMilestone;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ConstructionContractSubmitted;
use App\Notifications\ConstructionContractApproved;
use App\Notifications\ConstructionContractRejected;

class ConstructionContractController extends Controller
{
    /**
     * Show the form to create a new construction contract
     */
    public function create(Request $request)
{
    $propertyId = $request->get('property_id');
    $property = null;
    
    if ($propertyId) {
        $property = Property::where('id', $propertyId)
            ->where('landlord_id', auth()->id())
            ->first();
    }
    
    // ✅ DEBUG: Get ALL properties first
    $allProperties = Property::where('landlord_id', auth()->id())->get();
    
    // ✅ DEBUG: Log what we have
    \Log::info('All properties for landlord:', [
        'landlord_id' => auth()->id(),
        'count' => $allProperties->count(),
        'properties' => $allProperties->map(function($p) {
            return [
                'id' => $p->id,
                'name' => $p->property_name,
                'status' => $p->status,
                'construction_status' => $p->construction_status,
                'property_type_id' => $p->property_type_id,
                'has_plans' => $p->has_plans,
                'has_construction_docs' => !empty($p->construction_documents),
            ];
        })->toArray()
    ]);
    
    // Get eligible properties
    $properties = Property::where('landlord_id', auth()->id())
        ->whereNotIn('status', ['active', 'completed'])  // ← FIX: Include ALL non-completed
        ->orderBy('property_name')
        ->get();
    
    // ✅ DEBUG: Log eligible properties
    \Log::info('Eligible properties for contracts:', [
        'count' => $properties->count(),
        'properties' => $properties->pluck('property_name', 'id')->toArray()
    ]);
    
    return view('landlord.construction.contract.create', compact('property', 'properties'));
}

    /**
     * Store a new construction contract
     */
    public function store(Request $request)
    {
        $validator = $this->validateContract($request);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();

        try {
            // Handle work scope - convert array to JSON
            $workScope = null;
            if ($request->has('work_scope_items')) {
                $workScope = array_filter($request->work_scope_items, function($item) {
                    return !empty(trim($item));
                });
                $workScope = !empty($workScope) ? json_encode(array_values($workScope)) : null;
            }

            // Create the contract
            $contract = ConstructionContract::create([
                'title' => $request->title,
                'description' => $request->description,
                'landlord_id' => auth()->id(),
                'property_id' => $request->property_id,
                'contractor_type' => $request->contractor_type,
                'contractor_name' => $request->contractor_name,
                'contractor_phone' => $request->contractor_phone,
                'contractor_email' => $request->contractor_email,
                'contractor_address' => $request->contractor_address,
                'company_registration_number' => $request->company_registration_number,
                'company_tin' => $request->company_tin,
                'contract_amount' => $request->contract_amount,
                'contract_start_date' => $request->contract_start_date,
                'estimated_completion_date' => $request->estimated_completion_date,
                'work_scope' => $workScope,
                'status' => ConstructionContract::STATUS_PENDING_APPROVAL,
            ]);

            // Log activity with try-catch
            try {
                if (method_exists($contract, 'logActivity')) {
                    $contract->logActivity('created', 'Contract submitted for approval');
                }
            } catch (\Exception $e) {
                Log::warning('Failed to log contract activity: ' . $e->getMessage());
            }

            // Notify admins
            $this->notifyAdmins($contract);

            DB::commit();

            return redirect()->route('landlord.construction.contract.show', $contract)
                ->with('success', 'Construction contract submitted successfully! It is pending admin approval.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create construction contract: ' . $e->getMessage(), [
                'landlord_id' => auth()->id(),
                'request' => $request->except(['_token', '_method'])
            ]);

            return redirect()->back()
                ->with('error', 'Failed to submit contract. Please try again.')
                ->withInput();
        }
    }

    /**
     * Show a specific contract
     */
    public function show(ConstructionContract $contract)
    {
        // ✅ Manual authorization check
        if ($contract->landlord_id !== auth()->id()) {
            abort(403, 'Unauthorized to view this contract.');
        }
        
        // Load only the relationships that exist
        $contract->load(['milestones', 'activityLogs.user']);
        
        return view('landlord.construction.contract.show', compact('contract'));
    }

    /**
     * Edit a contract (only if pending approval)
     */
    public function edit(ConstructionContract $contract)
    {
        // ✅ Manual authorization check
        if ($contract->landlord_id !== auth()->id()) {
            abort(403, 'Unauthorized to edit this contract.');
        }
        
        if (!in_array($contract->status, [ConstructionContract::STATUS_DRAFT, ConstructionContract::STATUS_PENDING_APPROVAL])) {
            return redirect()->route('landlord.construction.contract.show', $contract)
                ->with('error', 'This contract cannot be edited in its current state.');
        }
        
        // Get properties for the dropdown
        $properties = Property::where('landlord_id', auth()->id())
            ->where(function($query) {
                $query->where('status', 'vacant')
                    ->orWhere('status', 'under_construction');
            })
            ->orderBy('property_name')
            ->get();
        
        return view('landlord.construction.contract.edit', compact('contract', 'properties'));
    }

    /**
     * Update a contract
     */
    public function update(Request $request, ConstructionContract $contract)
    {
        // ✅ Manual authorization check
        if ($contract->landlord_id !== auth()->id()) {
            abort(403, 'Unauthorized to update this contract.');
        }
        
        if (!in_array($contract->status, [ConstructionContract::STATUS_DRAFT, ConstructionContract::STATUS_PENDING_APPROVAL])) {
            return redirect()->route('landlord.construction.contract.show', $contract)
                ->with('error', 'This contract cannot be updated in its current state.');
        }

        $validator = $this->validateContract($request, $contract->id);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();

        try {
            // Handle work scope - convert array to JSON
            $workScope = null;
            if ($request->has('work_scope_items')) {
                $workScope = array_filter($request->work_scope_items, function($item) {
                    return !empty(trim($item));
                });
                $workScope = !empty($workScope) ? json_encode(array_values($workScope)) : null;
            }

            $contract->update([
                'title' => $request->title,
                'description' => $request->description,
                'contractor_type' => $request->contractor_type,
                'contractor_name' => $request->contractor_name,
                'contractor_phone' => $request->contractor_phone,
                'contractor_email' => $request->contractor_email,
                'contractor_address' => $request->contractor_address,
                'company_registration_number' => $request->company_registration_number,
                'company_tin' => $request->company_tin,
                'contract_amount' => $request->contract_amount,
                'contract_start_date' => $request->contract_start_date,
                'estimated_completion_date' => $request->estimated_completion_date,
                'work_scope' => $workScope,
            ]);

            try {
                if (method_exists($contract, 'logActivity')) {
                    $contract->logActivity('updated', 'Contract updated by landlord');
                }
            } catch (\Exception $e) {
                Log::warning('Failed to log contract activity: ' . $e->getMessage());
            }

            DB::commit();

            return redirect()->route('landlord.construction.contract.show', $contract)
                ->with('success', 'Contract updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update construction contract: ' . $e->getMessage(), [
                'contract_id' => $contract->id,
                'landlord_id' => auth()->id()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to update contract. Please try again.')
                ->withInput();
        }
    }

    /**
     * List all contracts for the landlord
     */
    public function index(Request $request)
    {
        $query = ConstructionContract::where('landlord_id', auth()->id());
        
        // Apply filters
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('contractor_name') && $request->contractor_name) {
            $query->where('contractor_name', 'LIKE', '%' . $request->contractor_name . '%');
        }
        
        if ($request->has('date_from') && $request->date_from) {
            $query->where('created_at', '>=', $request->date_from);
        }
        
        if ($request->has('date_to') && $request->date_to) {
            $query->where('created_at', '<=', $request->date_to);
        }
        
        $contracts = $query->latest()->paginate(15);
        
        $stats = [
            'total' => ConstructionContract::where('landlord_id', auth()->id())->count(),
            'pending' => ConstructionContract::where('landlord_id', auth()->id())
                ->where('status', ConstructionContract::STATUS_PENDING_APPROVAL)->count(),
            'approved' => ConstructionContract::where('landlord_id', auth()->id())
                ->where('status', ConstructionContract::STATUS_APPROVED)->count(),
            'in_progress' => ConstructionContract::where('landlord_id', auth()->id())
                ->where('status', ConstructionContract::STATUS_IN_PROGRESS)->count(),
            'completed' => ConstructionContract::where('landlord_id', auth()->id())
                ->where('status', ConstructionContract::STATUS_COMPLETED)->count(),
        ];
        
        return view('landlord.construction.contract.index', compact('contracts', 'stats'));
    }

    /**
     * Validate contract data
     */
    private function validateContract(Request $request, $contractId = null)
    {
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'property_id' => 'required|exists:properties,id',
            'contractor_type' => 'required|in:company,individual',
            'contractor_name' => 'required|string|max:255',
            'contractor_phone' => 'required|string|max:20',
            'contractor_email' => 'nullable|email|max:255',
            'contractor_address' => 'nullable|string|max:500',
            'company_registration_number' => 'nullable|string|max:100',
            'company_tin' => 'nullable|string|max:100',
            'contract_amount' => 'nullable|numeric|min:0',
            'contract_start_date' => 'required|date|after_or_equal:today',
            'estimated_completion_date' => 'required|date|after:contract_start_date',
            'work_scope_items' => 'nullable|array',
            'work_scope_items.*' => 'nullable|string|max:500',
        ];

        $messages = [
            'property_id.required' => 'Please select a property for this contract.',
            'contractor_name.required' => 'Please provide the contractor name.',
            'contractor_phone.required' => 'Please provide the contractor phone number.',
            'contract_start_date.required' => 'Please select a start date.',
            'estimated_completion_date.required' => 'Please select an estimated completion date.',
            'estimated_completion_date.after' => 'The completion date must be after the start date.',
        ];

        return Validator::make($request->all(), $rules, $messages);
    }

    /**
     * Notify admins about new contract
     */
    private function notifyAdmins(ConstructionContract $contract)
    {
        $admins = User::whereIn('type', [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN])
            ->where('status', User::STATUS_ACTIVE)
            ->get();

        foreach ($admins as $admin) {
            try {
                $admin->notify(new ConstructionContractSubmitted($contract));
            } catch (\Exception $e) {
                Log::error('Failed to notify admin about contract: ' . $e->getMessage(), [
                    'admin_id' => $admin->id,
                    'contract_id' => $contract->id
                ]);
            }
        }
    }
}