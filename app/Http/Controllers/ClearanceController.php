<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ClearanceController extends Controller
{
    /**
     * Every active student with what they still owe. Clearance is automatic: no unpaid fines means cleared.
     */
    public function index(): View
    {
        $students = User::role('student')
            ->where('department_id', Auth::user()->department_id)
            ->where('is_active', true)
            ->withUnpaidTotal()
            ->orderBy('name')
            ->get();

        return view('clearance.list', compact('students'));
    }
}
