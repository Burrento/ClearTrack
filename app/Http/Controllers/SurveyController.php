<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SurveyController extends Controller
{
    // Officer Methods
    public function create(Event $event): View
    {
        $this->authorizeDepartment($event);
        return view('surveys.create', compact('event'));
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeDepartment($event);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'questions' => 'required|array|min:1',
            'questions.*' => 'required|string',
        ]);

        $survey = Survey::create([
            'event_id' => $event->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
        ]);

        foreach ($validated['questions'] as $questionText) {
            SurveyQuestion::create([
                'survey_id' => $survey->id,
                'question_text' => $questionText,
            ]);
        }

        return redirect()->route('events.show', $event)->with('status', 'Survey created successfully.');
    }

    // Student Methods
    public function show(Survey $survey): View
    {
        return view('surveys.show', compact('survey'));
    }

    public function submit(Request $request, Survey $survey): RedirectResponse
    {
        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*' => 'required|string',
        ]);

        foreach ($validated['answers'] as $questionId => $answer) {
            SurveyResponse::updateOrCreate(
                ['survey_question_id' => $questionId, 'user_id' => Auth::id()],
                ['answer' => $answer]
            );
        }

        return redirect()->route('student.events')->with('status', 'Survey submitted successfully.');
    }

    private function authorizeDepartment(Event $event)
    {
        if ($event->department_id !== Auth::user()->department_id) {
            abort(403);
        }
    }
}
