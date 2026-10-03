@extends('layouts.app')

@section('title', 'Invoice Management')

@php
    // Ensure all statistics are properly formatted as integers and arrays
    $totalInvoices = isset($totalInvoices) && is_numeric($totalInvoices) ? (int) $totalInvoices : 0;
    $paidInvoices = isset($paidInvoices) && is_numeric($paidInvoices) ? (int) $paidInvoices : 0;
    $pendingInvoices = isset($pendingInvoices) && is_numeric($pendingInvoices) ? (int) $pendingInvoices : 0;
    $overdueInvoices = isset($overdueInvoices) && is_numeric($overdueInvoices) ? (int) $overdueInvoices : 0;
    $consolidatedInvoices = isset($consolidatedInvoices) && is_numeric($consolidatedInvoices) ? (int) $consolidatedInvoices : 0;
    $activeCoverages = isset($activeCoverages) && is_numeric($activeCoverages) ? (int) $activeCoverages : 0;
    $totalDue = isset($totalDue) && is_numeric($totalDue) ? (float) $totalDue : 0;
    $totalRevenue = isset($totalRevenue) && is_numeric($totalRevenue) ? (float) $totalRevenue : 0;
    $totalPenalties = isset($totalPenalties) && is_numeric($totalPenalties) ? (float) $totalPenalties : 0;
    $collectionRate = isset($collectionRate) && is_numeric($collectionRate) ? (float) $collectionRate : 0;
    
    // Ensure properties is a collection
    $properties = isset($properties) && $properties instanceof \Illuminate\Support\Collection ? $properties : collect();
    
    // Ensure invoices has pagination methods
    $invoices = isset($invoices) && $invoices instanceof \Illuminate\Pagination\LengthAwarePaginator ? $invoices : collect();
    
    // Ensure settings is available
    $settings = isset($settings) ? $settings : \App\Models\SystemSetting::getSettings();
    
    // Helper function to safely count items
    function safeCount($items) {
        if (is_array($items) || $items instanceof \Countable) {
            return count($items);
        }
        return 0;
    }
    
    // Calculate actual paid invoices (excluding consolidated)
    $actualPaidInvoices = $paidInvoices;
    $actualTotalInvoices = $totalInvoices;
    $actualCollectionRate = $actualTotalInvoices > 0 ? round(($actualPaidInvoices / $actualTotalInvoices) * 100, 2) : 0;
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div class="flex items-center mb-4 md:mb-0">
                <i class="fas fa-file-invoice text-2xl mr-3" style="color: var(--primary);"></i>
                <div>
                    <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Landlord Invoice Management</h2>
                    <p class="text-sm" style="color: var(--text-secondary);">Manage monthly dues for all properties</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @php
                    $trashCount = \App\Models\Invoice::onlyTrashed()->count();
                @endphp
                @if($trashCount > 0)
                    <a href="{{ route('invoices.trash') }}" class="btn-trash flex items-center">
                        <i class="fas fa-trash-alt mr-2"></i> Trash 
                        <span class="ml-1 px-2 py-0.5 rounded-full text-xs" style="background-color: var(--warning); color: white;">{{ $trashCount }}</span>
                    </a>
                @endif
                
                <a href="{{ route('admin.tenant-invoices.index') }}" class="btn-tenant flex items-center">
                    <i class="fas fa-users mr-2"></i> Tenant Invoices
                </a>
                <a href="{{ route('invoices.create') }}" class="btn-primary flex items-center">
                    <i class="fas fa-plus mr-2"></i> Generate Manual Invoice
                </a>
                <form action="{{ route('invoices.generate-monthly') }}" method="POST" class="inline" id="generateMonthlyForm">
                    @csrf
                    <button type="submit" class="btn-secondary flex items-center" onclick="return confirmGenerateMonthly()">
                        <i class="fas fa-sync mr-2"></i> Generate Monthly
                    </button>
                </form>
                <form action="{{ route('invoices.mark-overdue') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="btn-warning flex items-center" onclick="return confirm('Mark all pending invoices with past due dates as overdue?')">
                        <i class="fas fa-clock mr-2"></i> Mark Overdue
                    </button>
                </form>
                <a href="{{ route('invoices.index', ['status' => 'consolidated']) }}" class="btn-info flex items-center">
                    <i class="fas fa-layer-group mr-2"></i> View Consolidated
                </a>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline">{{ session('success') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Error Message -->
    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Error!</strong>
        <span class="block sm:inline">{{ session('error') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Auto-generation Status Alert -->
    @if(isset($autoGenerationEnabled) && !$autoGenerationEnabled)
    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle mr-3 text-xl"></i>
                <div>
                    <strong class="font-bold">Auto-generation Disabled</strong>
                    <p class="text-sm mt-1">Monthly invoice auto-generation is currently disabled. Enable it in System Settings or use manual generation.</p>
                </div>
            </div>
            <a href="{{ route('settings.invoice') }}" class="px-3 py-1 bg-yellow-600 text-white rounded text-sm hover:bg-yellow-700">
                Configure
            </a>
        </div>
    </div>
    @endif

    <!-- Generation Details Alert (if available) -->
    @if(session('generation_details') && safeCount(session('generation_details')) > 0)
    <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-start">
            <i class="fas fa-info-circle mr-3 text-xl mt-1"></i>
            <div>
                <strong class="font-bold">Generation Summary</strong>
                <p class="text-sm mt-1">{{ session('success') }}</p>
                @if(safeCount(session('generation_details')) > 0)
                <ul class="text-xs mt-2 list-disc list-inside">
                    @foreach(session('generation_details') as $key => $value)
                        @if(is_numeric($value) && $value > 0)
                        <li>{{ ucfirst(str_replace('_', ' ', $key)) }}: {{ $value }}</li>
                        @endif
                    @endforeach
                </ul>
                @endif
            </div>
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-7 gap-4 mb-6" id="statisticsCards">
        <div class="card p-4" style="background-color: rgba(var(--primary-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Invoices</div>
                    <div class="text-2xl font-semibold" style="color: var(--text-primary);" id="totalInvoices">{{ $totalInvoices }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">(Excludes consolidated)</div>
                </div>
                <i class="fas fa-file-invoice text-2xl opacity-70" style="color: var(--primary);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--success-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Paid</div>
                    <div class="text-2xl font-semibold" style="color: var(--success);" id="paidInvoices">{{ $paidInvoices }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Regular paid invoices</div>
                </div>
                <i class="fas fa-check-circle text-2xl opacity-70" style="color: var(--success);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Pending</div>
                    <div class="text-2xl font-semibold" style="color: var(--warning);" id="pendingInvoices">{{ $pendingInvoices }}</div>
                </div>
                <i class="fas fa-clock text-2xl opacity-70" style="color: var(--warning);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--danger-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Overdue</div>
                    <div class="text-2xl font-semibold" style="color: var(--danger);" id="overdueInvoices">{{ $overdueInvoices }}</div>
                </div>
                <i class="fas fa-exclamation-triangle text-2xl opacity-70" style="color: var(--danger);"></i>
            </div>
        </div>

        <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Consolidated</div>
                    <div class="text-2xl font-semibold" style="color: var(--info);" id="consolidatedInvoices">{{ $consolidatedInvoices }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Covered by bulk payments</div>
                </div>
                <i class="fas fa-layer-group text-2xl opacity-70" style="color: var(--info);"></i>
            </div>
        </div>

        <div class="card p-4" style="background-color: rgba(var(--success-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Active Coverage</div>
                    <div class="text-2xl font-semibold" style="color: var(--success);" id="activeCoverage">{{ $activeCoverages }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Bulk payment coverages</div>
                </div>
                <i class="fas fa-shield-alt text-2xl opacity-70" style="color: var(--success);"></i>
            </div>
        </div>

        <a href="{{ route('admin.tenant-invoices.index') }}" class="card p-4 hover:opacity-90 transition" style="background-color: rgba(147, 51, 234, 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Tenant Invoices</div>
                    <div class="text-2xl font-semibold" style="color: rgb(147, 51, 234);">View All →</div>
                </div>
                <i class="fas fa-users text-2xl opacity-70" style="color: rgb(147, 51, 234);"></i>
            </div>
        </a>
    </div>

    <!-- Additional Stats Row -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="card p-4">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Due (Pending/Overdue)</div>
                    <div class="text-2xl font-semibold" style="color: var(--text-primary);" id="totalDue">{{ $settings->formatAmount($totalDue) }}</div>
                </div>
                <i class="fas fa-money-bill-wave text-2xl opacity-70" style="color: var(--warning);"></i>
            </div>
        </div>

        <div class="card p-4">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Revenue</div>
                    <div class="text-2xl font-semibold" style="color: var(--success);" id="totalRevenue">{{ $settings->formatAmount($totalRevenue) }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">From regular paid invoices</div>
                </div>
                <i class="fas fa-chart-line text-2xl opacity-70" style="color: var(--success);"></i>
            </div>
        </div>

        <div class="card p-4">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Collection Rate</div>
                    <div class="text-2xl font-semibold" style="color: var(--primary);" id="collectionRate">{{ $actualCollectionRate }}%</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Based on regular invoices only</div>
                </div>
                <i class="fas fa-percent text-2xl opacity-70" style="color: var(--primary);"></i>
            </div>
        </div>

        <div class="card p-4">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Penalties</div>
                    <div class="text-2xl font-semibold" style="color: var(--danger);" id="totalPenalties">{{ $settings->formatAmount($totalPenalties) }}</div>
                </div>
                <i class="fas fa-exclamation-circle text-2xl opacity-70" style="color: var(--danger);"></i>
            </div>
        </div>
    </div>

    <!-- Explanation Card -->
    <div class="card p-4 mb-4" style="background-color: rgba(var(--info-rgb), 0.05);">
        <div class="flex items-start">
            <i class="fas fa-info-circle mr-3 mt-1" style="color: var(--info);"></i>
            <div class="text-sm" style="color: var(--text-secondary);">
                <strong class="font-semibold" style="color: var(--text-primary);">Understanding Invoice Statistics:</strong>
                <ul class="mt-1 space-y-1">
                    <li>• <strong class="text-success">Paid Invoices</strong> - Regular invoices that have been paid</li>
                    <li>• <strong class="text-info">Consolidated Invoices</strong> - Original invoices that have been covered by a bulk payment</li>
                    <li>• <strong class="text-success">Active Coverage</strong> - Bulk payments that are active and covering future months</li>
                    <li>• <strong class="text-primary">Collection Rate</strong> - Based on regular paid invoices only (excludes consolidated)</li>
                </ul>
                <p class="mt-2 text-xs">
                    <i class="fas fa-lightbulb mr-1"></i> 
                    When a bulk payment covers existing invoices, those invoices become "Consolidated" and are no longer counted as "Paid" to prevent double counting.
                </p>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card p-6">
        <form method="GET" action="{{ route('invoices.index') }}" id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div>
                    <select name="property_id" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Properties</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->id }}" {{ request('property_id') == $property->id ? 'selected' : '' }}>
                                {{ $property->house_number }} {{ $property->street_name }}
                                ({{ $property->landlord->name }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <select name="status" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Status</option>
                        <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="overdue" {{ request('status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                        <option value="partial" {{ request('status') == 'partial' ? 'selected' : '' }}>Partial</option>
                        <option value="consolidated" {{ request('status') == 'consolidated' ? 'selected' : '' }}>Consolidated</option>
                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                <div>
                    <select name="type" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Types</option>
                        <option value="regular" {{ request('type') == 'regular' ? 'selected' : '' }}>Regular Monthly</option>
                        <option value="bulk" {{ request('type') == 'bulk' ? 'selected' : '' }}>Bulk Payment</option>
                        <option value="child" {{ request('type') == 'child' ? 'selected' : '' }}>Consolidated Child</option>
                    </select>
                </div>
                <div>
                    <input type="month" name="period" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           placeholder="Filter by period..." value="{{ request('period') }}">
                </div>
                <div>
                    <input type="text" name="search" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           placeholder="Search invoices..." value="{{ request('search') }}">
                </div>
                <div class="flex space-x-2">
                    <button type="submit" class="w-full p-2 border rounded flex items-center justify-center" style="background-color: var(--primary); color: white; border-color: var(--border-color);">
                        <i class="fas fa-filter mr-2"></i> Filter
                    </button>
                    <a href="{{ route('invoices.index') }}" class="w-full p-2 border rounded flex items-center justify-center" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <i class="fas fa-sync mr-2"></i> Reset
                    </a>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="button" onclick="toggleAdvancedFilters()" class="text-sm flex items-center" style="color: var(--primary);">
                    <i class="fas fa-chevron-down mr-1" id="advancedFilterIcon"></i> Advanced Filters
                </button>
            </div>
            
            <div id="advancedFilters" class="hidden mt-4 grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm mb-1" style="color: var(--text-secondary);">Has Parent Bulk</label>
                    <select name="has_parent" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All</option>
                        <option value="yes" {{ request('has_parent') == 'yes' ? 'selected' : '' }}>Yes (Consolidated)</option>
                        <option value="no" {{ request('has_parent') == 'no' ? 'selected' : '' }}>No</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm mb-1" style="color: var(--text-secondary);">Is Bulk Payment</label>
                    <select name="is_bulk" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All</option>
                        <option value="yes" {{ request('is_bulk') == 'yes' ? 'selected' : '' }}>Yes</option>
                        <option value="no" {{ request('is_bulk') == 'no' ? 'selected' : '' }}>No</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm mb-1" style="color: var(--text-secondary);">Has Active Coverage</label>
                    <select name="has_coverage" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All</option>
                        <option value="yes" {{ request('has_coverage') == 'yes' ? 'selected' : '' }}>Yes</option>
                        <option value="no" {{ request('has_coverage') == 'no' ? 'selected' : '' }}>No</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm mb-1" style="color: var(--text-secondary);">Has Discount</label>
                    <select name="has_discount" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All</option>
                        <option value="yes" {{ request('has_discount') == 'yes' ? 'selected' : '' }}>Yes</option>
                        <option value="no" {{ request('has_discount') == 'no' ? 'selected' : '' }}>No</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm mb-1" style="color: var(--text-secondary);">Has Penalty</label>
                    <select name="has_penalty" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All</option>
                        <option value="yes" {{ request('has_penalty') == 'yes' ? 'selected' : '' }}>Yes</option>
                        <option value="no" {{ request('has_penalty') == 'no' ? 'selected' : '' }}>No</option>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <!-- System Status Card -->
    <div class="card p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h3 class="font-medium mb-1" style="color: var(--text-primary);">System Status</h3>
                <p class="text-sm" style="color: var(--text-secondary);">Invoice generation and reminder settings</p>
            </div>
            <div class="flex flex-wrap gap-4">
                <div class="flex items-center">
                    <span class="w-2 h-2 rounded-full {{ ($autoGenerationEnabled ?? false) ? 'bg-green-500' : 'bg-yellow-500' }} mr-2"></span>
                    <span class="text-sm" style="color: var(--text-secondary);">Auto-generation: {{ ($autoGenerationEnabled ?? false) ? 'Enabled' : 'Disabled' }}</span>
                </div>
                <div class="flex items-center">
                    <span class="w-2 h-2 rounded-full {{ ($remindersEnabled ?? false) ? 'bg-green-500' : 'bg-yellow-500' }} mr-2"></span>
                    <span class="text-sm" style="color: var(--text-secondary);">Reminders: {{ ($remindersEnabled ?? false) ? 'Enabled' : 'Disabled' }}</span>
                </div>
                @if(isset($nextGenerationDate) && $nextGenerationDate)
                <div class="flex items-center">
                    <i class="far fa-calendar-alt mr-2 text-sm" style="color: var(--text-secondary);"></i>
                    <span class="text-sm" style="color: var(--text-secondary);">Next generation: {{ $nextGenerationDate }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Bulk Operations Card -->
    <div class="card p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h3 class="font-medium mb-1" style="color: var(--text-primary);">Bulk Operations</h3>
                <p class="text-sm" style="color: var(--text-secondary);">Perform actions on multiple invoices at once</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" onclick="openBulkStatusModal()" class="px-4 py-2 rounded" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                    <i class="fas fa-edit mr-2"></i> Bulk Update Status
                </button>
                <button type="button" onclick="openExportModal()" class="px-4 py-2 rounded" style="background-color: rgba(var(--danger-rgb), 0.1); color: #dc2626;">
                    <i class="fas fa-file-pdf mr-2"></i> Export PDF
                </button>
                <button type="button" onclick="openBulkCoverageModal()" class="px-4 py-2 rounded" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                    <i class="fas fa-shield-alt mr-2"></i> Check Coverage
                </button>
            </div>
        </div>
    </div>

    <!-- Invoices Table Card -->
    <div class="card p-6">
        <!-- Results Count and Selection Info -->
        <div class="mb-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div class="flex items-center gap-4">
                <p class="text-sm" style="color: var(--text-secondary);">
                    Showing <span id="showingFrom">{{ $invoices->firstItem() ?? 0 }}</span> to <span id="showingTo">{{ $invoices->lastItem() ?? 0 }}</span> of <span id="showingTotal">{{ $invoices->total() }}</span> results
                </p>
                <div class="flex items-center">
                    <input type="checkbox" id="selectAllInvoices" class="mr-2">
                    <label for="selectAllInvoices" class="text-sm" style="color: var(--text-secondary);">Select All</label>
                </div>
            </div>
            
            @if(request()->hasAny(['property_id', 'status', 'type', 'period', 'has_parent', 'is_bulk', 'has_coverage']))
            <div class="flex items-center flex-wrap gap-2">
                <span class="text-sm mr-2" style="color: var(--text-secondary);">Active filters:</span>
                @if(request('property_id'))
                @php
                    $selectedProperty = $properties->firstWhere('id', request('property_id'));
                @endphp
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                    Property: {{ $selectedProperty ? $selectedProperty->house_number . ' ' . $selectedProperty->street_name : 'N/A' }}
                </span>
                @endif
                @if(request('status'))
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                    Status: {{ ucfirst(request('status')) }}
                </span>
                @endif
                @if(request('type'))
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                    Type: {{ ucfirst(request('type')) }}
                </span>
                @endif
                @if(request('has_coverage') == 'yes')
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                    <i class="fas fa-shield-alt mr-1"></i> Active Coverage
                </span>
                @endif
            </div>
            @endif
        </div>

        <!-- Selection Actions Bar (hidden by default) -->
        <div id="selectionActions" class="hidden mb-4 p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.1);">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm font-medium" style="color: var(--text-primary);">
                        <span id="selectedCount">0</span> invoice(s) selected
                    </span>
                    <span class="text-sm ml-2" style="color: var(--text-secondary);">
                        Total: <span id="selectedTotal">{{ $settings->formatAmount(0) }}</span>
                    </span>
                </div>
                <div class="flex gap-2">
                    <button onclick="bulkMarkAsPaid()" class="px-3 py-1 rounded text-sm" style="background-color: var(--success); color: white;">
                        <i class="fas fa-check mr-1"></i> Mark as Paid
                    </button>
                    <button onclick="bulkExport()" class="px-3 py-1 rounded text-sm" style="background-color: var(--primary); color: white;">
                        <i class="fas fa-download mr-1"></i> Export
                    </button>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color);">
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary); width: 30px;">
                            <input type="checkbox" id="selectAllCheckbox">
                        </th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Invoice #</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Property</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Period</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Due Date</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Amount</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Type</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Coverage</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Payment</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody id="invoicesTableBody">
                    @forelse($invoices as $invoice)
                    @php
                        // ✅ FIX: Properly decode covers_periods for coverage display
                        $coverageCount = 0;
                        $coveragePeriods = [];
                        if($invoice->is_bulk_payment && $invoice->isPaid() && !empty($invoice->covers_periods)) {
                            $coversRaw = $invoice->covers_periods;
                            if(is_string($coversRaw)) {
                                $coveragePeriods = json_decode($coversRaw, true);
                                if(is_array($coveragePeriods)) {
                                    $coverageCount = count($coveragePeriods);
                                }
                            } elseif(is_array($coversRaw)) {
                                $coveragePeriods = $coversRaw;
                                $coverageCount = count($coveragePeriods);
                            }
                        }
                    @endphp
                    <tr class="border-b {{ $invoice->status == 'consolidated' ? 'opacity-75' : '' }} {{ $invoice->bulk_parent_id ? 'child-invoice' : '' }}" 
                        style="border-color: var(--border-color); {{ $invoice->status == 'consolidated' ? 'background-color: rgba(var(--info-rgb), 0.02);' : '' }}" 
                        data-invoice-id="{{ $invoice->id }}"
                        data-amount="{{ $invoice->total_amount }}"
                        data-status="{{ $invoice->status }}"
                        data-type="{{ $invoice->is_bulk_payment ? 'bulk' : ($invoice->bulk_parent_id ? 'child' : 'regular') }}">
                        
                        <td class="p-3">
                            @if(!in_array($invoice->status, ['paid', 'cancelled']) && $invoice->status != 'consolidated')
                                <input type="checkbox" class="invoice-checkbox" value="{{ $invoice->id }}" 
                                       data-amount="{{ $invoice->total_amount }}">
                            @endif
                        </td>
                        <td class="p-3">
                            <div class="flex items-center">
                                @if($invoice->is_bulk_payment)
                                    <i class="fas fa-layer-group mr-1 text-xs" style="color: var(--info);"></i>
                                @elseif($invoice->bulk_parent_id)
                                    <i class="fas fa-link mr-1 text-xs" style="color: var(--info);"></i>
                                @endif
                                <span class="font-medium" style="color: var(--text-primary);">
                                    INV-{{ str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}
                                </span>
                            </div>
                            @if($invoice->bulk_parent_id)
                            <p class="text-xs mt-1">
                                <a href="{{ route('invoices.show', $invoice->bulk_parent_id) }}" class="hover:underline" style="color: var(--info);">
                                    <i class="fas fa-layer-group mr-1"></i> Parent: INV-{{ str_pad($invoice->bulk_parent_id, 6, '0', STR_PAD_LEFT) }}
                                </a>
                            </p>
                            @endif
                            @if($invoice->payment_reference)
                                <p class="text-xs" style="color: var(--text-secondary);">Ref: {{ $invoice->payment_reference }}</p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $invoice->property->house_number }} {{ $invoice->property->street_name }}
                            </p>
                            <div class="text-sm" style="color: var(--text-secondary);">
                                @if($invoice->property->block_number)
                                    Block: {{ $invoice->property->block_number }}
                                @endif
                                @if($invoice->property->zone)
                                    • {{ $invoice->property->zone }}
                                @endif
                            </div>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                Landlord: {{ $invoice->property->landlord->name }}
                            </p>
                        </td>
                        <td class="p-3">
                            @php
                                $periodDisplay = $invoice->period;
                                
                                if($invoice->is_bulk_payment) {
                                    if($invoice->bulk_coverage_start && $invoice->bulk_coverage_end) {
                                        $periodDisplay = \Carbon\Carbon::parse($invoice->bulk_coverage_start . '-01')->format('M Y') . 
                                                       ' - ' . 
                                                       \Carbon\Carbon::parse($invoice->bulk_coverage_end . '-01')->format('M Y');
                                    } else {
                                        $periodDisplay = 'Bulk Payment';
                                    }
                                }
                                elseif(preg_match('/^\d{4}-\d{2}$/', $invoice->period)) {
                                    try {
                                        $periodDisplay = \Carbon\Carbon::parse($invoice->period . '-01')->format('M Y');
                                    } catch (\Exception $e) {
                                        // Keep original
                                    }
                                }
                            @endphp
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $periodDisplay }}
                            </p>
                            @if($invoice->is_bulk_payment && $invoice->bulk_months)
                            <p class="text-xs" style="color: var(--text-secondary);">
                                {{ $invoice->bulk_months }} months
                            </p>
                            @endif
                            @if($invoice->bulk_parent_id)
                            <p class="text-xs" style="color: var(--info);">
                                <i class="fas fa-check-circle mr-1"></i> Consolidated
                            </p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $invoice->due_date->format('M d, Y') }}
                            </p>
                            @if($invoice->status === 'overdue')
                            <p class="text-xs text-danger">Overdue by {{ $invoice->due_date->diffInDays(now()) }} days</p>
                            @endif
                        </td>
                        <td class="p-3">
                            <div>
                                <p class="font-medium" style="color: var(--success);">
                                    {{ $settings->formatAmount($invoice->amount) }}
                                </p>
                                @if($invoice->penalty_amount > 0)
                                <p class="text-xs" style="color: var(--danger);">
                                    +{{ $settings->formatAmount($invoice->penalty_amount) }} penalty
                                </p>
                                @endif
                                @if(isset($invoice->discount_amount) && $invoice->discount_amount > 0)
                                <p class="text-xs" style="color: var(--success);">
                                    -{{ $settings->formatAmount($invoice->discount_amount) }} discount
                                </p>
                                @endif
                                @if($invoice->paid_amount > 0 && $invoice->paid_amount < $invoice->total_amount)
                                <p class="text-xs" style="color: var(--warning);">
                                    Paid: {{ $settings->formatAmount($invoice->paid_amount) }}
                                </p>
                                @endif
                            </div>
                        </td>
                        <td class="p-3">
                            @php
                                $statusColors = [
                                    'paid' => 'success',
                                    'pending' => 'warning',
                                    'overdue' => 'danger',
                                    'partial' => 'info',
                                    'processing' => 'info',
                                    'cancelled' => 'secondary',
                                    'consolidated' => 'info'
                                ];
                                $statusColor = $statusColors[$invoice->status] ?? 'secondary';
                            @endphp
                            <span class="px-2 py-1 rounded-full text-xs status-badge" style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.2); color: var(--{{ $statusColor }});">
                                {{ $invoice->status == 'consolidated' ? 'In Bulk' : ucfirst($invoice->status) }}
                            </span>
                        </td>
                        <td class="p-3">
                            @if($invoice->is_bulk_payment)
                                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                                    <i class="fas fa-layer-group mr-1"></i> Bulk
                                </span>
                            @elseif($invoice->bulk_parent_id)
                                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                                    <i class="fas fa-link mr-1"></i> Child
                                </span>
                            @else
                                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--secondary-rgb), 0.2); color: var(--text-secondary);">
                                    Regular
                                </span>
                            @endif
                        </td>
                        <!-- ✅ FIXED: Coverage Column with proper JSON decoding -->
                        <td class="p-3">
                            @if($invoice->is_bulk_payment && $invoice->isPaid() && $coverageCount > 0)
                                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);" title="Covers {{ $coverageCount }} months">
                                    <i class="fas fa-shield-alt mr-1"></i> Active
                                </span>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    {{ $coverageCount }} months
                                </p>
                            @elseif($invoice->is_bulk_payment && $invoice->isPaid())
                                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                                    <i class="fas fa-clock mr-1"></i> Pending
                                </span>
                            @elseif($invoice->is_bulk_payment)
                                <span class="text-xs" style="color: var(--text-secondary);">-</span>
                            @elseif($invoice->bulk_parent_id)
                                <span class="text-xs" style="color: var(--info);">Covered by bulk</span>
                            @else
                                <span class="text-xs" style="color: var(--text-secondary);">-</span>
                            @endif
                        </td>
                        <td class="p-3">
                            @if($invoice->payment_method)
                            <span class="px-2 py-1 rounded-full text-xs payment-method" style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                                {{ ucfirst(str_replace('_', ' ', $invoice->payment_method)) }}
                            </span>
                            @if($invoice->payment_date)
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                {{ \Carbon\Carbon::parse($invoice->payment_date)->format('M d, Y') }}
                            </p>
                            @endif
                            @else
                            <span class="text-sm" style="color: var(--text-secondary);">-</span>
                            @endif
                        </td>
                        <td class="p-3">
                            <div class="flex space-x-2">
                                <a href="{{ route('invoices.show', $invoice->id) }}" 
                                   class="p-2 rounded" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('invoices.print', $invoice->id) }}" 
                                   class="p-2 rounded" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);" title="Print Invoice">
                                    <i class="fas fa-print"></i>
                                </a>
                                <button onclick="exportSinglePDF({{ $invoice->id }})" 
                                        class="p-2 rounded" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);" title="Export PDF">
                                    <i class="fas fa-file-pdf"></i>
                                </button>
                                @if($invoice->status !== 'paid' && $invoice->status !== 'consolidated')
                                <button type="button" 
                                        onclick="openMarkAsPaidModal({{ $invoice->id }})"
                                        class="p-2 rounded mark-as-paid-btn" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" title="Mark as Paid">
                                    <i class="fas fa-check"></i>
                                </button>
                                @endif
                                @if($invoice->is_bulk_payment && $invoice->childInvoices && safeCount($invoice->childInvoices) > 0)
                                <button type="button"
                                        onclick="openReverseConsolidationModal({{ $invoice->id }})"
                                        class="p-2 rounded" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);" title="Reverse Consolidation">
                                    <i class="fas fa-undo"></i>
                                </button>
                                @endif
                                <!-- ✅ FIXED: Coverage Info Button with proper condition -->
                                @if($invoice->is_bulk_payment && $invoice->isPaid() && $coverageCount > 0)
                                <button type="button"
                                        onclick="showCoverageInfo({{ $invoice->id }})"
                                        class="p-2 rounded" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" title="View Coverage Details">
                                    <i class="fas fa-shield-alt"></i>
                                </button>
                                @endif
                                @if($invoice->status !== 'consolidated')
                                <button type="button" 
                                        onclick="confirmSoftDelete({{ $invoice->id }})"
                                        class="p-2 rounded" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);" title="Move to Trash">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                      </tr>
                    @empty
                     <tr>
                        <td colspan="11" class="p-8 text-center">
                            <div class="flex flex-col items-center justify-center" style="color: var(--text-secondary);">
                                <i class="fas fa-file-invoice text-4xl mb-4 opacity-50"></i>
                                <p class="text-lg font-medium mb-2">No invoices found</p>
                                <p class="text-sm">Try adjusting your filters or generate new invoices.</p>
                            </div>
                        </td>
                     </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($invoices->hasPages())
        <div class="flex justify-center mt-6">
            {{ $invoices->appends(request()->query())->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Export PDF Modal -->
<div id="exportModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Export Invoices as PDF</h3>
                <button type="button" onclick="closeExportModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="space-y-4">
                <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-blue-700">
                                Select the invoices you want to export as PDF. You can choose to export the current page, all filtered invoices, or selected invoices.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-3">
                    <button onclick="exportCurrentPagePDF()" class="w-full p-3 border rounded-lg flex items-center justify-between transition-colors" style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                        <div class="flex items-center">
                            <i class="fas fa-file-pdf text-red-500 text-xl mr-3"></i>
                            <div class="text-left">
                                <p class="font-medium">Export Current Page</p>
                                <p class="text-xs" style="color: var(--text-secondary);">Export only the invoices visible on this page</p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </button>
                    
                    <button onclick="exportFilteredPDF()" class="w-full p-3 border rounded-lg flex items-center justify-between transition-colors" style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                        <div class="flex items-center">
                            <i class="fas fa-filter text-blue-500 text-xl mr-3"></i>
                            <div class="text-left">
                                <p class="font-medium">Export All Filtered Invoices</p>
                                <p class="text-xs" style="color: var(--text-secondary);">Export all invoices matching current filters ({{ $invoices->total() }} total)</p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </button>
                    
                    <button onclick="openBulkExportFromModal()" class="w-full p-3 border rounded-lg flex items-center justify-between transition-colors" style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                        <div class="flex items-center">
                            <i class="fas fa-check-square text-green-500 text-xl mr-3"></i>
                            <div class="text-left">
                                <p class="font-medium">Export Selected Invoices</p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    Export <span id="modalSelectedCount">0</span> selected invoice(s)
                                </p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </button>
                </div>
                
                <div class="border-t pt-4 mt-4" style="border-color: var(--border-color);">
                    <div class="flex items-center justify-between text-sm">
                        <span style="color: var(--text-secondary);">Total invoices available:</span>
                        <span class="font-semibold" style="color: var(--text-primary);">{{ $invoices->total() }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm mt-2">
                        <span style="color: var(--text-secondary);">Currently selected:</span>
                        <span id="modalSelectedTotal" class="font-semibold" style="color: var(--primary);">0</span>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="closeExportModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- PDF Export Loading Modal -->
<div id="pdfLoadingModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card p-8 text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
        <p class="text-lg font-semibold" style="color: var(--text-primary);">Generating PDF...</p>
        <p class="text-sm mt-2" style="color: var(--text-secondary);">Please wait while we prepare your document</p>
    </div>
</div>

<!-- Hidden form for current page export -->
<form id="currentPageExportForm" method="GET" action="{{ route('invoices.export-current-page') }}" target="_blank">
    @foreach(request()->all() as $key => $value)
        @if($key != '_token' && $key != 'page')
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach
</form>

<!-- Hidden form for all filtered export -->
<form id="allFilteredExportForm" method="GET" action="{{ route('invoices.export-all-filtered') }}" target="_blank">
    @foreach(request()->all() as $key => $value)
        @if($key != '_token' && $key != 'page')
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach
</form>

<!-- Hidden form for bulk PDF export -->
<form id="bulkExportForm" method="POST" action="{{ route('invoices.bulk-export') }}" target="_blank">
    @csrf
    <input type="hidden" name="invoice_ids" id="bulkInvoiceIds">
    <input type="hidden" name="format" value="pdf">
</form>

<!-- Generation Progress Modal -->
<div id="generationProgressModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card p-8 text-center max-w-md w-full">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
        <p class="text-lg font-semibold" style="color: var(--text-primary);">Generating Monthly Invoices...</p>
        <p id="generationStatus" class="text-sm mt-2" style="color: var(--text-secondary);">Please wait while we generate invoices for all properties.</p>
        <div class="mt-4 w-full bg-gray-200 rounded-full h-2">
            <div id="generationProgress" class="bg-primary h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
        </div>
        <button onclick="hideGenerationModal()" class="mt-4 text-sm text-gray-500 hover:text-gray-700">Cancel</button>
    </div>
</div>

<!-- Soft Delete Confirmation Modal -->
<div id="softDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Move Invoice to Trash</h3>
            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                This invoice will be moved to the trash. You can restore it later if needed.
            </p>
            <div id="softDeleteWarning" class="hidden mb-4 p-3 rounded" style="background-color: rgba(var(--warning-rgb), 0.1);">
                <p class="text-sm" style="color: var(--warning);">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <span id="softDeleteWarningMessage"></span>
                </p>
            </div>
            <form id="softDeleteForm" method="POST">
                @csrf
                @method('DELETE')
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Reason (Optional)</label>
                    <textarea name="reason" rows="2" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                              placeholder="Enter reason for deletion"></textarea>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeSoftDeleteModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded text-white" style="background-color: var(--danger);">
                        Move to Trash
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mark as Paid Modal -->
<div id="markAsPaidModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Mark Invoice as Paid</h3>
            <form id="markAsPaidForm" method="POST">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Payment Method</label>
                        <select name="payment_method" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" required>
                            <option value="">Select Payment Method</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="manual_mobile_money">Manual Mobile Money</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Payment Reference</label>
                        <input type="text" name="payment_reference" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               placeholder="Optional reference number">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Payment Date</label>
                        <input type="date" name="payment_date" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               value="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <!-- Coverage Activation for Bulk Invoices -->
                    <div id="coverageActivationSection" class="hidden">
                        <label class="flex items-center">
                            <input type="checkbox" name="activate_coverage" value="1" class="mr-2" checked>
                            <span class="text-sm" style="color: var(--text-secondary);">Activate bulk coverage (prevents duplicate generation)</span>
                        </label>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Notes</label>
                        <textarea name="notes" rows="2" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                                  placeholder="Optional notes"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" onclick="closeMarkAsPaidModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded text-white" style="background-color: var(--success);">
                        Mark as Paid
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reverse Consolidation Modal -->
<div id="reverseConsolidationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Reverse Consolidation</h3>
            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                This will separate the bulk invoice back into individual monthly invoices. 
                The bulk invoice will be cancelled and all child invoices will be restored to pending status.
            </p>
            <form id="reverseConsolidationForm" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Reason for Reversal</label>
                    <textarea name="reason" rows="2" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                              placeholder="Enter reason for reversal" required></textarea>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeReverseConsolidationModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded text-white" style="background-color: var(--warning);">
                        Reverse Consolidation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Status Update Modal -->
<div id="bulkStatusModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Bulk Update Status</h3>
            <form id="bulkStatusForm" method="POST" action="{{ route('invoices.bulk-update-status') }}">
                @csrf
                <input type="hidden" name="invoice_ids" id="bulkStatusInvoiceIds">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">New Status</label>
                        <select name="status" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" required>
                            <option value="">Select Status</option>
                            <option value="paid">Paid</option>
                            <option value="pending">Pending</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Payment Method (if paid)</label>
                        <select name="payment_method" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">Select Payment Method</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="manual_mobile_money">Manual Mobile Money</option>
                        </select>
                    </div>
                    <!-- Bulk Coverage Activation -->
                    <div id="bulkCoverageSection" class="hidden">
                        <label class="flex items-center">
                            <input type="checkbox" name="activate_coverage" value="1" class="mr-2" checked>
                            <span class="text-sm" style="color: var(--text-secondary);">Activate coverage for bulk invoices</span>
                        </label>
                    </div>
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="send_notifications" value="1" class="mr-2">
                            <span class="text-sm" style="color: var(--text-secondary);">Send notifications to landlords</span>
                        </label>
                    </div>
                </div>
                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" onclick="closeBulkStatusModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded text-white" style="background-color: var(--primary);">
                        Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Coverage Info Modal -->
<div id="coverageInfoModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-lg w-full">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Bulk Coverage Details</h3>
                <button type="button" onclick="closeCoverageModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="coverageModalContent" class="space-y-4">
                <!-- Content loaded dynamically -->
            </div>
            <div class="flex justify-end mt-6">
                <button type="button" onclick="closeCoverageModal()" class="px-4 py-2 rounded" style="background-color: var(--primary); color: white;">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Coverage Check Modal -->
<div id="bulkCoverageCheckModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Check Bulk Coverage</h3>
            <form id="bulkCoverageForm" onsubmit="checkBulkCoverage(event)">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Property</label>
                        <select id="coveragePropertyId" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" required>
                            <option value="">Select Property</option>
                            @foreach($properties as $property)
                                <option value="{{ $property->id }}">
                                    {{ $property->house_number }} {{ $property->street_name }} ({{ $property->landlord->name }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Period (Month)</label>
                        <input type="month" id="coveragePeriod" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" required>
                    </div>
                </div>
                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" onclick="closeBulkCoverageModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded text-white" style="background-color: var(--primary);">
                        Check Coverage
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let selectedInvoices = new Set();
let generationFormSubmitted = false;

document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide messages after 5 seconds
    const messages = document.querySelectorAll('.bg-green-100, .bg-red-100, .bg-yellow-100, .bg-blue-100');
    messages.forEach(message => {
        setTimeout(() => {
            message.style.transition = 'opacity 0.5s';
            message.style.opacity = '0';
            setTimeout(() => {
                message.style.display = 'none';
            }, 500);
        }, 5000);
    });
    
    // ✅ FIXED: Initialize checkbox handling with proper select all functionality
    initializeCheckboxes();
    
    // Update modal selection count periodically
    setInterval(updateModalSelectionCount, 500);
    
    // Handle generation form submission
    const generateForm = document.getElementById('generateMonthlyForm');
    if (generateForm) {
        generateForm.addEventListener('submit', function(e) {
            if (!generationFormSubmitted) {
                e.preventDefault();
                confirmAndSubmitGeneration();
            }
        });
    }
});

// ==================== GENERATION FUNCTIONS ====================

function confirmGenerateMonthly() {
    return confirm('Generate monthly invoices for all properties? This will create invoices for the current month if they don\'t already exist.\n\nThis may take a few moments.');
}

function confirmAndSubmitGeneration() {
    if (confirm('Generate monthly invoices for all properties? This will create invoices for the current month if they don\'t already exist.\n\nThis may take a few moments.')) {
        showGenerationModal();
        generationFormSubmitted = true;
        document.getElementById('generateMonthlyForm').submit();
    }
    return false;
}

function showGenerationModal() {
    const modal = document.getElementById('generationProgressModal');
    const progressBar = document.getElementById('generationProgress');
    const statusText = document.getElementById('generationStatus');
    
    if (modal) {
        modal.classList.remove('hidden');
        if (progressBar) progressBar.style.width = '30%';
        if (statusText) statusText.textContent = 'Starting generation process...';
        
        let progress = 30;
        const interval = setInterval(() => {
            if (progress < 90) {
                progress += 10;
                if (progressBar) progressBar.style.width = progress + '%';
                if (progress === 50 && statusText) statusText.textContent = 'Processing properties...';
                if (progress === 70 && statusText) statusText.textContent = 'Calculating amounts...';
                if (progress === 80 && statusText) statusText.textContent = 'Creating invoice records...';
            }
        }, 800);
        
        window.generationInterval = interval;
    }
}

function hideGenerationModal() {
    const modal = document.getElementById('generationProgressModal');
    if (modal) modal.classList.add('hidden');
    if (window.generationInterval) {
        clearInterval(window.generationInterval);
        window.generationInterval = null;
    }
}

// ==================== CHECKBOX FUNCTIONS ====================

function initializeCheckboxes() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const selectAllInvoices = document.getElementById('selectAllInvoices');
    const invoiceCheckboxes = document.querySelectorAll('.invoice-checkbox:not(:disabled)');
    const selectionActions = document.getElementById('selectionActions');
    const selectedCountSpan = document.getElementById('selectedCount');
    const selectedTotalSpan = document.getElementById('selectedTotal');

    // Function to update selection summary
    function updateSelectionSummary() {
        const checkedCheckboxes = document.querySelectorAll('.invoice-checkbox:checked');
        const checkedCount = checkedCheckboxes.length;
        
        if (selectedCountSpan) selectedCountSpan.textContent = checkedCount;
        
        let totalAmount = 0;
        checkedCheckboxes.forEach(checkbox => {
            const amount = parseFloat(checkbox.dataset.amount) || 0;
            totalAmount += amount;
        });
        
        if (selectedTotalSpan) selectedTotalSpan.textContent = formatAmount(totalAmount);
        
        if (selectionActions) {
            if (checkedCount > 0) {
                selectionActions.classList.remove('hidden');
            } else {
                selectionActions.classList.add('hidden');
            }
        }
        
        // Update the "Select All" checkboxes
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = checkedCount === invoiceCheckboxes.length;
            selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < invoiceCheckboxes.length;
        }
        
        if (selectAllInvoices) {
            selectAllInvoices.checked = checkedCount === invoiceCheckboxes.length;
            selectAllInvoices.indeterminate = checkedCount > 0 && checkedCount < invoiceCheckboxes.length;
        }
        
        updateModalSelectionCount();
    }

    // Handle the main table header checkbox
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            invoiceCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
                const invoiceId = parseInt(checkbox.value);
                if (this.checked) {
                    selectedInvoices.add(invoiceId);
                } else {
                    selectedInvoices.delete(invoiceId);
                }
            });
            updateSelectionSummary();
        });
    }

    // Handle the "Select All Invoices" checkbox in the results count area
    if (selectAllInvoices) {
        selectAllInvoices.addEventListener('change', function() {
            invoiceCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
                const invoiceId = parseInt(checkbox.value);
                if (this.checked) {
                    selectedInvoices.add(invoiceId);
                } else {
                    selectedInvoices.delete(invoiceId);
                }
            });
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = this.checked;
                selectAllCheckbox.indeterminate = false;
            }
            updateSelectionSummary();
        });
    }

    // Handle individual invoice checkboxes
    invoiceCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const invoiceId = parseInt(this.value);
            if (this.checked) {
                selectedInvoices.add(invoiceId);
            } else {
                selectedInvoices.delete(invoiceId);
            }
            updateSelectionSummary();
        });
    });
    
    updateSelectionSummary();
}

function formatAmount(amount) {
    const currencySymbol = '{{ $settings->currency_symbol ?? "₵" }}';
    const decimalPlaces = {{ $settings->decimal_places ?? 2 }};
    const formattedAmount = parseFloat(amount).toLocaleString('en-US', {
        minimumFractionDigits: decimalPlaces,
        maximumFractionDigits: decimalPlaces
    });
    return currencySymbol + formattedAmount;
}

function getSelectedIds() {
    return Array.from(document.querySelectorAll('.invoice-checkbox:checked')).map(cb => cb.value);
}

// ==================== EXPORT MODAL FUNCTIONS ====================

function openExportModal() {
    updateModalSelectionCount();
    document.getElementById('exportModal').classList.remove('hidden');
}

function closeExportModal() {
    document.getElementById('exportModal').classList.add('hidden');
}

function updateModalSelectionCount() {
    const count = selectedInvoices.size;
    const modalSelectedCount = document.getElementById('modalSelectedCount');
    const modalSelectedTotal = document.getElementById('modalSelectedTotal');
    
    if (modalSelectedCount) modalSelectedCount.textContent = count;
    if (modalSelectedTotal) modalSelectedTotal.textContent = count;
}

function openBulkExportFromModal() {
    closeExportModal();
    if (selectedInvoices.size === 0) {
        alert('Please select at least one invoice to export.');
        return;
    }
    bulkExport();
}

// ==================== PDF EXPORT FUNCTIONS ====================

function exportCurrentPagePDF() {
    closeExportModal();
    showLoadingModal();
    
    const form = document.getElementById('currentPageExportForm');
    if (form) form.submit();
    
    setTimeout(hideLoadingModal, 2000);
}

function exportFilteredPDF() {
    closeExportModal();
    showLoadingModal();
    
    const form = document.getElementById('allFilteredExportForm');
    if (form) form.submit();
    
    setTimeout(hideLoadingModal, 2000);
}

function exportSinglePDF(invoiceId) {
    showLoadingModal();
    window.open(`/invoices/${invoiceId}/export-pdf`, '_blank');
    setTimeout(hideLoadingModal, 2000);
}

function bulkExport() {
    const selectedIds = getSelectedIds();
    
    if (selectedIds.length === 0) {
        alert('Please select at least one invoice to export.');
        return;
    }
    
    showLoadingModal();
    
    const form = document.getElementById('bulkExportForm');
    const bulkInvoiceIds = document.getElementById('bulkInvoiceIds');
    if (form && bulkInvoiceIds) {
        bulkInvoiceIds.value = selectedIds.join(',');
        form.submit();
    }
    
    setTimeout(hideLoadingModal, 3000);
}

function showLoadingModal() {
    const modal = document.getElementById('pdfLoadingModal');
    if (modal) modal.classList.remove('hidden');
}

function hideLoadingModal() {
    const modal = document.getElementById('pdfLoadingModal');
    if (modal) modal.classList.add('hidden');
}

// ==================== ADVANCED FILTERS ====================

function toggleAdvancedFilters() {
    const advancedFilters = document.getElementById('advancedFilters');
    const icon = document.getElementById('advancedFilterIcon');
    
    if (advancedFilters.classList.contains('hidden')) {
        advancedFilters.classList.remove('hidden');
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
    } else {
        advancedFilters.classList.add('hidden');
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    }
}

// ==================== SOFT DELETE FUNCTIONS ====================

function confirmSoftDelete(invoiceId) {
    const form = document.getElementById('softDeleteForm');
    form.action = `/invoices/${invoiceId}`;
    
    fetch(`/invoices/${invoiceId}/can-delete`)
        .then(response => response.json())
        .then(data => {
            const warningDiv = document.getElementById('softDeleteWarning');
            const warningMessage = document.getElementById('softDeleteWarningMessage');
            
            if (!data.can_delete) {
                warningDiv.classList.remove('hidden');
                warningMessage.textContent = data.message;
            } else {
                warningDiv.classList.add('hidden');
            }
            
            document.getElementById('softDeleteModal').classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error checking delete eligibility:', error);
            document.getElementById('softDeleteModal').classList.remove('hidden');
        });
}

function closeSoftDeleteModal() {
    document.getElementById('softDeleteModal').classList.add('hidden');
    document.getElementById('softDeleteForm').reset();
}

// ==================== PAYMENT FUNCTIONS ====================

function openMarkAsPaidModal(invoiceId) {
    const form = document.getElementById('markAsPaidForm');
    form.action = `/invoices/${invoiceId}/mark-paid`;
    
    const row = document.querySelector(`tr[data-invoice-id="${invoiceId}"]`);
    if (row && row.dataset.type === 'bulk') {
        document.getElementById('coverageActivationSection').classList.remove('hidden');
    } else {
        document.getElementById('coverageActivationSection').classList.add('hidden');
    }
    
    document.getElementById('markAsPaidModal').classList.remove('hidden');
}

function closeMarkAsPaidModal() {
    document.getElementById('markAsPaidModal').classList.add('hidden');
    document.getElementById('markAsPaidForm').reset();
    document.getElementById('coverageActivationSection').classList.add('hidden');
}

function bulkMarkAsPaid() {
    const selectedIds = getSelectedIds();
    
    if (selectedIds.length === 0) {
        alert('Please select at least one invoice.');
        return;
    }
    
    const hasConsolidated = Array.from(document.querySelectorAll('.invoice-checkbox:checked'))
        .some(checkbox => {
            const row = checkbox.closest('tr');
            return row && row.dataset.status === 'consolidated';
        });
    
    if (hasConsolidated) {
        alert('Cannot mark consolidated invoices as paid. Please use the bulk invoice instead.');
        return;
    }
    
    if (confirm(`Mark ${selectedIds.length} invoice(s) as paid?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("invoices.bulk-mark-paid") }}';
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = '{{ csrf_token() }}';
        form.appendChild(csrfInput);
        
        const idsInput = document.createElement('input');
        idsInput.type = 'hidden';
        idsInput.name = 'invoice_ids';
        idsInput.value = JSON.stringify(selectedIds);
        form.appendChild(idsInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// ==================== REVERSE CONSOLIDATION ====================

function openReverseConsolidationModal(invoiceId) {
    const form = document.getElementById('reverseConsolidationForm');
    form.action = `/invoices/${invoiceId}/reverse-consolidation`;
    document.getElementById('reverseConsolidationModal').classList.remove('hidden');
}

function closeReverseConsolidationModal() {
    document.getElementById('reverseConsolidationModal').classList.add('hidden');
    document.getElementById('reverseConsolidationForm').reset();
}

// ==================== BULK STATUS FUNCTIONS ====================

function openBulkStatusModal() {
    const selectedIds = getSelectedIds();
    
    if (selectedIds.length === 0) {
        alert('Please select at least one invoice.');
        return;
    }
    
    const hasBulkInvoices = Array.from(document.querySelectorAll('.invoice-checkbox:checked'))
        .some(checkbox => {
            const row = checkbox.closest('tr');
            return row && row.dataset.type === 'bulk';
        });
    
    if (hasBulkInvoices) {
        document.getElementById('bulkCoverageSection').classList.remove('hidden');
    } else {
        document.getElementById('bulkCoverageSection').classList.add('hidden');
    }
    
    document.getElementById('bulkStatusInvoiceIds').value = JSON.stringify(selectedIds);
    document.getElementById('bulkStatusModal').classList.remove('hidden');
}

function closeBulkStatusModal() {
    document.getElementById('bulkStatusModal').classList.add('hidden');
    document.getElementById('bulkStatusForm').reset();
    document.getElementById('bulkCoverageSection').classList.add('hidden');
}

// ==================== COVERAGE FUNCTIONS ====================

function showCoverageInfo(invoiceId) {
    fetch(`/invoices/${invoiceId}/coverage-info`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = '<div class="space-y-3">';
                
                if (data.coverage && data.coverage.periods) {
                    // Use formatted_periods if available, otherwise format periods
                    const periods = data.coverage.formatted_periods || 
                        (data.coverage.periods ? data.coverage.periods.map(p => {
                            const [year, month] = p.split('-');
                            const date = new Date(year, month - 1);
                            return date.toLocaleString('default', { month: 'long', year: 'numeric' });
                        }) : []);
                    
                    html += `
                        <div class="p-4 rounded" style="background-color: rgba(var(--success-rgb), 0.1);">
                            <p class="font-medium mb-2" style="color: var(--text-primary);">Covered Periods:</p>
                            <div class="flex flex-wrap gap-2">
                    `;
                    
                    periods.forEach(period => {
                        html += `<span class="px-2 py-1 rounded text-xs" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">${period}</span>`;
                    });
                    
                    html += `
                            </div>
                            <p class="text-sm mt-3" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                No further invoices will be generated for these periods.
                            </p>
                        </div>
                    `;
                } else if (data.coverage && data.coverage.periods_json) {
                    // Handle case where periods might be JSON string
                    try {
                        const periodsArray = JSON.parse(data.coverage.periods_json);
                        html += `
                            <div class="p-4 rounded" style="background-color: rgba(var(--success-rgb), 0.1);">
                                <p class="font-medium mb-2" style="color: var(--text-primary);">Covered Periods:</p>
                                <div class="flex flex-wrap gap-2">
                        `;
                        periodsArray.forEach(period => {
                            html += `<span class="px-2 py-1 rounded text-xs" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">${period}</span>`;
                        });
                        html += `
                                </div>
                                <p class="text-sm mt-3" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    No further invoices will be generated for these periods.
                                </p>
                            </div>
                        `;
                    } catch(e) {
                        html += `<p class="text-sm text-gray-500">Coverage periods: ${data.coverage.periods}</p>`;
                    }
                } else {
                    html += `<p class="text-sm text-gray-500">No coverage periods found.</p>`;
                }
                
                html += '</div>';
                document.getElementById('coverageModalContent').innerHTML = html;
                document.getElementById('coverageInfoModal').classList.remove('hidden');
            } else {
                alert('Failed to load coverage information: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error loading coverage info:', error);
            alert('Error loading coverage information. Please try again.');
        });
}

function closeCoverageModal() {
    document.getElementById('coverageInfoModal').classList.add('hidden');
}

function openBulkCoverageModal() {
    document.getElementById('bulkCoverageCheckModal').classList.remove('hidden');
}

function closeBulkCoverageModal() {
    document.getElementById('bulkCoverageCheckModal').classList.add('hidden');
    document.getElementById('bulkCoverageForm').reset();
}

function checkBulkCoverage(event) {
    event.preventDefault();
    
    const propertyId = document.getElementById('coveragePropertyId').value;
    const period = document.getElementById('coveragePeriod').value;
    
    if (!propertyId || !period) {
        alert('Please select both property and period');
        return;
    }
    
    fetch(`/invoices/check-coverage?property_id=${propertyId}&period=${period}`)
        .then(response => response.json())
        .then(data => {
            closeBulkCoverageModal();
            
            if (data.success) {
                let message = data.covered 
                    ? `✅ Period ${period} is covered by bulk payment.`
                    : `❌ Period ${period} is NOT covered by any bulk payment.`;
                
                if (data.coverage_details) {
                    message += `\n\nBulk Invoice: INV-${String(data.coverage_details.bulk_invoice_id).padStart(6, '0')}`;
                    message += `\nPayment Date: ${data.coverage_details.payment_date}`;
                    message += `\nTransaction: ${data.coverage_details.transaction_id}`;
                    if (data.coverage_details.covered_periods) {
                        const formattedPeriods = data.coverage_details.covered_periods.map(p => {
                            const [year, month] = p.split('-');
                            const date = new Date(year, month - 1);
                            return date.toLocaleString('default', { month: 'long', year: 'numeric' });
                        }).join(', ');
                        message += `\nCovered Periods: ${formattedPeriods}`;
                    }
                }
                
                alert(message);
            } else {
                alert('Error checking coverage: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Coverage check failed:', error);
            alert('Error checking coverage status. Please try again.');
        });
}

// ==================== MODAL CLOSE HANDLERS ====================

// Close modals when clicking outside
document.getElementById('exportModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeExportModal();
});

document.getElementById('softDeleteModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeSoftDeleteModal();
});

document.getElementById('markAsPaidModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeMarkAsPaidModal();
});

document.getElementById('reverseConsolidationModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeReverseConsolidationModal();
});

document.getElementById('bulkStatusModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeBulkStatusModal();
});

document.getElementById('coverageInfoModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeCoverageModal();
});

document.getElementById('bulkCoverageCheckModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeBulkCoverageModal();
});

document.getElementById('pdfLoadingModal')?.addEventListener('click', function(e) {
    if (e.target === this) hideLoadingModal();
});

document.getElementById('generationProgressModal')?.addEventListener('click', function(e) {
    if (e.target === this) hideGenerationModal();
});

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeExportModal();
        closeSoftDeleteModal();
        closeMarkAsPaidModal();
        closeReverseConsolidationModal();
        closeBulkStatusModal();
        closeCoverageModal();
        closeBulkCoverageModal();
        hideLoadingModal();
        hideGenerationModal();
    }
});
</script>

<style>
/* Button styles */
.btn-primary {
    background-color: var(--primary);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    text-decoration: none;
    display: inline-block;
    cursor: pointer;
}

.btn-primary:hover {
    opacity: 0.9;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-secondary {
    background-color: var(--secondary);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    border: none;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
    cursor: pointer;
}

.btn-secondary:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.btn-warning {
    background-color: var(--warning);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    border: none;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
    cursor: pointer;
}

.btn-warning:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.btn-info {
    background-color: var(--info);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    border: none;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
    cursor: pointer;
}

.btn-info:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.btn-export {
    background-color: #dc2626;
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    text-decoration: none;
    display: inline-block;
    cursor: pointer;
}

.btn-export:hover {
    background-color: #b91c1c;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
}

.btn-tenant {
    background-color: rgb(147, 51, 234);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    text-decoration: none;
    display: inline-block;
}

.btn-tenant:hover {
    background-color: rgb(126, 34, 206);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(147, 51, 234, 0.3);
}

.btn-trash {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    text-decoration: none;
    display: inline-block;
}

.btn-trash:hover {
    background-color: rgba(var(--warning-rgb), 0.2);
    transform: translateY(-2px);
}

/* Message styles */
.bg-green-100 {
    background-color: rgba(209, 250, 229, 0.9);
    border-color: rgba(16, 185, 129, 0.3);
}

.bg-red-100 {
    background-color: rgba(254, 226, 226, 0.9);
    border-color: rgba(239, 68, 68, 0.3);
}

.bg-yellow-100 {
    background-color: rgba(254, 249, 195, 0.9);
    border-color: rgba(234, 179, 8, 0.3);
}

.bg-blue-100 {
    background-color: rgba(219, 234, 254, 0.9);
    border-color: rgba(59, 130, 246, 0.3);
}

/* Loading animation */
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.animate-spin {
    animation: spin 1s linear infinite;
}

/* Modal styles */
#exportModal,
#softDeleteModal,
#markAsPaidModal,
#reverseConsolidationModal,
#bulkStatusModal,
#coverageInfoModal,
#bulkCoverageCheckModal,
#pdfLoadingModal,
#generationProgressModal {
    transition: opacity 0.3s ease;
}

.hidden {
    opacity: 0;
    pointer-events: none;
    display: flex !important;
}

#exportModal:not(.hidden),
#softDeleteModal:not(.hidden),
#markAsPaidModal:not(.hidden),
#reverseConsolidationModal:not(.hidden),
#bulkStatusModal:not(.hidden),
#coverageInfoModal:not(.hidden),
#bulkCoverageCheckModal:not(.hidden),
#pdfLoadingModal:not(.hidden),
#generationProgressModal:not(.hidden) {
    opacity: 1;
    pointer-events: auto;
}

/* Consolidated invoice styling */
.opacity-75 {
    opacity: 0.75;
}

.child-invoice {
    border-left: 2px solid var(--info);
}

/* Checkbox styling */
input[type="checkbox"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
}

/* Selection actions bar */
#selectionActions {
    transition: all 0.3s ease;
}

/* Button hover transitions */
button.w-full {
    transition: transform 0.2s ease;
}

button.w-full:hover {
    transform: translateX(4px);
}

/* Dark mode adjustments */
.dark .bg-green-100 {
    background-color: rgba(16, 185, 129, 0.2) !important;
    border-color: rgba(16, 185, 129, 0.3) !important;
    color: #10b981 !important;
}

.dark .bg-red-100 {
    background-color: rgba(239, 68, 68, 0.2) !important;
    border-color: rgba(239, 68, 68, 0.3) !important;
    color: #ef4444 !important;
}

.dark .bg-yellow-100 {
    background-color: rgba(234, 179, 8, 0.2) !important;
    border-color: rgba(234, 179, 8, 0.3) !important;
    color: #eab308 !important;
}

.dark .bg-blue-100 {
    background-color: rgba(59, 130, 246, 0.2) !important;
    border-color: rgba(59, 130, 246, 0.3) !important;
    color: #3b82f6 !important;
}

.dark .bg-blue-50 {
    background-color: rgba(59, 130, 246, 0.2) !important;
}

.dark .text-blue-700 {
    color: #60a5fa !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid {
        gap: 1rem;
    }
    
    table {
        font-size: 0.875rem;
    }
    
    .btn-primary,
    .btn-secondary,
    .btn-warning,
    .btn-info,
    .btn-export,
    .btn-tenant,
    .btn-trash {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }
}
</style>
@endsection