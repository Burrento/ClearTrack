<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(): View
    {
        $students = User::role('student')
            ->where('department_id', auth()->user()->department_id)
            ->with('department')
            ->get();
            
        return view('officer.students.index', compact('students'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'rfid_uid' => ['nullable', 'string', 'max:255', 'unique:users'],
            'year_level' => ['required', 'string', 'in:First Year,Second Year,Third Year,Fourth Year,Fifth Year'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'password' => Hash::make('Dwcc' . $request->username),
            'department_id' => auth()->user()->department_id,
            'rfid_uid' => $request->rfid_uid,
            'year_level' => $request->year_level,
        ]);

        $user->assignRole('student');

        return redirect()->route('students.index')->with('status', 'Student registered successfully. Default password is Dwcc' . $request->username);
    }
}
