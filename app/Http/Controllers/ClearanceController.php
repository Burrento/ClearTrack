<?php

namespace App\Http\Controllers;

use App\Models\ClearancePeriod;
use App\Models\User;
use App\Models\Fine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ClearanceController extends Controller
{
    /**
     * Display the fines settings page (tier builder).
     */
    public function index(): View
    {
        $clearance = $this->latestClearance();

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

        // Sort tiers by min_points descending for consistent storage
        $tiers = collect($request->tiers)->map(function ($tier) {
            return [
                'min_points' => (int) $tier['min_points'],
                'max_points' => isset($tier['max_points']) && $tier['max_points'] !== '' ? (int) $tier['max_points'] : null,
                'fine_amount' => (float) $tier['fine_amount'],
                'requirements' => $tier['requirements'] ?? '',
            ];
        })->sortByDesc('min_points')->values()->toArray();

        $clearance = $this->latestClearance();

        if ($clearance) {
            $clearance->update(['fine_tiers' => $tiers]);
        } else {
            ClearancePeriod::create([
                'department_id' => Auth::user()->department_id,
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
        $clearance = $this->latestClearance();

        if (!$clearance || empty($clearance->fine_tiers)) {
            return back()->with('error', 'You must define fine tiers before processing fines.');
        }

        // Recalculate fresh: drop previous clearance fines and cleared flags
        $students = $this->departmentStudents()->get();
        $this->resetClearance($students->pluck('id'));

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
            'activated_at' => now(),
        ]);

        return back()->with('status', "Fines processed: {$fineCount} student(s) fined. Clearance is now active.");
    }

    /**
     * Reset/deactivate the clearance period and remove all clearance fines.
     */
    public function deactivate(): RedirectResponse
    {
        $clearance = $this->latestClearance();

        if ($clearance) {
            $this->resetClearance($this->departmentStudents()->pluck('id'));
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
        $students = $this->departmentStudents()
            ->with('department')
            ->orderBy('name')
            ->get();

        $clearanceActive = Auth::user()->isClearanceActive();

        return view('clearance.list', compact('students', 'clearanceActive'));
    }

    /**
     * Mark a student as cleared.
     */
    public function clearStudent(User $user): RedirectResponse
    {
        $this->authorizeDepartment($user->department_id);

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
        $this->authorizeDepartment($user->department_id);

        $user->update(['is_cleared' => false]);

        return back()->with('status', "{$user->name} clearance has been revoked.");
    }

    private function latestClearance(): ?ClearancePeriod
    {
        return ClearancePeriod::where('department_id', Auth::user()->department_id)->latest()->first();
    }

    private function departmentStudents()
    {
        return User::role('student')->where('department_id', Auth::user()->department_id);
    }

    /**
     * Remove clearance fines (fines with no event) and reset cleared status for the given students.
     */
    private function resetClearance($studentIds): void
    {
        Fine::whereIn('user_id', $studentIds)->whereNull('event_id')->delete();
        User::whereIn('id', $studentIds)->update(['is_cleared' => false]);
    }
}
