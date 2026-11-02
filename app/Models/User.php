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

#[Fillable(['name', 'username', 'password', 'department_id', 'rfid_uid', 'year_level', 'is_cleared', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

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
     * Determine if the user is cleared (manually set by officer).
     */
    public function isCleared(): bool
    {
        if (!$this->hasRole('student')) {
            return true;
        }

        return (bool) $this->is_cleared;
    }

    /**
     * Default student password: last name with a capital first letter + last 6 digits of the ID,
     * e.g. "Juan Santos" / LGC25-105680 → Santos105680. "Dela Cruz, Juan" → Delacruz105680.
     */
    public static function defaultPassword(string $name, string $studentId): string
    {
        // ponytail: "Lastname, Firstname" or last word; officer sees the result in the flash message
        $lastName = str_contains($name, ',') ? Str::before($name, ',') : Str::afterLast(trim($name), ' ');

        return Str::ucfirst(Str::lower(preg_replace('/\s+/', '', $lastName))) . substr($studentId, -6);
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
        return $this->fines()->where('status', 'unpaid')->count() > 0;
    }

    /**
     * Check if there is an active clearance period for the student's department.
     */
    public function isClearanceActive(): bool
    {
        $clearance = $this->getActiveClearancePeriod();
        return $clearance !== null && $clearance->isActive();
    }

    /**
     * Get the active clearance period for the student's department.
     */
    public function getActiveClearancePeriod(): ?ClearancePeriod
    {
        if (!$this->department_id) {
            return null;
        }

        return ClearancePeriod::where('department_id', $this->department_id)
            ->where('semester_id', Semester::current()?->id)
            ->where('status', 'active')
            ->latest()
            ->first();
    }

    /**
     * Calculate total penalty points from this semester's events where the student was absent.
     */
    public function getTotalPenaltyPoints(): int
    {
        return (int) Attendance::where('user_id', $this->id)
            ->where('status', 'absent')
            ->join('events', 'attendances.event_id', '=', 'events.id')
            ->when(Semester::current(), fn ($q, $semester) => $q->where('events.start_time', '>=', $semester->starts_at))
            ->sum('events.penalty_points');
    }

    /**
     * Get the matched fine tier for this student based on their accumulated points.
     * Returns null if no active clearance period or no matching tier.
     */
    public function getMatchedTier(): ?array
    {
        $clearance = $this->getActiveClearancePeriod();

        if (!$clearance) {
            return null;
        }

        return $clearance->matchTier($this->getTotalPenaltyPoints());
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
