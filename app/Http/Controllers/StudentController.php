<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(): View
    {
        $students = User::role('student')
            ->where('department_id', auth()->user()->department_id)
            ->with('department')
            ->withUnpaidTotal()
            ->get();

        return view('officer.students.index', compact('students'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'username' => strtoupper(trim((string) $request->username)),
            'rfid_uid' => $this->normalizeRfid($request->rfid_uid),
        ]);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'regex:' . User::STUDENT_ID_PATTERN, 'unique:users'],
            'rfid_uid' => ['nullable', 'string', 'max:255', 'unique:users'],
            'year_level' => ['required', 'string', Rule::in(User::YEAR_LEVELS)],
        ]);

        $password = User::defaultPassword($request->name, $request->username);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'password' => Hash::make($password),
            'department_id' => auth()->user()->department_id,
            'rfid_uid' => $request->rfid_uid,
            'year_level' => $request->year_level,
        ]);

        $user->assignRole('student');

        return redirect()->route('students.index')->with('status', 'Student registered. Default password: ' . $password
            . '. RFID card: ' . ($user->formattedRfid() ?? 'None'));
    }

    /**
     * "Who-is": whose RFID card is this? Read-only. Students of other departments are only
     * reported as such, without their details.
     */
    public function whoIs(Request $request): View
    {
        $uid = $this->normalizeRfid($request->query('rfid'));
        $student = $uid ? User::role('student')->where('rfid_uid', $uid)->first() : null;
        $ownDepartment = $student && $student->department_id === auth()->user()->department_id;

        return view('officer.students.who-is', compact('uid', 'student', 'ownDepartment'));
    }

    public function edit(User $user): View
    {
        $this->authorizeDepartment($user->department_id);
        abort_unless($user->hasRole('student'), 404);

        return view('officer.students.edit', ['student' => $user]);
    }

    /**
     * Update a student's name or RFID card (e.g. a replacement card).
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeDepartment($user->department_id);
        abort_unless($user->hasRole('student'), 404);

        $request->merge([
            'username' => strtoupper(trim((string) $request->username)),
            'rfid_uid' => $this->normalizeRfid($request->rfid_uid),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'regex:' . User::STUDENT_ID_PATTERN, Rule::unique('users')->ignore($user->id)],
            'year_level' => ['required', Rule::in(User::YEAR_LEVELS)],
            'rfid_uid' => ['nullable', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]);

        // A corrected name or ID doesn't change the password on its own; the officer can reset it to the
        // default for the corrected details (also how a student who forgot their password gets back in).
        $password = $request->boolean('reset_password') ? User::defaultPassword($validated['name'], $validated['username']) : null;
        $user->update($validated + ($password ? ['password' => Hash::make($password)] : []));

        return redirect()->route('students.index')->with('status', "Changes saved for {$user->name}. RFID card: " . ($user->formattedRfid() ?? 'None')
            . ($password ? ". Password reset to: {$password}" : ''));
    }

    /**
     * Accept serials typed with colons/dashes/spaces; store bare hex to match scans.
     */
    private const YEAR_LEVELS = User::YEAR_LEVELS;
    private const CSV_COLUMNS = ['name', 'student_id', 'year_level']; // cards are tapped in later with Edit

    /**
     * An empty CSV with just the headers, to fill in with Excel or Google Sheets.
     */
    public function importTemplate()
    {
        return response()->streamDownload(function () {
            echo "\u{FEFF}" . implode(',', self::CSV_COLUMNS) . "\r\n"; // BOM so Excel reads ñ and other accents
        }, 'cleartrack-students-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Registers every student in the CSV into the officer's department, or none: if any row has a
     * problem, nothing is imported and each problem is listed. Students already registered are skipped.
     * Extra columns (an old template's rfid, notes) are ignored.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate(['csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $handle = fopen($request->file('csv')->getRealPath(), 'r');
        $header = array_map(fn ($h) => strtolower(trim(str_replace("\u{FEFF}", '', $h))), fgetcsv($handle) ?: []);

        if ($missing = array_diff(['name', 'student_id', 'year_level'], $header)) {
            return back()->withErrors(['csv' => 'The file is missing these columns: ' . implode(', ', $missing) . '. Start from the template.']);
        }

        $years = collect(self::YEAR_LEVELS)->keyBy(fn ($y) => strtolower($y));
        $existing = User::pluck('username')->flip();
        $rows = $problems = $seenIds = [];
        $skipped = 0;
        $line = 1;

        while (($cells = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue; // blank line
            }

            $row = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), ''));
            $name = trim($row['name']);
            $id = strtoupper(trim($row['student_id']));
            $year = $years->get(strtolower(trim($row['year_level'])));

            $rowProblems = array_filter([
                $name === '' ? 'name is empty' : null,
                !preg_match(User::STUDENT_ID_PATTERN, $id) ? "student ID \"{$row['student_id']}\" should be LGC, the 2-digit year, then numbers, e.g. LGC26-6578" : null,
                isset($seenIds[$id]) ? "student ID {$id} is also on line {$seenIds[$id]}" : null,
                !$year ? "year level \"{$row['year_level']}\" should be one of: " . implode(', ', self::YEAR_LEVELS) : null,
            ]);

            $seenIds[$id] ??= $line;

            if ($rowProblems) {
                $problems[] = "Line {$line}: " . implode('; ', $rowProblems) . '.';
            } elseif (isset($existing[$id])) {
                $skipped++;
            } else {
                $rows[] = compact('name', 'id', 'year');
            }
        }
        fclose($handle);

        if ($problems) {
            return back()->withErrors(['csv' => 'Nothing was imported. Fix these lines and upload the file again:'])
                ->with('csv_problems', array_slice($problems, 0, 50));
        }

        // At the normal bcrypt cost, 1,000 hashes take minutes on the 1 GB server and Nginx gives up at 60 s.
        // Default passwords are guessable from name + ID anyway, so they're hashed cheaply here and Laravel
        // rehashes them at the normal cost on first login. Inserted directly: the "hashed" cast rejects the lower cost.
        set_time_limit(300);
        DB::transaction(function () use ($rows) {
            $now = now();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('users')->insert(array_map(fn ($r) => [
                    'name' => $r['name'],
                    'username' => $r['id'],
                    'password' => Hash::make(User::defaultPassword($r['name'], $r['id']), ['rounds' => 8]),
                    'department_id' => auth()->user()->department_id,
                    'year_level' => $r['year'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }
            User::whereIn('username', array_column($rows, 'id'))->get()->each->assignRole('student');
        });

        return redirect()->route('students.index')->with('status', count($rows) . ' ' . Str::plural('student', count($rows)) . ' imported.'
            . ($skipped ? " {$skipped} already registered, so skipped." : '')
            . ' Each default password is their last name plus the number at the end of their ID.');
    }

    private function normalizeRfid($rfid): ?string
    {
        return User::normalizeRfid($rfid);
    }

    /**
     * Deactivate (e.g. graduated) or reactivate a student. Deactivated students can't log in
     * and are skipped by attendance, absences and clearance.
     */
    public function toggleActive(User $user): RedirectResponse
    {
        $this->authorizeDepartment($user->department_id);
        abort_unless($user->hasRole('student'), 404);

        $user->update(['is_active' => !$user->is_active]);

        return back()->with('status', "{$user->name} has been " . ($user->is_active ? 'reactivated.' : 'deactivated.'));
    }
}
