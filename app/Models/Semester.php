<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'starts_at'])]
class Semester extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
        ];
    }

    /**
     * The semester in progress: the one the admin started most recently.
     */
    public static function current(): ?self
    {
        return static::latest('id')->first();
    }
}
