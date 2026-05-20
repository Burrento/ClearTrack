<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

        return back()->with('status', 'Department created successfully.');
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

        return back()->with('status', 'Officer created successfully.');
    }
}
