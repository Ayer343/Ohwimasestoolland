<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class WhatsAppTemplateController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * List WhatsApp templates
     */
    public function index(Request $request): View
    {
        $query = WhatsAppTemplate::query()
            ->orderBy('created_at', 'desc');

        // Filter by category
        if ($request->has('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $templates = $query->paginate(20);

        $categories = [
            'payment' => 'Payment Reminders',
            'invoice' => 'Invoice Notifications',
            'property' => 'Property Management',
            'maintenance' => 'Maintenance Updates',
            'booking' => 'Booking & Reservations',
            'general' => 'General Notifications',
            'emergency' => 'Emergency Alerts',
        ];

        return view('admin.whatsapp.templates.index', compact('templates', 'categories'));
    }

    /**
     * Show template creation form
     */
    public function create(): View
    {
        $categories = [
            'payment' => 'Payment Reminders',
            'invoice' => 'Invoice Notifications',
            'property' => 'Property Management',
            'maintenance' => 'Maintenance Updates',
            'booking' => 'Booking & Reservations',
            'general' => 'General Notifications',
            'emergency' => 'Emergency Alerts',
        ];

        $variables = [
            '{{system_name}}' => 'System Name',
            '{{system_phone}}' => 'System Phone',
            '{{user_name}}' => 'User Name',
            '{{invoice_number}}' => 'Invoice Number',
            '{{amount}}' => 'Amount',
            '{{due_date}}' => 'Due Date',
            '{{property_name}}' => 'Property Name',
            '{{property_address}}' => 'Property Address',
            '{{maintenance_request}}' => 'Maintenance Request',
            '{{booking_date}}' => 'Booking Date',
            '{{booking_status}}' => 'Booking Status',
            '{{payment_link}}' => 'Payment Link',
            '{{support_phone}}' => 'Support Phone',
            '{{emergency_contact}}' => 'Emergency Contact',
        ];

        return view('admin.whatsapp.templates.create', compact('categories', 'variables'));
    }

    /**
     * Store new template
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:whatsapp_templates',
            'description' => 'nullable|string|max:500',
            'content' => 'required|string|max:4096',
            'category' => 'required|string',
            'status' => 'required|in:active,inactive,draft',
            'variables' => 'nullable|array',
            'is_default' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $template = WhatsAppTemplate::create([
                'name' => $request->name,
                'description' => $request->description,
                'content' => $request->content,
                'category' => $request->category,
                'status' => $request->status,
                'variables' => $request->variables ?? [],
                'is_default' => $request->boolean('is_default', false),
                'created_by' => auth()->id()
            ]);

            // If this is set as default, remove default flag from other templates
            if ($template->is_default) {
                WhatsAppTemplate::where('category', $template->category)
                    ->where('id', '!=', $template->id)
                    ->update(['is_default' => false]);
            }

            Log::info('WhatsApp template created', [
                'template_id' => $template->id,
                'name' => $template->name,
                'category' => $template->category,
                'created_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Template created successfully!',
                'data' => $template
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating WhatsApp template: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show template details
     */
    public function show($id): View
    {
        $template = WhatsAppTemplate::findOrFail($id);
        
        return view('admin.whatsapp.templates.show', compact('template'));
    }

    /**
     * Show template edit form
     */
    public function edit($id): View
    {
        $template = WhatsAppTemplate::findOrFail($id);
        
        $categories = [
            'payment' => 'Payment Reminders',
            'invoice' => 'Invoice Notifications',
            'property' => 'Property Management',
            'maintenance' => 'Maintenance Updates',
            'booking' => 'Booking & Reservations',
            'general' => 'General Notifications',
            'emergency' => 'Emergency Alerts',
        ];

        $variables = [
            '{{system_name}}' => 'System Name',
            '{{system_phone}}' => 'System Phone',
            '{{user_name}}' => 'User Name',
            '{{invoice_number}}' => 'Invoice Number',
            '{{amount}}' => 'Amount',
            '{{due_date}}' => 'Due Date',
            '{{property_name}}' => 'Property Name',
            '{{property_address}}' => 'Property Address',
            '{{maintenance_request}}' => 'Maintenance Request',
            '{{booking_date}}' => 'Booking Date',
            '{{booking_status}}' => 'Booking Status',
            '{{payment_link}}' => 'Payment Link',
            '{{support_phone}}' => 'Support Phone',
            '{{emergency_contact}}' => 'Emergency Contact',
        ];

        return view('admin.whatsapp.templates.edit', compact('template', 'categories', 'variables'));
    }

    /**
     * Update template
     */
    public function update(Request $request, $id): JsonResponse
    {
        $template = WhatsAppTemplate::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:whatsapp_templates,name,' . $id,
            'description' => 'nullable|string|max:500',
            'content' => 'required|string|max:4096',
            'category' => 'required|string',
            'status' => 'required|in:active,inactive,draft',
            'variables' => 'nullable|array',
            'is_default' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $template->update([
                'name' => $request->name,
                'description' => $request->description,
                'content' => $request->content,
                'category' => $request->category,
                'status' => $request->status,
                'variables' => $request->variables ?? [],
                'is_default' => $request->boolean('is_default', false),
                'updated_by' => auth()->id()
            ]);

            // If this is set as default, remove default flag from other templates
            if ($template->is_default) {
                WhatsAppTemplate::where('category', $template->category)
                    ->where('id', '!=', $template->id)
                    ->update(['is_default' => false]);
            }

            Log::info('WhatsApp template updated', [
                'template_id' => $template->id,
                'name' => $template->name,
                'updated_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Template updated successfully!',
                'data' => $template
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating WhatsApp template: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete template
     */
    public function destroy($id): JsonResponse
    {
        try {
            $template = WhatsAppTemplate::findOrFail($id);
            
            // Prevent deletion of default templates
            if ($template->is_default) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete a default template. Please remove the default flag first.'
                ], 400);
            }

            $template->delete();

            Log::info('WhatsApp template deleted', [
                'template_id' => $id,
                'name' => $template->name,
                'deleted_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully!'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting WhatsApp template: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync template with provider
     */
    public function sync($id): JsonResponse
    {
        try {
            $template = WhatsAppTemplate::findOrFail($id);
            
            // Check if provider supports templates
            $status = $this->whatsappService->getQuickStatus();
            if (!$status['system_ready']) {
                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp service is not ready. Please check configuration.'
                ], 400);
            }

            // Sync with provider
            // This would depend on your provider's API
            $syncResult = $this->syncTemplateWithProvider($template);

            if ($syncResult['success']) {
                $template->update([
                    'provider_template_id' => $syncResult['provider_template_id'],
                    'last_synced_at' => now(),
                    'sync_status' => 'synced'
                ]);

                Log::info('WhatsApp template synced with provider', [
                    'template_id' => $template->id,
                    'provider_template_id' => $syncResult['provider_template_id']
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Template synced successfully!',
                    'data' => $syncResult
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $syncResult['message'] ?? 'Failed to sync template with provider'
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Error syncing WhatsApp template: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint for template list
     */
    public function apiList(Request $request): JsonResponse
    {
        $query = WhatsAppTemplate::where('status', 'active');

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $templates = $query->get();

        return response()->json([
            'success' => true,
            'data' => $templates
        ]);
    }

    /**
     * Sync template with provider
     */
    protected function syncTemplateWithProvider($template): array
    {
        // This method would implement provider-specific template syncing
        // For now, return a simulated success
        
        return [
            'success' => true,
            'provider_template_id' => 'template_' . uniqid(),
            'provider' => config('whatsapp.provider', 'unknown')
        ];
    }
}