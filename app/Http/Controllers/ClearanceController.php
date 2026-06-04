<?php

namespace App\Http\Controllers;

use App\Models\ClearancePeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Carbon\Carbon;

class ClearanceController extends Controller
{
    /**
     * Display the clearance management page.
     */
    public function index(): View
    {
        $departmentId = Auth::user()->department_id;

        $clearance = ClearancePeriod::where('department_id', $departmentId)
            ->latest()
            ->first();

        return view('clearance.index', compact('clearance'));
    }

    /**
     * Store or update the fine tiers for the department's clearance period.
     */
    public function storeTiers(Request $request): RedirectResponse
    {
        $request->validate([
            'tiers' => 'required|array|min:1',
            'tiers.*.min_points' => 'required|integer|min:1',
            'tiers.*.max_points' => 'nullable|integer|min:1',
            'tiers.*.fine_amount' => 'required|numeric|min:0',
            'tiers.*.requirements' => 'nullable|string|max:500',
        ]);

        $departmentId = Auth::user()->department_id;

        // Sort tiers by min_points descending for consistent storage
        $tiers = collect($request->tiers)->map(function ($tier) {
            return [
                'min_points' => (int) $tier['min_points'],
                'max_points' => isset($tier['max_points']) && $tier['max_points'] !== '' ? (int) $tier['max_points'] : null,
                'fine_amount' => (float) $tier['fine_amount'],
                'requirements' => $tier['requirements'] ?? '',
            ];
        })->sortByDesc('min_points')->values()->toArray();

        $clearance = ClearancePeriod::where('department_id', $departmentId)->latest()->first();

        if ($clearance) {
            $clearance->update(['fine_tiers' => $tiers]);
        } else {
            ClearancePeriod::create([
                'department_id' => $departmentId,
                'status' => 'inactive',
                'fine_tiers' => $tiers,
            ]);
        }

        return back()->with('status', 'Fine tiers saved successfully.');
    }

    /**
     * Activate the clearance period for the department.
     */
    public function activate(): RedirectResponse
    {
        $departmentId = Auth::user()->department_id;
        $clearance = ClearancePeriod::where('department_id', $departmentId)->latest()->first();

        if (!$clearance || empty($clearance->fine_tiers)) {
            return back()->with('error', 'You must define fine tiers before activating clearance.');
        }

        $clearance->update([
            'status' => 'active',
            'activated_at' => Carbon::now(),
        ]);

        return back()->with('status', 'Clearance period is now ACTIVE. Students will be notified.');
    }

    /**
     * Deactivate the clearance period for the department.
     */
    public function deactivate(): RedirectResponse
    {
        $departmentId = Auth::user()->department_id;
        $clearance = ClearancePeriod::where('department_id', $departmentId)->latest()->first();

        if ($clearance) {
            $clearance->update(['status' => 'inactive']);
        }

        return back()->with('status', 'Clearance period has been deactivated.');
    }
}
