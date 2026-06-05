<?php

namespace App\Http\Controllers;

use App\Models\ClearancePeriod;
use App\Models\User;
use App\Models\Fine;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Carbon\Carbon;

class ClearanceController extends Controller
{
    /**
     * Display the fines settings page (tier builder).
     */
    public function index(): View
    {
        $departmentId = Auth::user()->department_id;

        $clearance = ClearancePeriod::where('department_id', $departmentId)
            ->latest()
            ->first();

        return view('fines-settings.index', compact('clearance'));
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
     * Process student fines: activate clearance and generate fine records.
     */
    public function activate(): RedirectResponse
    {
        $departmentId = Auth::user()->department_id;
        $clearance = ClearancePeriod::where('department_id', $departmentId)->latest()->first();

        if (!$clearance || empty($clearance->fine_tiers)) {
            return back()->with('error', 'You must define fine tiers before processing fines.');
        }

        // Get all student IDs in the department
        $studentIds = User::role('student')
            ->where('department_id', $departmentId)
            ->pluck('id');

        // Delete any previous clearance fines so we can recalculate fresh
        Fine::whereIn('user_id', $studentIds)
            ->whereNull('event_id')
            ->delete();

        // Reset all students' cleared status
        User::whereIn('id', $studentIds)->update(['is_cleared' => false]);

        $students = User::role('student')
            ->where('department_id', $departmentId)
            ->get();

        $fineCount = 0;

        foreach ($students as $student) {
            $totalPoints = $student->getTotalPenaltyPoints();

            if ($totalPoints <= 0) {
                // Student has no penalty points — auto-clear them
                $student->update(['is_cleared' => true]);
                continue;
            }

            $tier = $clearance->matchTier($totalPoints);

            if (!$tier || $tier['fine_amount'] <= 0) {
                continue;
            }

            $requirements = !empty($tier['requirements']) ? ' + ' . $tier['requirements'] : '';

            Fine::create([
                'user_id' => $student->id,
                'event_id' => null,
                'amount' => $tier['fine_amount'],
                'status' => 'unpaid',
                'reason' => "Clearance Period — {$totalPoints} pts{$requirements}",
            ]);
            $fineCount++;
        }

        $clearance->update([
            'status' => 'active',
            'activated_at' => Carbon::now(),
        ]);

        return back()->with('status', "Fines processed: {$fineCount} student(s) fined. Clearance is now active.");
    }

    /**
     * Reset/deactivate the clearance period and remove all clearance fines.
     */
    public function deactivate(): RedirectResponse
    {
        $departmentId = Auth::user()->department_id;
        $clearance = ClearancePeriod::where('department_id', $departmentId)->latest()->first();

        if ($clearance) {
            $studentIds = User::role('student')
                ->where('department_id', $departmentId)
                ->pluck('id');

            Fine::whereIn('user_id', $studentIds)
                ->whereNull('event_id')
                ->delete();

            // Reset all students' cleared status
            User::whereIn('id', $studentIds)->update(['is_cleared' => false]);

            $clearance->update(['status' => 'inactive']);
        }

        return back()->with('status', 'Clearance has been reset. All clearance fines have been removed.');
    }

    // ──────────────────────────────────────────────
    // Clearance List (new tab)
    // ──────────────────────────────────────────────

    /**
     * Display the clearance list — all students with their clear status.
     */
    public function clearanceList(): View
    {
        $departmentId = Auth::user()->department_id;

        $students = User::role('student')
            ->where('department_id', $departmentId)
            ->with('department')
            ->orderBy('name')
            ->get();

        $clearanceActive = ClearancePeriod::where('department_id', $departmentId)
            ->where('status', 'active')
            ->exists();

        return view('clearance.list', compact('students', 'clearanceActive'));
    }

    /**
     * Mark a student as cleared.
     */
    public function clearStudent(User $user): RedirectResponse
    {
        if ($user->department_id !== Auth::user()->department_id) {
            abort(403);
        }

        if ($user->hasUnpaidFines()) {
            return back()->with('error', "Cannot clear {$user->name} — this student has unpaid fines that need to be paid first.");
        }

        $user->update(['is_cleared' => true]);

        return back()->with('status', "{$user->name} has been marked as CLEARED.");
    }

    /**
     * Remove clearance from a student.
     */
    public function unclearStudent(User $user): RedirectResponse
    {
        if ($user->department_id !== Auth::user()->department_id) {
            abort(403);
        }

        $user->update(['is_cleared' => false]);

        return back()->with('status', "{$user->name} clearance has been revoked.");
    }
}
