@extends('layouts.app')

@section('content')
@php
    $sessions = $event->sessions;
    // One row per student, with that student's attendance per session
    $byStudent = $event->attendances()->with('user')->get()->groupBy('user_id')
        ->map(fn ($rows) => ['user' => $rows->first()->user, 'sessions' => $rows->keyBy('event_session_id')])
        ->sortBy(fn ($r) => $r['user']->name)->values();
    $money = fn ($amount) => '₱' . number_format($amount, 2);

    // What the attendance search and filter match against, one entry per student row
    $filterRows = $byStudent->map(function ($r) use ($sessions, $event) {
        $rows = $sessions->map(fn ($s) => $r['sessions']->get($s->id));
        return [
            'text' => strtolower($r['user']->name . ' ' . $r['user']->username),
            'year' => $r['user']->year_level,
            'late' => $rows->contains(fn ($a) => $a?->status === 'late'),
            'absent' => $rows->contains(fn ($a) => !$a || $a->status === 'absent'),
            'no_in' => $rows->contains(fn ($a) => $a?->status === 'missed_in'),
            'no_out' => $sessions->contains(fn ($s) => $s->has_out && ($a = $r['sessions']->get($s->id)) && $a->time_in_at && !$a->time_out_at),
        ];
    });
@endphp

<x-page-header :title="$event->name" :back="route('events.index')" back-label="Events">
    @unless($event->fines_processed)
        <a href="{{ route('events.edit', $event) }}" class="btn btn-secondary">Edit event</a>
    @endunless
</x-page-header>

<div class="-mt-4 mb-8 space-y-1 text-ink-soft">
    <p class="flex flex-wrap items-center gap-x-4 gap-y-2"><x-event-phase :event="$event" /> {{ $event->schedule() }}</p>
    <p>For: <strong class="text-ink">{{ $event->audience() }}</strong></p>
    <p>Fines per session: {{ $money($event->missed_in_fine) }} no time in, {{ $money($event->missed_out_fine) }} no time out,
        {{ $money($event->late_fine) }} late. Absent (no taps) is both no time in and no time out.</p>
</div>

{{-- Actions, in the order an officer uses them --}}
@php
    $open = !$event->fines_processed;
    // Something is taking taps, or the day has begun and later sessions are still to come
    $endable = $open && ($event->sessions->contains->canEndNow()
        || ($event->sessions->contains(fn ($s) => !$s->canStartNow()) && $event->sessions->contains->canStartNow()));
@endphp
<section class="panel panel-body mb-6" aria-label="Event actions">
    <div class="flex flex-wrap gap-3">
        @if($event->isOpenForAttendance())
            <a href="{{ route('attendance.rfid', $event) }}" class="btn btn-primary btn-lg">Open RFID scanner</a>
        @endif

        @if($event->survey)
            <span class="btn btn-secondary btn-lg cursor-default">Survey attached</span>
        @else
            <a href="{{ route('surveys.create', $event) }}" class="btn btn-secondary btn-lg">Attach survey</a>
        @endif

        @if($open)
            <div class="flex flex-wrap gap-3 sm:ml-auto">
                @if($endable)
                    <form action="{{ route('events.end', $event) }}" method="POST"
                        onsubmit="return confirm('End the whole event now? Every session closes at once and no more taps are recorded. Sessions not started yet are cancelled. You can then process absences straight away.')">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-lg">End event now</button>
                    </form>
                @endif
                <form action="{{ route('events.processFines', $event) }}" method="POST"
                    onsubmit="return confirm('Process absences now? Students who missed a session, came late or missed a time in or time out get this event\'s fines.')">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-lg" @disabled($event->closesAt()->isFuture())>Process absences</button>
                </form>
            </div>
        @endif
    </div>
    @if($open)
        <p class="hint mt-4">
            @if($event->closesAt()->isFuture())
                Absences can be processed after the last time out closes at {{ $event->closesAt()->format('g:i A') }}.
            @else
                Process absences to add the fines. This also runs automatically a few minutes after the last time out closes.
            @endif
        </p>
    @endif
</section>

{{-- Each session: its state, and starting or ending it early --}}
<section class="mb-10" aria-labelledby="sessions-heading">
    <h2 id="sessions-heading" class="panel-title mb-4">Sessions</h2>
    <ul class="panel divide-y divide-rule">
        @foreach($event->sessions as $session)
            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <p class="font-semibold">{{ $session->label() }} <span class="font-normal text-ink-soft">{{ $session->times() }}, {{ $session->taps() }}</span></p>
                    <p class="text-sm text-ink-soft">
                        @switch($session->state())
                            @case('upcoming') {{ $session->has_in ? 'Time in' : 'Time out' }} opens {{ $session->opensAt()->format($session->opensAt()->isToday() ? 'g:i A' : 'M j, g:i A') }} @break
                            @case('in') <span class="font-semibold text-seal-deep">Time in open</span>{{ $session->starts_at->isFuture() ? ', late after ' . $session->starts_at->format('g:i A') : ', taps now are late' }} @break
                            @case('out') <span class="font-semibold text-seal-deep">Time out open</span> until {{ $session->outClosesAt()->format('g:i A') }} @break
                            @case('closed') Ended early at {{ $session->closed_at->format('g:i A') }} @break
                            @default Ended
                        @endswitch
                    </p>
                </div>
                @if($open)
                    <div class="flex gap-2">
                        @if($session->canStartNow())
                            <form action="{{ route('events.sessions.start', [$event, $session]) }}" method="POST"
                                onsubmit="return confirm(@js('Start ' . strtolower($session->label()) . ' attendance now? Time in opens immediately instead of at ' . $session->inOpensAt()->format('g:i A') . '. Taps before ' . $session->starts_at->format('g:i A') . ' count as on time.'))">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-sm">Start now</button>
                            </form>
                        @endif
                        @if($session->canEndNow())
                            <form action="{{ route('events.sessions.end', [$event, $session]) }}" method="POST"
                                onsubmit="return confirm(@js('End ' . strtolower($session->label()) . ' now? It closes at once and no more taps are recorded for it. Students who have not tapped out will get the No time out fine.'))">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-sm">End now</button>
                            </form>
                        @endif
                    </div>
                @endif
            </li>
        @endforeach
    </ul>
</section>

<section aria-labelledby="log-heading" x-data="{
        search: '', year: '', show: '', rows: @js($filterRows),
        matches(row) {
            return (this.search === '' || row.text.includes(this.search.toLowerCase()))
                && (this.year === '' || row.year === this.year)
                && (this.show === '' || row[this.show]);
        },
        get shown() { return this.rows.filter((row) => this.matches(row)).length },
    }">
    <h2 id="log-heading" class="panel-title mb-4">Attendance</h2>

    @if($byStudent->isNotEmpty())
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <label class="relative w-full sm:w-72">
                <span class="sr-only">Search by name or ID</span>
                <svg class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-soft" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input type="search" x-model="search" placeholder="Search name or ID" class="input pl-9">
            </label>
            <label class="w-full sm:w-44">
                <span class="sr-only">Year level</span>
                <select x-model="year" class="input">
                    <option value="">All year levels</option>
                    @foreach(\App\Models\User::YEAR_LEVELS as $level)
                        <option value="{{ $level }}">{{ $level }}</option>
                    @endforeach
                </select>
            </label>
            <label class="w-full sm:w-48">
                <span class="sr-only">Show</span>
                <select x-model="show" class="input">
                    <option value="">Everyone</option>
                    <option value="late">Late in any session</option>
                    <option value="absent">Absent from a session</option>
                    <option value="no_in">No time in</option>
                    <option value="no_out">No time out</option>
                </select>
            </label>
            <p class="text-sm text-ink-soft sm:ml-auto" aria-live="polite">Showing <span x-text="shown">{{ $byStudent->count() }}</span> of {{ $byStudent->count() }}</p>
        </div>
    @endif

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
                    <tr x-show="matches(rows[{{ $loop->index }}])">
                        <td class="font-semibold whitespace-nowrap">{{ $row['user']->name }}</td>
                        @foreach($sessions as $session)
                            @php $a = $row['sessions']->get($session->id); @endphp
                            @if(!$a || $a->status === 'absent')
                                <td colspan="2">
                                    @if($a) <x-badge tone="bad">Absent</x-badge> @else <span class="text-ink-soft">—</span> @endif
                                </td>
                            @else
                                <td class="whitespace-nowrap">
                                    @if(!$session->has_in)
                                        <span class="text-ink-soft">Not needed</span>
                                    @elseif($a->time_in_at)
                                        {{ $a->time_in_at->format('g:i A') }}
                                        @if($a->status === 'late') <x-badge tone="wait">Late</x-badge> @endif
                                    @else
                                        <x-badge tone="bad">No time in</x-badge>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap">
                                    @if(!$session->has_out)
                                        <span class="text-ink-soft">Not needed</span>
                                    @elseif($a->time_out_at)
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
                @if($byStudent->isNotEmpty())
                    <tr x-show="shown === 0" style="display: none">
                        <td colspan="{{ 1 + 2 * $sessions->count() }}" class="empty">No students match. Clear the search or choose another filter.</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</section>
@endsection

@push('scripts')
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endpush
