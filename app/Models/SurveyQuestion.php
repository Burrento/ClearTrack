<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['survey_id', 'question_text', 'type'])]
class SurveyQuestion extends Model
{
    //
}
