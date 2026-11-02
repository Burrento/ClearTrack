@extends('layouts.app')

@section('content')
<div class="max-w-2xl">
    <x-page-header :title="$survey->title" :back="route('student.events')" back-label="My events" :description="$survey->description" />

    <form action="{{ route('surveys.show', $survey) }}" method="POST" class="panel">
        @csrf
        <ol class="panel-body space-y-7">
            @foreach($survey->questions as $question)
                <li>
                    <label for="q_{{ $question->id }}" class="label">
                        <span class="text-ink-soft">{{ $loop->iteration }}.</span> {{ $question->question_text }}
                    </label>
                    <textarea name="answers[{{ $question->id }}]" id="q_{{ $question->id }}" rows="3" required class="input">{{ old('answers.' . $question->id) }}</textarea>
                </li>
            @endforeach
        </ol>
        @error('answers')
            <p class="field-error px-6">{{ $message }}</p>
        @enderror

        <div class="flex justify-end border-t border-rule px-5 py-4 sm:px-6">
            <button type="submit" class="btn btn-primary">Submit answers</button>
        </div>
    </form>
</div>
@endsection
