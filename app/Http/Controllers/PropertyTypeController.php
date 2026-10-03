<?php

namespace App\Http\Controllers;

use App\Models\PropertyType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PropertyTypeController extends Controller
{
    /**
     * Display a listing of property types.
     */
    public function index(Request $request)
    {
        $query = PropertyType::withCount('properties');

        if ($request->has('active_only') && $request->active_only) {
            $query->active();
        }

        if ($request->has('type') && $request->type === 'standard') {
            $query->standard();
        } elseif ($request->has('type') && $request->type === 'custom') {
            $query->custom();
        }

        $propertyTypes = $query->ordered()->get();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'property_types' => $propertyTypes,
                'message' => 'Property types retrieved successfully'
            ]);
        }

        return view('property-types.index', compact('propertyTypes'));
    }

    /**
     * Show the form for creating a new property type.
     */
    public function create()
    {
        return view('property-types.create');
    }

    /**
     * Store a newly created property type.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:property_types,name',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:7|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'is_custom' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ], [
            'color.regex' => 'The color must be a valid hex color code (e.g., #3B82F6 or #FFF).',
            'name.unique' => 'A property type with this name already exists.',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $propertyType = PropertyType::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'icon' => $request->icon ?? 'fas fa-home',
            'color' => $request->color ?? '#3b82f6',
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
            'is_custom' => $request->is_custom ?? false,
            'sort_order' => $request->sort_order ?? 0,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'property_type' => $propertyType,
                'message' => 'Property type created successfully'
            ], 201);
        }

        return redirect()->route('property-types.index')
            ->with('success', 'Property type created successfully');
    }

    /**
     * Display the specified property type.
     */
    public function show(PropertyType $propertyType, Request $request)
    {
        $propertyType->loadCount('properties');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'property_type' => $propertyType,
                'message' => 'Property type retrieved successfully'
            ]);
        }

        return view('property-types.show', compact('propertyType'));
    }

    /**
     * Show the form for editing the specified property type.
     */
    public function edit(PropertyType $propertyType)
    {
        return view('property-types.edit', compact('propertyType'));
    }

    /**
     * Update the specified property type.
     */
    public function update(Request $request, PropertyType $propertyType)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:property_types,name,' . $propertyType->id,
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:7|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ], [
            'color.regex' => 'The color must be a valid hex color code (e.g., #3B82F6 or #FFF).',
            'name.unique' => 'A property type with this name already exists.',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $propertyType->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'icon' => $request->icon ?? $propertyType->icon,
            'color' => $request->color ?? $propertyType->color,
            'description' => $request->description,
            'is_active' => $request->is_active ?? $propertyType->is_active,
            'sort_order' => $request->sort_order ?? $propertyType->sort_order,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'property_type' => $propertyType,
                'message' => 'Property type updated successfully'
            ]);
        }

        return redirect()->route('property-types.index')
            ->with('success', 'Property type updated successfully');
    }

    /**
     * Remove the specified property type (soft delete).
     */
    public function destroy(PropertyType $propertyType, Request $request)
    {
        // Prevent deletion of standard property types that are in use
        if (!$propertyType->is_custom && $propertyType->properties()->count() > 0) {
            $message = 'Cannot delete standard property type. It has associated properties.';
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message
                ], 422);
            }
            return redirect()->back()->with('error', $message);
        }

        // For custom types, allow deletion even with properties (or implement soft delete)
        $propertyType->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Property type deleted successfully'
            ]);
        }

        return redirect()->route('property-types.index')
            ->with('success', 'Property type deleted successfully');
    }

    /**
     * Get property types for API (for dropdowns).
     */
    public function getPropertyTypes(Request $request)
    {
        $propertyTypes = PropertyType::active()
            ->withCount('properties')
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'property_types' => $propertyTypes,
            'message' => 'Property types retrieved successfully'
        ]);
    }

    /**
     * Get property type statistics.
     */
    public function getStats(Request $request)
    {
        $stats = PropertyType::active()
            ->withCount('properties')
            ->ordered()
            ->get()
            ->map(function($type) {
                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'slug' => $type->slug,
                    'icon' => $type->icon,
                    'color' => $type->color,
                    'properties_count' => $type->properties_count,
                    'description' => $type->description,
                    'is_custom' => $type->is_custom
                ];
            });

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'message' => 'Property type statistics retrieved successfully'
        ]);
    }

    /**
     * Restore a soft-deleted property type.
     */
    public function restore($id, Request $request)
    {
        $propertyType = PropertyType::withTrashed()->findOrFail($id);
        $propertyType->restore();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Property type restored successfully'
            ]);
        }

        return redirect()->route('property-types.index')
            ->with('success', 'Property type restored successfully');
    }

    /**
     * Get only standard property types (for forms and filters).
     */
    public function getStandardTypes(Request $request)
    {
        $propertyTypes = PropertyType::standard()
            ->active()
            ->ordered()
            ->get(['id', 'name', 'slug', 'icon', 'color']);

        return response()->json([
            'success' => true,
            'property_types' => $propertyTypes,
            'message' => 'Standard property types retrieved successfully'
        ]);
    }
}