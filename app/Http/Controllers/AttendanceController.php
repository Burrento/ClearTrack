<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function rfidScanner(Event $event): View
    {
        $this->authorizeDepartment($event->department_id);
        return view('attendance.rfid', compact('event'));
    }

    public function logRfid(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeDepartment($event->department_id);

        $validated = $request->validate([
            'rfid_uid' => 'required|string',
        ]);

        $user = User::where('rfid_uid', $validated['rfid_uid'])
            ->where('department_id', $event->department_id)
            ->where('is_active', true)
            ->first();

        if (!$user) {
            return back()->withErrors(['rfid_uid' => 'Student not found or not in this department.']);
        }

        if (!$event->isOpenForAttendance()) {
            return back()->withErrors(['rfid_uid' => 'Attendance is closed: the event is not running or absences were already processed.']);
        }

        Attendance::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $user->id],
            ['status' => 'present', 'verified_by_id' => Auth::id()]
        );

        // Shown full-width on the scanner page rather than as the generic status flash
        return back()->with('scanned', $user->name);
    }

    // Student Methods
    public function studentEvents(): View
    {
        $user = Auth::user();
        $events = Event::where('department_id', $user->department_id)
            ->orderBy('start_time', 'desc')
            ->get();

        return view('attendance.student_events', compact('events'));
    }
}
