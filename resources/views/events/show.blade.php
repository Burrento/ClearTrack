@extends('layouts.app')

@section('content')
@php
    $attendances = $event->attendances()->with('user')->latest('updated_at')->get();
    $counts = $attendances->countBy('status');
@endphp

<x-page-header :title="$event->name" :back="route('events.index')" back-label="Events">
    <a href="{{ route('events.edit', $event) }}" class="btn btn-secondary">Edit event</a>
</x-page-header>

<div class="-mt-4 mb-8 flex flex-wrap items-center gap-x-6 gap-y-2 text-ink-soft">
    <x-event-phase :event="$event" />
    <span>{{ $event->schedule() }}</span>
    <span>Fines: ₱{{ number_format($event->absent_fine, 2) }} if absent, ₱{{ number_format($event->late_fine, 2) }} if late</span>
</div>

{{-- Actions, in the order an officer uses them --}}
<section class="panel panel-body mb-10" aria-label="Event actions">
    <div class="flex flex-wrap gap-3">
        @if($event->isOpenForAttendance())
            <a href="{{ route('attendance.rfid', $event) }}" class="btn btn-primary btn-lg">Open RFID scanner</a>
        @endif

        @if($event->survey)
            <span class="btn btn-secondary btn-lg cursor-default">Survey attached</span>
        @else
            <a href="{{ route('surveys.create', $event) }}" class="btn btn-secondary btn-lg">Attach survey</a>
        @endif

        @unless($event->fines_processed)
            <form action="{{ route('events.processFines', $event) }}" method="POST" class="sm:ml-auto"
                onsubmit="return confirm(@js('Mark every student who didn't tap in as absent and add fines: ₱' . number_format($event->absent_fine, 2) . ' per absent student, ₱' . number_format($event->late_fine, 2) . ' per late student?'))">
                @csrf
                <button type="submit" class="btn btn-danger btn-lg" @disabled($event->end_time->isFuture())>Process absences</button>
            </form>
        @endunless
    </div>
    @if(!$event->fines_processed)
        <p class="hint mt-4">
            @if($event->end_time->isFuture())
                Absences can be processed after the event ends at {{ $event->endsAt() }}.
            @else
                Process absences to add fines for absent and late students. This also runs automatically a few minutes after the event ends.
            @endif
        </p>
    @endif
</section>

<section aria-labelledby="log-heading">
    <div class="mb-4 flex flex-wrap items-baseline justify-between gap-3">
        <h2 id="log-heading" class="panel-title">Attendance</h2>
        <p class="text-ink-soft">
            {{ $counts['present'] ?? 0 }} present, {{ $counts['late'] ?? 0 }} late, {{ $counts['absent'] ?? 0 }} absent
        </p>
    </div>
    <div class="panel overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Status</th>
                    <th>Recorded</th>
                    <th>Method</th>
                </tr>
            </thead>
            <tbody>
                @forelse($attendances as $attendance)
                    <tr>
                        <td class="font-semibold">{{ $attendance->user->name }}</td>
                        <td>
                            @switch($attendance->status)
                                @case('present') <x-badge tone="good">Present</x-badge> @break
                                @case('late') <x-badge tone="wait">Late</x-badge> @break
                                @default <x-badge tone="bad">{{ ucfirst($attendance->status) }}</x-badge>
                            @endswitch
                        </td>
                        <td class="whitespace-nowrap text-ink-soft">{{ $attendance->updated_at->format('M j, g:i A') }}</td>
                        <td class="text-ink-soft">
                            {{ $attendance->verified_by_id ? 'RFID' : 'Automatic' }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">No attendance yet. The scanner opens 1 hour before the event starts.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
