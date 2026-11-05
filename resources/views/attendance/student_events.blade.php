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
                            @case('late') <x-badge tone="wait">Late{{ $event->late_fine > 0 ? ', ₱' . number_format($event->late_fine, 2) . ' fine' : '' }}</x-badge> @break
                            @default <x-badge tone="bad">Absent{{ $event->absent_fine > 0 ? ', ₱' . number_format($event->absent_fine, 2) . ' fine' : '' }}</x-badge>
                        @endswitch

                        @if($event->requires_survey && $event->survey)
                            @if($event->survey->questions->pluck('id')->intersect($answeredQuestionIds)->isNotEmpty())
                                <x-badge tone="good">Survey done</x-badge>
                            @else
                                <a href="{{ route('surveys.show', $event->survey) }}" class="btn btn-secondary btn-sm">Answer survey</a>
                            @endif
                        @endif
                    @elseif($event->isOpenForAttendance())
                        <x-badge tone="wait">Happening now: tap your ID</x-badge>
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
