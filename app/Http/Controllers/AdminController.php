<?php

namespace App\Http\Controllers;

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

    public function editDepartment(Department $department): View
    {
        return view('admin.departments-edit', compact('department'));
    }

    public function updateDepartment(Request $request, Department $department): RedirectResponse
    {
        $department->update($request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', Rule::unique('departments')->ignore($department->id)],
        ]));

        return redirect()->route('admin.departments')->with('status', 'Changes saved.');
    }

    /**
     * Archive instead of delete: deleting would cascade away events and attendance. Archived
     * departments' users can't log in (AuthController::login); restoring brings everything back.
     */
    public function toggleArchive(Department $department): RedirectResponse
    {
        $department->update(['archived_at' => $department->archived_at ? null : now()]);

        return back()->with('status', $department->archived_at
            ? "{$department->name} archived. Its officers and students can't log in."
            : "{$department->name} restored.");
    }

    public function officers(): View
    {
        $officers = User::role('officer')->with('department')->get();
        $departments = Department::active()->get();
        return view('admin.officers', compact('officers', 'departments'));
    }

    public function storeOfficer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username',
            'password' => 'required|min:8',
            'department_id' => ['required', Rule::exists('departments', 'id')->whereNull('archived_at')],
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

    private const YEAR_LEVELS = User::YEAR_LEVELS;

    public function semesters(): View
    {
        $semesters = Semester::latest('id')->get();
        $yearLevels = self::YEAR_LEVELS;
        return view('admin.semesters', compact('semesters', 'yearLevels'));
    }

    /**
     * Start a new semester for every department, optionally deactivating a graduating year level.
     * New fines carry the new semester; earlier fines stay on record.
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
            Semester::create(['name' => $validated['name'], 'starts_at' => $validated['starts_at']]);

            return empty($validated['deactivate_year_level']) ? 0
                : User::role('student')->where('year_level', $validated['deactivate_year_level'])->update(['is_active' => false]);
        });

        return back()->with('status', "{$validated['name']} started."
            . ($deactivated ? " {$deactivated} student(s) deactivated." : ''));
    }
}
