<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['event_id', 'title', 'description'])]
class Survey extends Model
{
    public function questions()
    {
        return $this->hasMany(SurveyQuestion::class);
    }
}
