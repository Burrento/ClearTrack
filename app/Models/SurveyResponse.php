<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['survey_question_id', 'user_id', 'answer'])]
class SurveyResponse extends Model
{
    //
}
