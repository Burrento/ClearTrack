@extends('layouts.app')

@section('content')
@php
    $attendances = Auth::user()->attendances()->whereIn('event_id', $events->pluck('id'))->get()->keyBy('event_id');
    $answeredQuestionIds = Auth::user()->surveyResponses()->pluck('survey_question_id');
@endphp

<x-page-header title="My events" description="Your attendance for each event in your department." />

@if($events->isEmpty())
    <div class="panel px-6 py-14 text-center text-ink-soft">No events yet for your department.</div>
@else
    <ul class="panel divide-y divide-rule">
        @foreach($events as $event)
            @php $attendance = $attendances->get($event->id); @endphp
            <li class="flex flex-wrap items-center justify-between gap-4 px-5 py-4">
                <div class="min-w-0">
                    <p class="font-semibold">{{ $event->name }}</p>
                    <p class="text-sm text-ink-soft">{{ $event->schedule() }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    @if($attendance)
                        @switch($attendance->status)
                            @case('present') <x-badge tone="good">Present</x-badge> @break
                            @case('pending_verification') <x-badge tone="wait">Selfie waiting for review</x-badge> @break
                            @default <x-badge tone="bad">Absent, +{{ $event->penalty_points }} {{ \Illuminate\Support\Str::plural('pt', $event->penalty_points) }}</x-badge>
                        @endswitch

                        @if($event->requires_survey && $event->survey)
                            @if($event->survey->questions->pluck('id')->intersect($answeredQuestionIds)->isNotEmpty())
                                <x-badge tone="good">Survey done</x-badge>
                            @else
                                <a href="{{ route('surveys.show', $event->survey) }}" class="btn btn-secondary btn-sm">Answer survey</a>
                            @endif
                        @endif
                    @elseif($event->isOpenForAttendance())
                        <a href="{{ route('attendance.upload', $event) }}" class="btn btn-primary btn-sm">Send a selfie</a>
                    @elseif($event->phase() === 'upcoming')
                        <x-badge tone="muted">Upcoming</x-badge>
                    @else
                        <x-badge tone="muted">Not recorded yet</x-badge>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
@endif
@endsection
