<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'username', 'password', 'department_id', 'rfid_uid'])]
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
     * Determine if the user is cleared.
     * A student is cleared if they have no unpaid fines.
     */
    public function isCleared(): bool
    {
        if (!$this->hasRole('student')) {
            return true;
        }

        return $this->fines()->where('status', 'unpaid')->count() === 0;
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
