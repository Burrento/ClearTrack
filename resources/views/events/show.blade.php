@extends('layouts.app')

@section('content')
@php
    $sessions = $event->sessions;
    // One row per student, with that student's attendance per session
    $byStudent = $event->attendances()->with('user')->get()->groupBy('user_id')
        ->map(fn ($rows) => ['user' => $rows->first()->user, 'sessions' => $rows->keyBy('event_session_id')])
        ->sortBy(fn ($r) => $r['user']->name);
    $money = fn ($amount) => '₱' . number_format($amount, 2);
@endphp

<x-page-header :title="$event->name" :back="route('events.index')" back-label="Events">
    <a href="{{ route('events.edit', $event) }}" class="btn btn-secondary">Edit event</a>
</x-page-header>

<div class="-mt-4 mb-8 space-y-1 text-ink-soft">
    <p class="flex flex-wrap items-center gap-x-4 gap-y-2"><x-event-phase :event="$event" /> {{ $event->schedule() }}</p>
    <p>Fines per session: {{ $money($event->absent_fine) }} absent, {{ $money($event->late_fine) }} late,
        {{ $money($event->missed_in_fine) }} no time in, {{ $money($event->missed_out_fine) }} no time out.</p>
</div>

{{-- Actions, in the order an officer uses them --}}
<section class="panel panel-body mb-10" aria-label="Event actions">
    <div class="flex flex-wrap gap-3">
        @php $nextToOpen = $event->sessions->first(fn ($s) => $s->inOpensAt()->isFuture()); @endphp
        @if($event->isOpenForAttendance())
            <a href="{{ route('attendance.rfid', $event) }}" class="btn btn-primary btn-lg">Open RFID scanner</a>
        @elseif(!$event->fines_processed && $nextToOpen)
            <form action="{{ route('events.startEarly', $event) }}" method="POST"
                onsubmit="return confirm(@js('Start ' . strtolower($nextToOpen->label()) . ' attendance now? Time in opens immediately instead of at ' . $nextToOpen->inOpensAt()->format('g:i A') . '. Taps before ' . $nextToOpen->starts_at->format('g:i A') . ' count as on time.'))">
                @csrf
                <button type="submit" class="btn btn-primary btn-lg">Start attendance now</button>
            </form>
        @endif

        @if($event->survey)
            <span class="btn btn-secondary btn-lg cursor-default">Survey attached</span>
        @else
            <a href="{{ route('surveys.create', $event) }}" class="btn btn-secondary btn-lg">Attach survey</a>
        @endif

        @unless($event->fines_processed)
            <form action="{{ route('events.processFines', $event) }}" method="POST" class="sm:ml-auto"
                onsubmit="return confirm('Process absences now? Students who missed a session, came late or missed a time in or time out get this event\'s fines.')">
                @csrf
                <button type="submit" class="btn btn-danger btn-lg" @disabled($event->closesAt()->isFuture())>Process absences</button>
            </form>
        @endunless
    </div>
    @if(!$event->fines_processed && !$event->isOpenForAttendance() && $nextToOpen)
        <p class="hint mt-4">{{ $nextToOpen->label() }} time in normally opens at {{ $nextToOpen->inOpensAt()->format('g:i A') }}{{ $nextToOpen->inOpensAt()->isToday() ? '' : ' on ' . $nextToOpen->inOpensAt()->format('M j') }}. Start it now if students arrive early.</p>
    @endif
    @if(!$event->fines_processed)
        <p class="hint mt-2">
            @if($event->closesAt()->isFuture())
                Absences can be processed after the last time out closes at {{ $event->closesAt()->format('g:i A') }}.
            @else
                Process absences to add the fines. This also runs automatically a few minutes after the last time out closes.
            @endif
        </p>
    @endif
</section>

<section aria-labelledby="log-heading">
    <h2 id="log-heading" class="panel-title mb-4">Attendance</h2>
    <div class="panel overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Student</th>
                    @foreach($sessions as $session)
                        <th>{{ $session->label() }} in</th>
                        <th>{{ $session->label() }} out</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($byStudent as $row)
                    <tr>
                        <td class="font-semibold whitespace-nowrap">{{ $row['user']->name }}</td>
                        @foreach($sessions as $session)
                            @php $a = $row['sessions']->get($session->id); @endphp
                            @if(!$a || $a->status === 'absent')
                                <td colspan="2">
                                    @if($a) <x-badge tone="bad">Absent</x-badge> @else <span class="text-ink-soft">—</span> @endif
                                </td>
                            @else
                                <td class="whitespace-nowrap">
                                    @if($a->time_in_at)
                                        {{ $a->time_in_at->format('g:i A') }}
                                        @if($a->status === 'late') <x-badge tone="wait">Late</x-badge> @endif
                                    @else
                                        <x-badge tone="bad">No time in</x-badge>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap">
                                    @if($a->time_out_at)
                                        {{ $a->time_out_at->format('g:i A') }}
                                    @elseif($event->fines_processed)
                                        <x-badge tone="bad">No time out</x-badge>
                                    @else
                                        <span class="text-ink-soft">Not yet</span>
                                    @endif
                                </td>
                            @endif
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ 1 + 2 * $sessions->count() }}" class="empty">No attendance yet. Time in opens 1 hour before each session starts.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
