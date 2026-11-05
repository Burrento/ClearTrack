<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventSession;
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

        if (!$event->isFor($user)) {
            return $this->scanError($request, "{$user->name} isn't expected here: this event is for {$event->audience()}.");
        }

        if (!$event->isOpenForAttendance()) {
            return $this->scanError($request, 'Attendance is closed: the event is not running or absences were already processed.');
        }

        // Which session and whether this is a time in or a time out comes from the open windows
        [$session, $action] = $event->resolveTap($user);

        // ponytail: two readers tapping the same card in the same instant can hit the unique index; tap again
        $attendance = Attendance::firstOrNew(
            ['event_session_id' => $session->id, 'user_id' => $user->id],
            ['event_id' => $event->id, 'status' => 'missed_in']
        );

        if ($action === 'in') {
            $attendance->fill([
                'status' => now()->gt($session->starts_at) ? 'late' : 'present',
                'time_in_at' => now(),
                'verified_by_id' => Auth::id(),
            ])->save();
        } elseif ($action === 'out') {
            $attendance->fill(['time_out_at' => now(), 'verified_by_id' => Auth::id()])->save();
        }

        $lastTap = $action === 'in' ? $attendance->time_in_at
            : ($action === 'out' ? $attendance->time_out_at : ($attendance->time_out_at ?? $attendance->time_in_at));

        $scan = [
            'name' => $user->name,
            'session' => $session->label(),
            'action' => $action,
            'status' => $attendance->status,
            'repeat' => $action === 'repeat',
            'at' => $lastTap?->format('g:i:s A'),
        ];

        if ($request->wantsJson()) {
            return response()->json($scan + ['counts' => [$session->name => $this->sessionCounts($session)]]);
        }

        // Shown full-width on the scanner page rather than as the generic status flash
        return back()->with('scanned', $scan);
    }

    /**
     * Tapped in (late included), late, and tapped out, for the scanner page's live counts.
     */
    public static function sessionCounts(EventSession $session): array
    {
        $rows = $session->attendances();

        return [
            'in' => (clone $rows)->whereNotNull('time_in_at')->count(),
            'late' => (clone $rows)->where('status', 'late')->count(),
            'out' => (clone $rows)->whereNotNull('time_out_at')->count(),
        ];
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
        $events = Event::forStudent($user)
            ->orderBy('start_time', 'desc')
            ->get();

        return view('attendance.student_events', compact('events'));
    }
}
