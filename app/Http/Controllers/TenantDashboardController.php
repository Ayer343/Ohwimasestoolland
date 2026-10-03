<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\PropertyUnit;
use App\Models\RentalAgreement;
use App\Models\TenantInvoice;
use App\Models\Payment;
use App\Models\MaintenanceRequest;
use App\Models\Notification;
use App\Models\Property;
use Carbon\Carbon;

class TenantDashboardController extends Controller
{
    /**
     * Tenant Dashboard - Main view
     * Route: tenant.dashboard
     * URL: /tenant/dashboard
     */
    public function index()
    {
        $tenant = Auth::user();
        
        // Get the tenant's assigned property unit
        $unit = PropertyUnit::where('tenant_id', $tenant->id)
            ->where('tenant_status', PropertyUnit::TENANT_STATUS_APPROVED)
            ->first();
        
        // Get property information if unit exists
        $property = null;
        $activeLease = null;
        
        if ($unit) {
            $property = Property::find($unit->property_id);
            // Get active lease using RentalAgreement model
            $activeLease = RentalAgreement::where('unit_id', $unit->id)
                ->where('status', 'active')
                ->first();
        }
        
        // Get statistics for dashboard
        $stats = $this->getTenantStats($tenant, $unit, $activeLease);
        
        // Get recent invoices (using correct column names)
        $recentInvoices = $this->getRecentInvoices($tenant, $unit);
        
        // Get recent maintenance requests
        $recentMaintenance = $this->getRecentMaintenanceRequests($tenant, $unit);
        
        // Get upcoming lease end date
        $upcomingLease = $this->getUpcomingLease($activeLease);
        
        // Get unread notifications count
        $unreadNotifications = $tenant->unreadNotifications()->count();
        
        // Get pending lease signature status
        $pendingLeaseSignature = $this->getPendingLeaseSignature($tenant, $unit);
        
        // Get notifications
        $notifications = $tenant->notifications()->latest()->limit(10)->get();
        
        return view('tenant.dashboard', compact(
            'tenant',
            'unit',
            'property',
            'activeLease',
            'stats',
            'recentInvoices',
            'recentMaintenance',
            'upcomingLease',
            'unreadNotifications',
            'pendingLeaseSignature',
            'notifications'
        ));
    }
    
    /**
     * Get dashboard statistics (AJAX)
     * Route: tenant.dashboard.stats
     * URL: /tenant/dashboard/stats
     */
    public function getStats(Request $request)
    {
        $tenant = Auth::user();
        $unit = PropertyUnit::where('tenant_id', $tenant->id)
            ->where('tenant_status', PropertyUnit::TENANT_STATUS_APPROVED)
            ->first();
        
        $activeLease = null;
        if ($unit) {
            $activeLease = RentalAgreement::where('unit_id', $unit->id)
                ->where('status', 'active')
                ->first();
        }
        
        $stats = $this->getTenantStats($tenant, $unit, $activeLease);
        
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        }
        
        return redirect()->route('tenant.dashboard');
    }
    
    /**
     * Get chart data for dashboard (AJAX)
     * Route: tenant.dashboard.charts
     * URL: /tenant/dashboard/charts
     */
    public function getChartDataAjax(Request $request)
    {
        $tenant = Auth::user();
        $unit = PropertyUnit::where('tenant_id', $tenant->id)
            ->where('tenant_status', PropertyUnit::TENANT_STATUS_APPROVED)
            ->first();
        
        if (!$unit) {
            return response()->json([
                'success' => false,
                'message' => 'No unit assigned'
            ]);
        }
        
        $type = $request->get('type', 'payment');
        $year = $request->get('year', date('Y'));
        $period = $request->get('period', 'monthly');
        
        if ($type === 'payment') {
            $paymentData = $this->getPaymentChartData($unit, $year);
            return response()->json([
                'success' => true,
                'labels' => $paymentData['labels'],
                'data' => $paymentData['data']
            ]);
        } elseif ($type === 'maintenance') {
            $maintenanceData = $this->getMaintenanceChartData($unit, $period);
            return response()->json([
                'success' => true,
                'labels' => $maintenanceData['labels'],
                'data' => $maintenanceData['data']
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Invalid chart type'
        ]);
    }
    
    /**
     * Refresh dashboard data (AJAX)
     * Route: tenant.dashboard.refresh
     * URL: /tenant/dashboard/refresh
     */
    public function refreshDashboard(Request $request)
    {
        $tenant = Auth::user();
        $unit = PropertyUnit::where('tenant_id', $tenant->id)
            ->where('tenant_status', PropertyUnit::TENANT_STATUS_APPROVED)
            ->first();
        
        $activeLease = null;
        if ($unit) {
            $activeLease = RentalAgreement::where('unit_id', $unit->id)
                ->where('status', 'active')
                ->first();
        }
        
        $stats = $this->getTenantStats($tenant, $unit, $activeLease);
        $recentInvoices = $this->getRecentInvoices($tenant, $unit);
        $recentMaintenance = $this->getRecentMaintenanceRequests($tenant, $unit);
        $upcomingLease = $this->getUpcomingLease($activeLease);
        $unreadNotifications = $tenant->unreadNotifications()->count();
        $pendingLeaseSignature = $this->getPendingLeaseSignature($tenant, $unit);
        
        // Get financial summary
        $financialSummary = $this->getFinancialSummary($unit);
        
        // Get maintenance summary
        $maintenanceSummary = $this->getMaintenanceSummary($unit);
        
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'financial_summary' => $financialSummary,
                    'maintenance_summary' => $maintenanceSummary,
                    'recent_invoices' => $recentInvoices,
                    'recent_maintenance' => $recentMaintenance,
                    'upcoming_lease' => $upcomingLease,
                    'unread_notifications' => $unreadNotifications,
                    'pending_lease_signature' => $pendingLeaseSignature,
                    'last_refresh' => now()->toDateTimeString()
                ]
            ]);
        }
        
        return redirect()->route('tenant.dashboard');
    }
    
    /**
     * Export dashboard data
     * Route: tenant.dashboard.export
     * URL: /tenant/dashboard/export
     */
    public function exportDashboard(Request $request)
    {
        $tenant = Auth::user();
        $unit = PropertyUnit::where('tenant_id', $tenant->id)
            ->where('tenant_status', PropertyUnit::TENANT_STATUS_APPROVED)
            ->first();
        
        $activeLease = null;
        if ($unit) {
            $activeLease = RentalAgreement::where('unit_id', $unit->id)
                ->where('status', 'active')
                ->first();
        }
        
        $format = $request->get('format', 'csv');
        
        $data = [
            'tenant_name' => $tenant->name,
            'tenant_email' => $tenant->email,
            'tenant_phone' => $tenant->phone,
            'export_date' => now()->toDateTimeString(),
            'unit_details' => $unit ? [
                'unit_number' => $unit->unit_number,
                'property_name' => $unit->property->property_name ?? 'N/A',
                'monthly_rent' => $unit->current_rent_amount,
                'security_deposit' => $unit->security_deposit,
                'lease_status' => $activeLease ? $activeLease->status : 'No active lease',
                'lease_start_date' => $activeLease && $activeLease->start_date ? $activeLease->start_date->format('Y-m-d') : null,
                'lease_end_date' => $activeLease && $activeLease->end_date ? $activeLease->end_date->format('Y-m-d') : null,
            ] : null,
            'statistics' => $this->getTenantStats($tenant, $unit, $activeLease),
            'invoices' => $this->getAllInvoices($unit),
            'payments' => $this->getAllPayments($unit),
            'maintenance_requests' => $this->getAllMaintenanceRequests($unit),
        ];
        
        if ($format === 'json') {
            return response()->json($data);
        }
        
        // Export as CSV
        $fileName = "tenant_dashboard_{$tenant->id}_" . now()->format('Ymd_His') . ".csv";
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$fileName}",
        ];
        
        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            // Write headers
            fputcsv($file, ['Section', 'Field', 'Value']);
            fputcsv($file, ['', '', '']);
            
            // Write tenant info
            fputcsv($file, ['TENANT INFORMATION', '', '']);
            fputcsv($file, ['', 'Name', $data['tenant_name']]);
            fputcsv($file, ['', 'Email', $data['tenant_email']]);
            fputcsv($file, ['', 'Phone', $data['tenant_phone']]);
            fputcsv($file, ['', 'Export Date', $data['export_date']]);
            fputcsv($file, ['', '', '']);
            
            // Write unit details
            if ($data['unit_details']) {
                fputcsv($file, ['UNIT DETAILS', '', '']);
                foreach ($data['unit_details'] as $key => $value) {
                    fputcsv($file, ['', ucfirst(str_replace('_', ' ', $key)), $value ?? 'N/A']);
                }
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
    
    /**
     * Get dashboard widgets configuration
     * Route: tenant.dashboard.widgets
     * URL: /tenant/dashboard/widgets
     */
    public function getWidgets(Request $request)
    {
        $tenant = Auth::user();
        
        // Get saved widget preferences or default
        $defaultWidgets = [
            'stats' => ['enabled' => true, 'position' => 1],
            'lease_status' => ['enabled' => true, 'position' => 2],
            'property_info' => ['enabled' => true, 'position' => 3],
            'financial_summary' => ['enabled' => true, 'position' => 4],
            'recent_invoices' => ['enabled' => true, 'position' => 5],
            'recent_maintenance' => ['enabled' => true, 'position' => 6],
            'charts' => ['enabled' => true, 'position' => 7],
            'quick_actions' => ['enabled' => true, 'position' => 8],
            'important_info' => ['enabled' => true, 'position' => 9],
            'notifications' => ['enabled' => true, 'position' => 10],
        ];
        
        // Get from tenant preferences if saved
        $widgets = json_decode($tenant->dashboard_widgets ?? '{}', true);
        $widgets = array_merge($defaultWidgets, $widgets);
        
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'widgets' => $widgets
            ]);
        }
        
        return view('tenant.dashboard.widgets', compact('widgets'));
    }
    
    /**
     * Update dashboard widgets configuration
     * Route: tenant.dashboard.widgets.update
     * URL: /tenant/dashboard/widgets/update
     */
    public function updateWidgets(Request $request)
    {
        $tenant = Auth::user();
        
        $validated = $request->validate([
            'widgets' => 'required|array',
            'widgets.*' => 'boolean'
        ]);
        
        $tenant->dashboard_widgets = json_encode($validated['widgets']);
        $tenant->save();
        
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Widgets updated successfully'
            ]);
        }
        
        return redirect()->route('tenant.dashboard')
            ->with('success', 'Dashboard layout updated successfully');
    }
    
    /**
     * Reset dashboard widgets to default
     * Route: tenant.dashboard.widgets.reset
     * URL: /tenant/dashboard/widgets/reset
     */
    public function resetWidgets(Request $request)
    {
        $tenant = Auth::user();
        $tenant->dashboard_widgets = null;
        $tenant->save();
        
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Widgets reset to default'
            ]);
        }
        
        return redirect()->route('tenant.dashboard')
            ->with('success', 'Dashboard layout reset to default');
    }
    
    /**
     * Get financial summary data
     * Route: tenant.financial.summary
     * URL: /tenant/financial-summary
     */
    public function getFinancialSummaryData(Request $request)
    {
        $tenant = Auth::user();
        $unit = PropertyUnit::where('tenant_id', $tenant->id)
            ->where('tenant_status', PropertyUnit::TENANT_STATUS_APPROVED)
            ->first();
        
        $summary = $this->getFinancialSummary($unit);
        
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($summary);
        }
        
        return redirect()->route('tenant.dashboard');
    }
    
    /**
     * Get maintenance summary data
     * Route: tenant.maintenance.summary
     * URL: /tenant/maintenance-summary
     */
    public function getMaintenanceSummaryData(Request $request)
    {
        $tenant = Auth::user();
        $unit = PropertyUnit::where('tenant_id', $tenant->id)
            ->where('tenant_status', PropertyUnit::TENANT_STATUS_APPROVED)
            ->first();
        
        $summary = $this->getMaintenanceSummary($unit);
        
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($summary);
        }
        
        return redirect()->route('tenant.dashboard');
    }
    
    /**
     * Get lease status for tenant
     * Route: tenant.lease.status
     * URL: /tenant/lease-status
     */
    public function getLeaseStatus(Request $request)
    {
        $tenant = Auth::user();
        $unit = PropertyUnit::where('tenant_id', $tenant->id)
            ->where('tenant_status', PropertyUnit::TENANT_STATUS_APPROVED)
            ->first();
        
        if (!$unit) {
            return response()->json([
                'success' => false,
                'has_lease' => false,
                'message' => 'No unit assigned'
            ]);
        }
        
        // Get the most recent lease agreement
        $lease = RentalAgreement::where('unit_id', $unit->id)
            ->where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc')
            ->first();
        
        if (!$lease) {
            return response()->json([
                'success' => true,
                'has_lease' => false,
                'message' => 'No lease agreement found'
            ]);
        }
        
        $status = [
            'has_lease' => true,
            'lease_id' => $lease->id,
            'status' => $lease->status,
            'status_label' => $this->getLeaseStatusLabel($lease->status),
            'start_date' => $lease->start_date ? $lease->start_date->format('Y-m-d') : null,
            'end_date' => $lease->end_date ? $lease->end_date->format('Y-m-d') : null,
            'monthly_rent' => (float) $lease->monthly_rent,
            'security_deposit' => (float) $lease->security_deposit,
            'landlord_signed' => !is_null($lease->landlord_signed_at),
            'landlord_signed_at' => $lease->landlord_signed_at ? $lease->landlord_signed_at->format('Y-m-d H:i:s') : null,
            'tenant_signed' => !is_null($lease->tenant_signed_at),
            'tenant_signed_at' => $lease->tenant_signed_at ? $lease->tenant_signed_at->format('Y-m-d H:i:s') : null,
            'needs_signature' => $lease->status === 'pending_signature' && is_null($lease->tenant_signed_at),
            'can_sign' => $lease->status === 'pending_signature' && is_null($lease->tenant_signed_at) && !is_null($lease->landlord_signed_at),
            'signature_url' => route('tenant.property-units.lease-details', [$unit->id, $lease->id]),
            'days_remaining' => $this->getLeaseDaysRemaining($lease),
        ];
        
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'lease' => $status
            ]);
        }
        
        return redirect()->route('tenant.dashboard');
    }
    
    // ==================== PRIVATE HELPER METHODS ====================
    
   private function getTenantStats($tenant, $unit = null, $activeLease = null)
{
    if (!$unit) {
        return [
            'has_unit' => false,
            'outstanding_balance' => 0,
            'pending_maintenance' => 0,
            'active_lease' => false,
            'lease_status' => 'no_lease',
            'days_since_joined' => $tenant->created_at->diffInDays(now())
        ];
    }
    
    // Get community dues outstanding balance
    $outstandingBalance = $this->getOutstandingBalance($tenant, $unit);
    
    $pendingMaintenance = MaintenanceRequest::where('unit_id', $unit->id)
        ->where('status', 'pending')->count();
    
    $leaseStatus = $activeLease ? $activeLease->status : 'no_lease';
    
    // Check if there's a pending signature lease
    $pendingSignatureLease = RentalAgreement::where('unit_id', $unit->id)
        ->where('status', 'pending_signature')
        ->whereNull('tenant_signed_at')
        ->exists();
    
    return [
        'has_unit' => true,
        'unit_number' => $unit->unit_number,
        'property_name' => $unit->property->property_name ?? 'N/A',
        'outstanding_balance' => $outstandingBalance,
        'pending_maintenance' => $pendingMaintenance,
        'active_lease' => !is_null($activeLease),
        'lease_status' => $leaseStatus,
        'has_pending_signature' => $pendingSignatureLease,
        'monthly_rent' => (float) ($activeLease->monthly_rent ?? $unit->current_rent_amount ?? 0),
        'security_deposit' => (float) ($activeLease->security_deposit ?? $unit->security_deposit ?? 0),
        'deposit_paid' => (float) ($activeLease->deposit_collected_so_far ?? 0),
        'deposit_remaining' => (float) (($activeLease->security_deposit ?? 0) - ($activeLease->deposit_collected_so_far ?? 0)),
        'days_since_joined' => $tenant->created_at->diffInDays(now()),
        'lease_start_date' => $activeLease && $activeLease->start_date ? $activeLease->start_date->format('Y-m-d') : null,
        'lease_end_date' => $activeLease && $activeLease->end_date ? $activeLease->end_date->format('Y-m-d') : null,
    ];
}
    
    /**
 * Get outstanding balance (community dues)
 */
private function getOutstandingBalance($tenant, $unit = null)
{
    if (!$tenant) {
        return 0;
    }
    
    try {
        // Get all unpaid invoices for this tenant
        $outstandingInvoices = TenantInvoice::where('tenant_id', $tenant->id)
            ->whereIn('status', ['pending', 'overdue'])
            ->get();
        
        $totalOutstanding = 0;
        foreach ($outstandingInvoices as $invoice) {
            $totalOutstanding += ($invoice->community_dues ?? 0) + ($invoice->additional_charges ?? 0);
        }
        
        return $totalOutstanding;
        
    } catch (\Exception $e) {
        \Log::error('Error calculating outstanding balance: ' . $e->getMessage());
        return 0;
    }
}
    
    /**
     * Get financial summary
     */
    private function getFinancialSummary($unit = null)
    {
        if (!$unit) {
            return [
                'total_paid' => 0,
                'total_outstanding' => 0,
                'collection_rate' => 0,
                'total_invoices' => 0,
                'paid_invoices' => 0
            ];
        }
        
        $totalPaid = (float) Payment::where('unit_id', $unit->id)
            ->where('status', 'completed')
            ->sum('amount');
        
        $totalOutstanding = $this->getOutstandingBalance($unit);
        
        $totalInvoiced = $totalPaid + $totalOutstanding;
        $collectionRate = $totalInvoiced > 0 ? ($totalPaid / $totalInvoiced) * 100 : 0;
        
        // Try to get invoice counts from TenantInvoice
        $totalInvoices = 0;
        $paidInvoices = 0;
        
        try {
            $invoiceClass = 'App\\Models\\TenantInvoice';
            if (class_exists($invoiceClass)) {
                $possibleKeys = ['property_unit_id', 'rental_agreement_id', 'unit_id', 'property_unit_id'];
                
                foreach ($possibleKeys as $key) {
                    try {
                        $totalInvoices = TenantInvoice::where($key, $unit->id)->count();
                        $paidInvoices = TenantInvoice::where($key, $unit->id)
                            ->where('status', 'paid')
                            ->count();
                        
                        if ($totalInvoices > 0) {
                            break;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        } catch (\Exception $e) {
            // Use default values
        }
        
        return [
            'total_paid' => $totalPaid,
            'total_outstanding' => $totalOutstanding,
            'collection_rate' => round($collectionRate, 2),
            'total_invoices' => $totalInvoices,
            'paid_invoices' => $paidInvoices,
            'unpaid_invoices' => $totalInvoices - $paidInvoices
        ];
    }
    
    /**
     * Get maintenance summary
     */
    private function getMaintenanceSummary($unit = null)
    {
        if (!$unit) {
            return [
                'total_requests' => 0,
                'pending_requests' => 0,
                'urgent_requests' => 0,
                'completion_rate' => 0,
                'avg_resolution_days' => 0,
                'total_cost' => 0
            ];
        }
        
        $totalRequests = MaintenanceRequest::where('unit_id', $unit->id)->count();
        $pendingRequests = MaintenanceRequest::where('unit_id', $unit->id)
            ->where('status', 'pending')->count();
        $urgentRequests = MaintenanceRequest::where('unit_id', $unit->id)
            ->where('priority', 'urgent')
            ->where('status', '!=', 'completed')
            ->count();
        $completedRequests = MaintenanceRequest::where('unit_id', $unit->id)
            ->where('status', 'completed')->count();
        
        $completionRate = $totalRequests > 0 ? ($completedRequests / $totalRequests) * 100 : 0;
        
        // Calculate average resolution time
        $avgResolutionDays = $this->getAverageResolutionTime($unit);
        
        $totalCost = (float) MaintenanceRequest::where('unit_id', $unit->id)
            ->where('status', 'completed')
            ->sum('cost');
        
        return [
            'total_requests' => $totalRequests,
            'pending_requests' => $pendingRequests,
            'urgent_requests' => $urgentRequests,
            'completion_rate' => round($completionRate, 2),
            'avg_resolution_days' => $avgResolutionDays,
            'total_cost' => $totalCost
        ];
    }
    
    /**
 * Get recent invoices
 */
private function getRecentInvoices($tenant, $unit = null, $limit = 5)
{
    if (!$unit) {
        return collect([]);
    }
    
    // Get invoices for this tenant (not by unit_id since TenantInvoice uses property_unit_id)
    $invoices = TenantInvoice::where('tenant_id', $tenant->id)
        ->orderBy('created_at', 'desc')
        ->limit($limit)
        ->get();
    
    return $invoices->map(function ($invoice) {
        // Calculate total amount from community_dues and additional_charges
        $totalAmount = ($invoice->community_dues ?? 0) + ($invoice->additional_charges ?? 0);
        
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number ?? 'INV-' . $invoice->id,
            'period' => $invoice->period ?? $invoice->created_at->format('Y-m'),
            'amount' => (float) $totalAmount,
            'community_dues' => (float) ($invoice->community_dues ?? 0),
            'additional_charges' => (float) ($invoice->additional_charges ?? 0),
            'status' => $invoice->status ?? 'pending',
            'due_date' => $invoice->due_date ? $invoice->due_date->format('Y-m-d') : null,
            'created_at' => $invoice->created_at->format('Y-m-d'),
            'grace_period_days' => $invoice->grace_period_days ?? 7,
            'is_overdue' => $invoice->isOverdue(),
            'days_overdue' => $invoice->days_overdue,
        ];
    });
}
    
    /**
     * Get all invoices (for export)
     */
    private function getAllInvoices($unit = null)
    {
        if (!$unit) {
            return [];
        }
        
        $possibleKeys = ['property_unit_id', 'rental_agreement_id', 'unit_id', 'property_unit_id'];
        $invoices = collect();
        
        foreach ($possibleKeys as $key) {
            try {
                $invoices = TenantInvoice::where($key, $unit->id)
                    ->orderBy('created_at', 'desc')
                    ->get();
                
                if ($invoices->isNotEmpty()) {
                    break;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        return $invoices->toArray();
    }
    
    /**
     * Get all payments (for export)
     */
    private function getAllPayments($unit = null)
    {
        if (!$unit) {
            return [];
        }
        
        return Payment::where('unit_id', $unit->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }
    
    /**
     * Get all maintenance requests (for export)
     */
    private function getAllMaintenanceRequests($unit = null)
    {
        if (!$unit) {
            return [];
        }
        
        return MaintenanceRequest::where('unit_id', $unit->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }
    
    /**
     * Get recent maintenance requests
     */
    private function getRecentMaintenanceRequests($tenant, $unit = null, $limit = 5)
    {
        if (!$unit) {
            return collect([]);
        }
        
        return MaintenanceRequest::where('unit_id', $unit->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($request) {
                return [
                    'id' => $request->id,
                    'title' => $request->title,
                    'description' => substr($request->description, 0, 100),
                    'status' => $request->status,
                    'priority' => $request->priority,
                    'created_at' => $request->created_at->format('Y-m-d')
                ];
            });
    }
    
    /**
     * Get upcoming lease information
     */
    private function getUpcomingLease($activeLease = null)
    {
        if (!$activeLease || !$activeLease->end_date) {
            return null;
        }
        
        $daysRemaining = now()->diffInDays($activeLease->end_date, false);
        
        return [
            'id' => $activeLease->id,
            'start_date' => $activeLease->start_date ? $activeLease->start_date->format('Y-m-d') : null,
            'end_date' => $activeLease->end_date->format('Y-m-d'),
            'days_remaining' => max(0, $daysRemaining),
            'is_expiring_soon' => $daysRemaining <= 60 && $daysRemaining > 0,
            'is_expired' => $daysRemaining <= 0,
            'can_renew' => $daysRemaining <= 60 && $daysRemaining > 0,
        ];
    }
    
    /**
     * Get pending lease signature status
     */
    private function getPendingLeaseSignature($tenant, $unit = null)
    {
        if (!$unit) {
            return null;
        }
        
        $pendingLease = RentalAgreement::where('unit_id', $unit->id)
            ->where('tenant_id', $tenant->id)
            ->where('status', 'pending_signature')
            ->whereNull('tenant_signed_at')
            ->first();
        
        if (!$pendingLease) {
            return null;
        }
        
        return [
            'lease_id' => $pendingLease->id,
            'lease_type' => $pendingLease->lease_type,
            'monthly_rent' => (float) $pendingLease->monthly_rent,
            'start_date' => $pendingLease->start_date ? $pendingLease->start_date->format('Y-m-d') : null,
            'landlord_signed' => !is_null($pendingLease->landlord_signed_at),
            'signature_url' => route('tenant.property-units.lease-details', [$unit->id, $pendingLease->id]),
            'days_until_expiry' => $pendingLease->created_at->addDays(14)->diffInDays(now(), false),
        ];
    }
    
    /**
     * Get payment chart data
     */
    private function getPaymentChartData($unit, $year)
    {
        $payments = Payment::where('unit_id', $unit->id)
            ->where('status', 'completed')
            ->whereYear('created_at', $year)
            ->select(
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();
        
        $monthlyData = array_fill(1, 12, 0);
        foreach ($payments as $payment) {
            $monthlyData[$payment->month] = (float) $payment->total;
        }
        
        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        return [
            'labels' => $monthNames,
            'data' => array_values($monthlyData)
        ];
    }
    
    /**
     * Get maintenance chart data
     */
    private function getMaintenanceChartData($unit, $period)
    {
        if ($period === 'quarterly') {
            $requests = MaintenanceRequest::where('unit_id', $unit->id)
                ->select(
                    DB::raw('QUARTER(created_at) as quarter'),
                    DB::raw('COUNT(*) as count')
                )
                ->whereYear('created_at', date('Y'))
                ->groupBy('quarter')
                ->orderBy('quarter')
                ->get();
            
            $quarterlyData = array_fill(1, 4, 0);
            foreach ($requests as $request) {
                $quarterlyData[$request->quarter] = $request->count;
            }
            
            return [
                'labels' => ['Q1', 'Q2', 'Q3', 'Q4'],
                'data' => array_values($quarterlyData)
            ];
        } else {
            $requests = MaintenanceRequest::where('unit_id', $unit->id)
                ->select(
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('COUNT(*) as count')
                )
                ->whereYear('created_at', date('Y'))
                ->groupBy('month')
                ->orderBy('month')
                ->get();
            
            $monthlyData = array_fill(1, 12, 0);
            foreach ($requests as $request) {
                $monthlyData[$request->month] = $request->count;
            }
            
            $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            
            return [
                'labels' => $monthNames,
                'data' => array_values($monthlyData)
            ];
        }
    }
    
    /**
     * Get average resolution time for maintenance requests
     */
    private function getAverageResolutionTime($unit)
    {
        $completedRequests = MaintenanceRequest::where('unit_id', $unit->id)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->get();
        
        if ($completedRequests->isEmpty()) {
            return 0;
        }
        
        $totalDays = 0;
        foreach ($completedRequests as $request) {
            $days = $request->created_at->diffInDays($request->completed_at);
            $totalDays += $days;
        }
        
        return round($totalDays / $completedRequests->count(), 1);
    }
    
    /**
     * Get lease status label
     */
    private function getLeaseStatusLabel($status)
    {
        $labels = [
            'draft' => 'Draft',
            'pending_signature' => 'Awaiting Signature',
            'active' => 'Active',
            'completed' => 'Completed',
            'terminated' => 'Terminated',
            'cancelled' => 'Cancelled',
        ];
        
        return $labels[$status] ?? ucfirst($status);
    }
    
    /**
     * Get lease days remaining
     */
    private function getLeaseDaysRemaining($lease)
    {
        if (!$lease->end_date || $lease->status !== 'active') {
            return null;
        }
        
        return max(0, now()->diffInDays($lease->end_date, false));
    }
}