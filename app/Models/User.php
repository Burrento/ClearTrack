<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'username', 'password', 'department_id', 'rfid_uid', 'year_level', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    public const YEAR_LEVELS = ['First Year', 'Second Year', 'Third Year', 'Fourth Year'];

    /**
     * Student IDs as the school issues them: LGC, a 2-digit year, then dash-separated numbers,
     * e.g. LGC25-105680, LGC26-6578, LGC26-2-0014.
     */
    public const STUDENT_ID_PATTERN = '/^LGC\d{2}(-\d+)+$/';

    /**
     * Get the department that the user belongs to.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the attendances for the user.
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Get the survey responses for the user.
     */
    public function surveyResponses()
    {
        return $this->hasMany(SurveyResponse::class);
    }

    /**
     * Get the fines for the user.
     */
    public function fines()
    {
        return $this->hasMany(Fine::class);
    }

    /**
     * A student is cleared once every fine is paid; there is no separate clearance step.
     */
    public function isCleared(): bool
    {
        return !$this->hasRole('student') || !$this->hasUnpaidFines();
    }

    /**
     * Default student password: last name with a capital first letter + the number after the ID's
     * last dash, e.g. "Juan Santos" / LGC25-105680 → Santos105680, LGC26-6578 → Santos6578,
     * LGC26-2-0014 → Santos0014. "Dela Cruz, Juan" → Delacruz105680.
     */
    public static function defaultPassword(string $name, string $studentId): string
    {
        // ponytail: "Lastname, Firstname" or last word; officer sees the result in the flash message
        $lastName = str_contains($name, ',') ? Str::before($name, ',') : Str::afterLast(trim($name), ' ');

        $number = preg_match('/(\d+)$/', $studentId, $m) ? $m[1] : substr($studentId, -6);

        return Str::ucfirst(Str::lower(preg_replace('/\s+/', '', $lastName))) . $number;
    }

    /**
     * A card number as stored: hex digits only, upper case ("04:a1-b2" → "04A1B2"), or null if empty.
     */
    public static function normalizeRfid($rfid): ?string
    {
        return strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string) $rfid)) ?: null;
    }

    /**
     * RFID serial key grouped for display, e.g. 04:A1:B2:C3. Stored without separators.
     */
    public function formattedRfid(): ?string
    {
        return $this->rfid_uid ? implode(':', str_split($this->rfid_uid, 2)) : null;
    }

    /**
     * Check if the student has unpaid fines.
     * Includes unpaid fines from earlier semesters; add ->where('semester_id', Semester::current()?->id)
     * here if previous semesters' fines should stop blocking clearance.
     */
    public function hasUnpaidFines(): bool
    {
        return $this->fines()->where('status', 'unpaid')->exists();
    }

    /**
     * Adds unpaid_total (sum of unpaid fines, null when none) to each student in a list, without a query per row.
     */
    public function scopeWithUnpaidTotal($query)
    {
        return $query->withSum(['fines as unpaid_total' => fn ($q) => $q->where('status', 'unpaid')], 'amount');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
