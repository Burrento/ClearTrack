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
        $this->authorizeDepartment($event);
        return view('attendance.rfid', compact('event'));
    }

    public function logRfid(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeDepartment($event);

        $validated = $request->validate([
            'rfid_uid' => 'required|string',
        ]);

        $user = User::where('rfid_uid', $validated['rfid_uid'])
            ->where('department_id', $event->department_id)
            ->first();

        if (!$user) {
            return back()->withErrors(['rfid_uid' => 'Student not found or not in this department.']);
        }

        Attendance::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $user->id],
            ['status' => 'present', 'verified_by_id' => Auth::id()]
        );

        return back()->with('status', "Attendance logged for {$user->name}.");
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

    public function uploadSelfieForm(Event $event): View
    {
        return view('attendance.upload', compact('event'));
    }

    public function storeSelfie(Request $request, Event $event): RedirectResponse
    {
        $request->validate([
            'photo' => 'required|image|max:2048',
        ]);

        $path = $request->file('photo')->store('selfies', 'public');

        Attendance::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => Auth::id()],
            ['status' => 'pending_verification', 'photo_path' => $path]
        );

        return redirect()->route('student.events')->with('status', 'Selfie uploaded and pending verification.');
    }

    // Officer Methods
    public function verifySelfies(Event $event): View
    {
        $this->authorizeDepartment($event);
        
        $pendingAttendances = Attendance::where('event_id', $event->id)
            ->where('status', 'pending_verification')
            ->with('user')
            ->get();

        return view('attendance.verify', compact('event', 'pendingAttendances'));
    }

    public function approveAttendance(Attendance $attendance, $status): RedirectResponse
    {
        $this->authorizeDepartment($attendance->event);

        if (!in_array($status, ['present', 'absent'])) {
            abort(400);
        }

        $attendance->update([
            'status' => $status,
            'verified_by_id' => Auth::id()
        ]);

        return back()->with('status', "Attendance marked as {$status}.");
    }

    private function authorizeDepartment(Event $event)
    {
        if ($event->department_id !== Auth::user()->department_id) {
            abort(403);
        }
    }
}
