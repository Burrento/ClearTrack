<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    /**
     * Abort with 403 unless the resource belongs to the current user's department.
     */
    protected function authorizeDepartment($departmentId): void
    {
        if ($departmentId !== Auth::user()->department_id) {
            abort(403);
        }
    }
}
