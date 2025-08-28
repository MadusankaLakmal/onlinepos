<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StoreController extends Controller
{
    /**
     * Display a listing of the stores.
     */
    public function index()
    {
        $user = auth()->user();
        
        // Check if user can manage all tenants (Super Admin)
        if ($user->hasPermissionTo('tenants.manage.all')) {
            $stores = Store::with('tenant')->latest()->paginate(10);
        } else {
            // Tenant owner can only see their own stores
            $stores = Store::where('tenant_id', $user->tenant_id)
                           ->with('tenant')
                           ->latest()
                           ->paginate(10);
        }

        return Inertia::render('Stores/Index', [
            'stores' => $stores,
            'canManageAll' => $user->hasPermissionTo('tenants.manage.all'),
        ]);
    }

    /**
     * Show the form for creating a new store.
     */
    public function create()
    {
        $user = auth()->user();
        
        // Only Super Admins can create stores
        if (!$user->hasPermissionTo('tenants.manage.all')) {
            abort(403, 'Unauthorized to create stores');
        }

        $tenants = Tenant::all();

        return Inertia::render('Stores/Create', [
            'tenants' => $tenants,
        ]);
    }

    /**
     * Store a newly created store in storage.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        
        // Only Super Admins can create stores
        if (!$user->hasPermissionTo('tenants.manage.all')) {
            abort(403, 'Unauthorized to create stores');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'tenant_id' => 'required|exists:tenants,id',
            'status' => 'required|in:active,inactive',
        ]);

        $store = Store::create($validated);

        return redirect()->route('stores.index')
                        ->with('success', 'Store created successfully.');
    }

    /**
     * Display the specified store.
     */
    public function show(Store $store)
    {
        $user = auth()->user();
        
        // Check permissions
        if (!$user->hasPermissionTo('tenants.manage.all') && $store->tenant_id !== $user->tenant_id) {
            abort(403, 'Unauthorized to view this store');
        }

        $store->load('tenant');

        return Inertia::render('Stores/Show', [
            'store' => $store,
            'canManageAll' => $user->hasPermissionTo('tenants.manage.all'),
        ]);
    }

    /**
     * Show the form for editing the specified store.
     */
    public function edit(Store $store)
    {
        $user = auth()->user();
        
        // Check permissions
        if (!$user->hasPermissionTo('tenants.manage.all') && $store->tenant_id !== $user->tenant_id) {
            abort(403, 'Unauthorized to edit this store');
        }

        $tenants = $user->hasPermissionTo('tenants.manage.all') ? Tenant::all() : collect();

        return Inertia::render('Stores/Edit', [
            'store' => $store,
            'tenants' => $tenants,
            'canManageAll' => $user->hasPermissionTo('tenants.manage.all'),
        ]);
    }

    /**
     * Update the specified store in storage.
     */
    public function update(Request $request, Store $store)
    {
        $user = auth()->user();
        
        // Check permissions
        if (!$user->hasPermissionTo('tenants.manage.all') && $store->tenant_id !== $user->tenant_id) {
            abort(403, 'Unauthorized to update this store');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'tenant_id' => $user->hasPermissionTo('tenants.manage.all') ? 'required|exists:tenants,id' : 'prohibited',
            'status' => 'required|in:active,inactive',
        ]);

        // If not Super Admin, preserve the original tenant_id
        if (!$user->hasPermissionTo('tenants.manage.all')) {
            $validated['tenant_id'] = $store->tenant_id;
        }

        $store->update($validated);

        return redirect()->route('stores.index')
                        ->with('success', 'Store updated successfully.');
    }

    /**
     * Remove the specified store from storage.
     */
    public function destroy(Store $store)
    {
        $user = auth()->user();
        
        // Check permissions
        if (!$user->hasPermissionTo('tenants.manage.all') && $store->tenant_id !== $user->tenant_id) {
            abort(403, 'Unauthorized to delete this store');
        }

        $store->delete();

        return redirect()->route('stores.index')
                        ->with('success', 'Store deleted successfully.');
    }
}