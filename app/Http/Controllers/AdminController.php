<?php

namespace App\Http\Controllers;

use App\Models\ClearancePeriod;
use App\Models\Department;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function departments(): View
    {
        $departments = Department::all();
        return view('admin.departments', compact('departments'));
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:departments,code',
        ]);

        Department::create($validated);

        return back()->with('status', 'Department added.');
    }

    public function officers(): View
    {
        $officers = User::role('officer')->with('department')->get();
        $departments = Department::all();
        return view('admin.officers', compact('officers', 'departments'));
    }

    public function storeOfficer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username',
            'password' => 'required|min:8',
            'department_id' => 'required|exists:departments,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
            'department_id' => $validated['department_id'],
        ]);

        $user->assignRole('officer');

        return back()->with('status', 'Officer added.');
    }

    private const YEAR_LEVELS = ['First Year', 'Second Year', 'Third Year', 'Fourth Year', 'Fifth Year'];

    public function semesters(): View
    {
        $semesters = Semester::latest('id')->get();
        $yearLevels = self::YEAR_LEVELS;
        return view('admin.semesters', compact('semesters', 'yearLevels'));
    }

    /**
     * Start a new semester for every department: fresh penalty points, carried-over fine tiers,
     * everyone un-cleared, and optionally deactivate a graduating year level.
     */
    public function startSemester(Request $request): RedirectResponse
    {
        $current = Semester::current();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'starts_at' => array_filter(['required', 'date', $current ? 'after:' . $current->starts_at->toDateString() : null]),
            'deactivate_year_level' => ['nullable', Rule::in(self::YEAR_LEVELS)],
        ]);

        $deactivated = DB::transaction(function () use ($validated) {
            $semester = Semester::create(['name' => $validated['name'], 'starts_at' => $validated['starts_at']]);

            ClearancePeriod::query()->update(['status' => 'inactive']);

            foreach (Department::all() as $department) {
                $previous = ClearancePeriod::where('department_id', $department->id)->latest('id')->first();

                if ($previous) {
                    ClearancePeriod::create([
                        'department_id' => $department->id,
                        'semester_id' => $semester->id,
                        'status' => 'inactive',
                        'fine_tiers' => $previous->fine_tiers,
                    ]);
                }
            }

            User::role('student')->update(['is_cleared' => false]);

            return empty($validated['deactivate_year_level']) ? 0
                : User::role('student')->where('year_level', $validated['deactivate_year_level'])->update(['is_active' => false]);
        });

        return back()->with('status', "{$validated['name']} started. Fine tiers were carried over and every student is now not cleared."
            . ($deactivated ? " {$deactivated} student(s) deactivated." : ''));
    }
}
