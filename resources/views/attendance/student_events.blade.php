@extends('layouts.app')

@section('content')
<div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
    <div class="p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Upcoming & Recent Events</h1>

        <div class="space-y-4">
            @forelse($events as $event)
                <div class="border rounded-lg p-4 flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">{{ $event->name }}</h3>
                        <p class="text-sm text-gray-500">{{ $event->start_time }} - {{ $event->end_time }}</p>
                    </div>
                    <div>
                        @php
                            $attendance = Auth::user()->attendances()->where('event_id', $event->id)->first();
                        @endphp

                        @if($attendance)
                            <div class="flex flex-col items-end space-y-2">
                                <span class="px-3 py-1 rounded-full text-xs font-bold 
                                    {{ $attendance->status == 'present' ? 'bg-green-100 text-green-700' : 
                                       ($attendance->status == 'pending_verification' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                    {{ ucfirst(str_replace('_', ' ', $attendance->status)) }}
                                </span>

                                @if($event->requires_survey && $event->survey)
                                    @php
                                        $surveyCompleted = Auth::user()->surveyResponses()
                                            ->whereIn('survey_question_id', $event->survey->questions->pluck('id'))
                                            ->exists();
                                    @endphp

                                    @if(!$surveyCompleted)
                                        <a href="{{ route('surveys.show', $event->survey) }}" class="text-xs text-purple-600 font-bold hover:underline">Complete Survey</a>
                                    @else
                                        <span class="text-xs text-green-600 font-bold italic">Survey Completed</span>
                                    @endif
                                @endif
                            </div>
                        @else
                            <a href="{{ route('attendance.upload', $event) }}" class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700">Upload Selfie</a>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-gray-500 italic text-center py-8">No events found for your department.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
