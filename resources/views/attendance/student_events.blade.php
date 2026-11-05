@extends('layouts.app')

@section('content')
@php
    $attendances = Auth::user()->attendances()->whereIn('event_id', $events->pluck('id'))->get()->keyBy('event_session_id');
    $answeredQuestionIds = Auth::user()->surveyResponses()->pluck('survey_question_id');
@endphp

<x-page-header title="My events" description="Your attendance for each event in your department." />

@if($events->isEmpty())
    <div class="panel px-6 py-14 text-center text-ink-soft">No events yet for your department.</div>
@else
    <ul class="panel divide-y divide-rule">
        @foreach($events as $event)
            @php $tapped = $event->sessions->contains(fn ($s) => $attendances->has($s->id)); @endphp
            <li class="flex flex-wrap items-start justify-between gap-4 px-5 py-4">
                <div class="min-w-0">
                    <p class="font-semibold">{{ $event->name }}</p>
                    <p class="text-sm text-ink-soft">{{ $event->schedule() }}</p>
                    @if($tapped)
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach($event->sessions as $session)
                                @php $a = $attendances->get($session->id); @endphp
                                <li class="flex flex-wrap items-center gap-2">
                                    <span class="w-20 font-semibold">{{ $session->label() }}</span>
                                    @if(!$a || $a->status === 'absent')
                                        @if($a || $event->fines_processed) <x-badge tone="bad">Absent</x-badge> @else <span class="text-ink-soft">No taps yet</span> @endif
                                    @else
                                        @if($a->time_in_at)
                                            <x-badge :tone="$a->status === 'late' ? 'wait' : 'good'">{{ $a->status === 'late' ? 'Late' : 'In' }} {{ $a->time_in_at->format('g:i A') }}</x-badge>
                                        @else
                                            <x-badge tone="bad">No time in</x-badge>
                                        @endif
                                        @if($a->time_out_at)
                                            <x-badge tone="good">Out {{ $a->time_out_at->format('g:i A') }}</x-badge>
                                        @elseif($event->fines_processed)
                                            <x-badge tone="bad">No time out</x-badge>
                                        @endif
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    @if($tapped)
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
