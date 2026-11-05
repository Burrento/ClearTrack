<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function rfidScanner(Event $event): View
    {
        $this->authorizeDepartment($event->department_id);
        return view('attendance.rfid', compact('event'));
    }

    /**
     * Records a tap. The scanner page posts JSON (no reload, so taps aren't lost while a page
     * loads); a plain form post still works and redirects back.
     */
    public function logRfid(Request $request, Event $event): RedirectResponse|JsonResponse
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
            return $this->scanError($request, 'Student not found or not in this department.');
        }

        if (!$event->isOpenForAttendance()) {
            return $this->scanError($request, 'Attendance is closed: the event is not running or absences were already processed.');
        }

        // First tap wins: tapping again later must not turn "present" into "late"
        $attendance = Attendance::firstOrCreate(
            ['event_id' => $event->id, 'user_id' => $user->id],
            ['status' => $event->tapStatus(), 'verified_by_id' => Auth::id()]
        );

        $scan = [
            'name' => $user->name,
            'status' => $attendance->status,
            'repeat' => !$attendance->wasRecentlyCreated,
            'at' => $attendance->created_at->format('g:i:s A'),
        ];

        if ($request->wantsJson()) {
            $counts = $event->attendances()->whereIn('status', ['present', 'late'])
                ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

            return response()->json($scan + ['counts' => ['present' => $counts['present'] ?? 0, 'late' => $counts['late'] ?? 0]]);
        }

        // Shown full-width on the scanner page rather than as the generic status flash
        return back()->with('scanned', $scan);
    }

    private function scanError(Request $request, string $message): RedirectResponse|JsonResponse
    {
        return $request->wantsJson()
            ? response()->json(['message' => $message], 422)
            : back()->withErrors(['rfid_uid' => $message]);
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
