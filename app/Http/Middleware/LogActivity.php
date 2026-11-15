<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Fine;
use App\Models\FineExemption;
use App\Models\Survey;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every action that changes something, in one place, so new actions are logged without
 * touching their controllers. Successful actions only, plus failed logins. Edits also record what
 * changed (before → after). Not logged: card taps (each is already an attendance record with the
 * officer who scanned it).
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
        'FineExemptionController@store' => 'Exempted a student from an event',
        'FineExemptionController@destroy' => 'Removed an exemption',
        'AdminStudentController@update' => 'Edited a student',
        'AdminStudentController@destroy' => 'Deleted a student',
        'FineController@payGroup' => 'Recorded a payment',
        'FineController@markAsPaid' => 'Recorded a payment',
        'BackupController@store' => 'Made a backup',
        'BackupController@download' => 'Downloaded a backup',
    ];

    private const SKIP = ['AttendanceController@logRfid'];

    /** Column => how it's called in "what changed"; columns not listed (timestamps, tokens) aren't shown. */
    private const FIELDS = [
        'name' => 'name', 'username' => 'student ID', 'year_level' => 'year level', 'department_id' => 'department',
        'rfid_uid' => 'RFID card', 'is_active' => 'active', 'password' => 'password', 'code' => 'code',
        'start_time' => 'start', 'end_time' => 'end', 'year_levels' => 'year levels', 'requires_survey' => 'survey required',
        'late_fine' => 'late fine', 'missed_in_fine' => 'no time in fine', 'missed_out_fine' => 'no time out fine',
        'archived_at' => 'archived', 'starts_at' => 'start', 'ends_at' => 'end', 'has_in' => 'time in', 'has_out' => 'time out',
        'in_opens_at' => 'time in opens', 'closed_at' => 'ended early', 'fines_processed' => 'absences processed',
    ];

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
        $before = $this->snapshot($request);

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
            'details' => $failed ? null : (trim(($message('status') ?? '') . "\n" . $this->changes($before)) ?: null),
            'ip_address' => $request->ip(),
        ]);

        return $response;
    }

    /**
     * The route's models (and an event's sessions) as they are before the action, to compare after.
     */
    private function snapshot(Request $request): array
    {
        $models = [];
        foreach ($request->route()?->parameters() ?? [] as $value) {
            if ($value instanceof Model) {
                $models[] = $value;
                if ($value instanceof Event) {
                    array_push($models, ...$value->sessions()->get()->all());
                }
            }
        }

        return array_map(fn (Model $model) => [$model, $model->getAttributes()], $models);
    }

    /**
     * "year level: Second Year → Third Year; RFID card changed", for what the action changed.
     */
    private function changes(array $before): string
    {
        $lines = [];
        foreach ($before as [$model, $old]) {
            $fresh = $model->fresh();
            $prefix = $model instanceof EventSession ? $model->label() . ' ' : '';
            if (!$fresh) {
                if ($model instanceof EventSession) {
                    $lines[] = $model->label() . ' session removed';
                }
                continue;
            }
            foreach (self::FIELDS as $column => $label) {
                if (!array_key_exists($column, $old) || (string) $old[$column] === (string) $fresh->getAttributes()[$column]) {
                    continue;
                }
                $new = $fresh->getAttributes()[$column];
                $lines[] = $prefix . match ($column) {
                    'password' => 'password reset',
                    'rfid_uid' => $new ? ($old[$column] ? 'RFID card replaced' : 'RFID card added') : 'RFID card removed',
                    default => "$label: " . $this->show($column, $old[$column]) . ' → ' . $this->show($column, $new),
                };
            }
        }

        return $lines ? 'Changed: ' . implode('; ', $lines) : '';
    }

    private function show(string $column, $value): string
    {
        return match (true) {
            $value === null || $value === '' => 'none',
            $column === 'department_id' => Department::find($value)?->code ?? (string) $value,
            in_array($column, ['is_active', 'requires_survey', 'has_in', 'has_out', 'fines_processed'], true) => $value ? 'yes' : 'no',
            in_array($column, ['late_fine', 'missed_in_fine', 'missed_out_fine'], true) => '₱' . number_format((float) $value, 2),
            $column === 'year_levels' => implode(', ', json_decode($value, true) ?: []) ?: 'all',
            in_array($column, ['start_time', 'end_time', 'starts_at', 'ends_at', 'in_opens_at', 'closed_at', 'archived_at'], true)
                => \Illuminate\Support\Carbon::parse($value)->format('M j, g:i A'),
            default => (string) $value,
        };
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
                $value instanceof FineExemption => "{$value->user?->name} ({$value->user?->username}), {$value->event?->name}",
                is_string($value) => $value,
                default => null,
            };
            if ($text) {
                return $text;
            }
        }

        if (is_numeric($request->input('user_id')) && $student = User::find($request->input('user_id'))) {
            return "{$student->name} ({$student->username})"; // recording a payment
        }

        $name = $request->input('name');
        $id = $request->input('username');

        return is_string($name) ? trim($name . ($id && is_string($id) ? " ({$id})" : '')) : null;
    }
}
