<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(): View
    {
        $students = User::role('student')
            ->where('department_id', auth()->user()->department_id)
            ->with('department')
            ->withUnpaidTotal()
            ->get();

        return view('officer.students.index', compact('students'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'username' => strtoupper(trim((string) $request->username)),
            'rfid_uid' => $this->normalizeRfid($request->rfid_uid),
        ]);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'regex:/^LGC\d{2}-\d{6}$/', 'unique:users'],
            'rfid_uid' => ['nullable', 'string', 'max:255', 'unique:users'],
            'year_level' => ['required', 'string', 'in:First Year,Second Year,Third Year,Fourth Year,Fifth Year'],
        ]);

        $password = User::defaultPassword($request->name, $request->username);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'password' => Hash::make($password),
            'department_id' => auth()->user()->department_id,
            'rfid_uid' => $request->rfid_uid,
            'year_level' => $request->year_level,
        ]);

        $user->assignRole('student');

        return redirect()->route('students.index')->with('status', 'Student registered. Default password: ' . $password
            . '. RFID card: ' . ($user->formattedRfid() ?? 'None'));
    }

    public function edit(User $user): View
    {
        $this->authorizeDepartment($user->department_id);
        abort_unless($user->hasRole('student'), 404);

        return view('officer.students.edit', ['student' => $user]);
    }

    /**
     * Update a student's name or RFID card (e.g. a replacement card).
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeDepartment($user->department_id);
        abort_unless($user->hasRole('student'), 404);

        $request->merge(['rfid_uid' => $this->normalizeRfid($request->rfid_uid)]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rfid_uid' => ['nullable', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->update($validated);

        return redirect()->route('students.index')->with('status', "Changes saved for {$user->name}. RFID card: " . ($user->formattedRfid() ?? 'None'));
    }

    /**
     * Accept serials typed with colons/dashes/spaces; store bare hex to match scans.
     */
    private function normalizeRfid($rfid): ?string
    {
        return strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string) $rfid)) ?: null;
    }

    /**
     * Deactivate (e.g. graduated) or reactivate a student. Deactivated students can't log in
     * and are skipped by attendance, absences and clearance.
     */
    public function toggleActive(User $user): RedirectResponse
    {
        $this->authorizeDepartment($user->department_id);
        abort_unless($user->hasRole('student'), 404);

        $user->update(['is_active' => !$user->is_active]);

        return back()->with('status', "{$user->name} has been " . ($user->is_active ? 'reactivated.' : 'deactivated.'));
    }
}
