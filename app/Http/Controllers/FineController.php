<?php

namespace App\Http\Controllers;

use App\Models\Fine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FineController extends Controller
{
    public function index(): View
    {
        $fines = Fine::whereHas('event', function ($query) {
            $query->where('department_id', Auth::user()->department_id);
        })->with(['user', 'event'])->orderBy('created_at', 'desc')->get();

        return view('fines.index', compact('fines'));
    }

    public function markAsPaid(Fine $fine): RedirectResponse
    {
        if ($fine->event->department_id !== Auth::user()->department_id) {
            abort(403);
        }

        $fine->update(['status' => 'paid']);

        return back()->with('status', 'Fine marked as paid.');
    }
}
