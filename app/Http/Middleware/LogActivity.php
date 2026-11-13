<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Fine;
use App\Models\Survey;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every action that changes something, in one place, so new actions are logged without
 * touching their controllers. Successful actions only, plus failed logins. Not logged: card taps (each
 * is already an attendance record with the officer who scanned it) and marking fines as paid.
 */
class LogActivity
{
    /** Controller@method => what it's called in the log. Anything not listed falls back to its URL. */
    private const ACTIONS = [
        'AuthController@login' => 'Logged in',
        'AuthController@logout' => 'Logged out',
        'AuthController@updatePassword' => 'Changed their password',
        'EventController@store' => 'Created an event',
        'EventController@update' => 'Edited an event',
        'EventController@destroy' => 'Deleted an event',
        'EventController@processFines' => 'Processed absences',
        'EventController@startSession' => 'Started a session early',
        'EventController@endSession' => 'Ended a session early',
        'EventController@endEvent' => 'Ended an event early',
        'StudentController@store' => 'Registered a student',
        'StudentController@update' => 'Edited a student',
        'StudentController@toggleActive' => 'Deactivated or reactivated a student',
        'StudentController@import' => 'Imported students from CSV',
        'SurveyController@store' => 'Attached a survey',
        'SurveyController@submit' => 'Answered a survey',
        'AdminController@storeDepartment' => 'Added a department',
        'AdminController@updateDepartment' => 'Edited a department',
        'AdminController@toggleArchive' => 'Archived or restored a department',
        'AdminController@storeOfficer' => 'Added an officer',
        'AdminController@startSemester' => 'Started a new semester',
        'AdminEventController@reprocess' => 'Fixed and charged an event again',
        'AdminStudentController@update' => 'Edited a student',
        'AdminStudentController@destroy' => 'Deleted a student',
        'BackupController@store' => 'Made a backup',
        'BackupController@download' => 'Downloaded a backup',
    ];

    private const SKIP = ['AttendanceController@logRfid', 'FineController@markAsPaid', 'FineController@payGroup'];

    public function handle(Request $request, Closure $next): Response
    {
        $action = class_basename((string) $request->route()?->getActionName());
        $logged = !in_array($action, self::SKIP, true)
            && (!$request->isMethodSafe() || $action === 'BackupController@download');

        if (!$logged) {
            return $next($request);
        }

        // Before the action runs: who is doing it (logout ends that) and what it's about (delete removes that)
        $user = $request->user();
        if (!$user && $action !== 'AuthController@login') {
            return $next($request); // not logged in: it gets bounced to the login page, nothing happens
        }
        $subject = $this->subject($request);

        $response = $next($request);

        // Only messages this action flashed; the session still holds the previous page's until the next request
        $flashed = $request->hasSession() ? $request->session()->get('_flash.new', []) : [];
        $message = fn ($key) => in_array($key, $flashed, true) ? $request->session()->get($key) : null;
        $failed = $response->getStatusCode() >= 400 || $message('errors') || $message('error');

        if ($failed && $action !== 'AuthController@login') {
            return $response; // refused or invalid: nothing changed
        }

        ActivityLog::create([
            'user_id' => ($user ?? $request->user())?->id,
            'action' => $failed ? 'Failed login' : (self::ACTIONS[$action] ?? $request->method() . ' /' . $request->path()),
            'subject' => $action === 'AuthController@login' ? $request->input('username') : $subject,
            'details' => $failed ? null : $message('status'),
            'ip_address' => $request->ip(),
        ]);

        return $response;
    }

    /**
     * A short name for what the action is about: the route's model, or the name/ID typed into the form.
     */
    private function subject(Request $request): ?string
    {
        foreach ($request->route()?->parameters() ?? [] as $value) {
            $text = match (true) {
                $value instanceof User => "{$value->name} ({$value->username})",
                $value instanceof Event => $value->name,
                $value instanceof EventSession => $value->event?->name . ' – ' . $value->label(),
                $value instanceof Department => "{$value->name} ({$value->code})",
                $value instanceof Fine => '₱' . number_format($value->amount, 2) . ' from ' . $value->user?->name,
                $value instanceof Survey => $value->event?->name,
                is_string($value) => $value,
                default => null,
            };
            if ($text) {
                return $text;
            }
        }

        $name = $request->input('name');
        $id = $request->input('username');

        return is_string($name) ? trim($name . ($id && is_string($id) ? " ({$id})" : '')) : null;
    }
}
