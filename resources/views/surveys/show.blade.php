@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto bg-white shadow-sm sm:rounded-lg overflow-hidden">
    <div class="p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-2">{{ $survey->title }}</h1>
        <p class="text-gray-600 mb-6">{{ $survey->description }}</p>

        <form action="{{ route('surveys.show', $survey) }}" method="POST">
            @csrf

            <div class="space-y-6">
                @foreach($survey->questions as $question)
                    <div>
                        <label for="q_{{ $question->id }}" class="block text-sm font-bold text-gray-700 mb-2">
                            {{ $loop->iteration }}. {{ $question->question_text }}
                        </label>
                        <textarea name="answers[{{ $question->id }}]" id="q_{{ $question->id }}" rows="2" required
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>
                @endforeach
            </div>

            <div class="mt-8 border-t pt-6 flex justify-end">
                <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-6 rounded-md hover:bg-blue-700 transition">
                    Submit Survey
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
