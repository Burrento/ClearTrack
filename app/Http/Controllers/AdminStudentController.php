<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The admin's view of every student in every department: filter, edit (including moving them to
 * another department) and permanently delete. Officers keep their own department-scoped Students page.
 */
class AdminStudentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'department' => 'nullable|integer',
            'year' => ['nullable', Rule::in(User::YEAR_LEVELS)],
            'status' => ['nullable', Rule::in(['active', 'deactivated', 'owes', 'cleared', 'no_card'])],
        ]);

        $students = User::role('student')
            ->with('department')
            ->withUnpaidTotal()
            ->withCount(['attendances', 'fines'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")->orWhere('username', 'like', "%{$term}%")))
            ->when($filters['department'] ?? null, fn ($q, $id) => $q->where('department_id', $id))
            ->when($filters['year'] ?? null, fn ($q, $year) => $q->where('year_level', $year))
            ->when($filters['status'] ?? null, fn ($q, $status) => match ($status) {
                'active' => $q->where('is_active', true),
                'deactivated' => $q->where('is_active', false),
                'owes' => $q->whereHas('fines', fn ($f) => $f->where('status', 'unpaid')),
                'cleared' => $q->whereDoesntHave('fines', fn ($f) => $f->where('status', 'unpaid')),
                'no_card' => $q->whereNull('rfid_uid'),
            })
            ->orderBy('name')
            ->paginate(50)->withQueryString();

        $departments = Department::orderBy('code')->get();

        return view('admin.students.index', compact('students', 'departments', 'filters'));
    }

    public function edit(User $user): View
    {
        abort_unless($user->hasRole('student'), 404);
        $departments = Department::orderBy('code')->get();

        return view('admin.students.edit', ['student' => $user, 'departments' => $departments]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->hasRole('student'), 404);

        $request->merge([
            'username' => strtoupper(trim((string) $request->username)),
            'rfid_uid' => User::normalizeRfid($request->rfid_uid),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'regex:' . User::STUDENT_ID_PATTERN, Rule::unique('users')->ignore($user->id)],
            'year_level' => ['required', Rule::in(User::YEAR_LEVELS)],
            // Only a department that's open, unless the student is already in an archived one
            'department_id' => ['required', Rule::exists('departments', 'id')->where(fn ($q) => $q
                ->whereNull('archived_at')->orWhere('id', $user->department_id))],
            'rfid_uid' => ['nullable', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        $password = $request->boolean('reset_password') ? User::defaultPassword($validated['name'], $validated['username']) : null;
        $user->update($validated + ($password ? ['password' => Hash::make($password)] : []));

        return redirect()->route('admin.students')->with('status', "Changes saved for {$user->name}."
            . ($password ? " Password reset to: {$password}" : ''));
    }

    /**
     * Permanently removes the student with their attendance, fines (paid ones too) and survey answers.
     * For graduates and dropouts, deactivating keeps their history instead.
     */
    public function destroy(User $user): RedirectResponse
    {
        abort_unless($user->hasRole('student'), 404);

        $name = "{$user->name} ({$user->username})";
        $user->delete(); // attendances, fines and survey responses cascade in the database

        return redirect()->route('admin.students')->with('status', "{$name} was deleted, with all their attendance and fines.");
    }
}
