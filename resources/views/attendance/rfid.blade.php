@extends('layouts.app')

@section('content')
@php
    $sessions = $event->sessions;
    $recent = $event->attendances()->where(fn ($q) => $q->whereNotNull('time_in_at')->orWhereNotNull('time_out_at'))
        ->with('user', 'session')->latest('updated_at')->take(8)->get();
    $scan = session('scanned');

    // Result screen wording and colour, shared with the JavaScript version in resources/js/app.js
    $resultLook = fn ($s) => match (true) {
        $s['repeat'] => ['bg-ink-soft', 'Already recorded'],
        $s['action'] === 'out' => ['bg-seal-deep', 'Time out'],
        $s['status'] === 'late' => ['bg-wait', 'Late time in'],
        default => ['bg-seal', 'Time in'],
    };
@endphp

<x-page-header :title="$event->name" :back="route('events.show', $event)" back-label="Event details">
    {{-- Browsers only allow sound after a click on the page, so the beep needs turning on once --}}
    <button type="button" data-sound-toggle aria-pressed="false" class="btn btn-secondary">Turn on beep</button>
</x-page-header>

{{-- Taps are recorded by resources/js/app.js without reloading; this form is the no-JavaScript fallback --}}
<form action="{{ route('attendance.logRfid', $event) }}" method="POST" id="rfid-form">
    @csrf
    <input type="hidden" name="rfid_uid" data-nfc data-nfc-submit>
</form>

{{-- Each session: what a tap records right now, and live counts --}}
<ul class="mb-6 grid gap-3 sm:grid-cols-3">
    @foreach($sessions as $session)
        @php
            $c = \App\Http\Controllers\AttendanceController::sessionCounts($session);
            $open = $session->isInOpen() || $session->isOutOpen();
        @endphp
        <li @class(['panel px-4 py-3', 'border-seal ring-2 ring-seal/20' => $open])>
            <p class="font-bold">{{ $session->label() }} <span class="font-normal text-ink-soft">{{ $session->times() }}, {{ $session->taps() }}</span></p>
            <p @class(['text-sm', 'font-semibold text-seal-deep' => $open, 'text-ink-soft' => !$open])>
                @if($session->isOutOpen())
                    Time out open until {{ $session->outClosesAt()->format('g:i A') }}
                @elseif($session->isInOpen())
                    Time in open{{ $session->starts_at->isFuture() ? ', late after ' . $session->starts_at->format('g:i A') : ', taps now are late' }}
                @elseif($session->state() === 'upcoming')
                    {{ $session->has_in ? 'Time in' : 'Time out' }} opens {{ $session->opensAt()->format('g:i A') }}
                @else
                    Closed
                @endif
            </p>
            <p class="mt-1">
                <strong data-count="{{ $session->name }}-in">{{ $c['in'] }}</strong> in
                (<span data-count="{{ $session->name }}-late">{{ $c['late'] }}</span> late),
                <strong data-count="{{ $session->name }}-out">{{ $c['out'] }}</strong> out
            </p>
            @if($session->canEndNow() && !$event->fines_processed)
                {{-- A normal form post: closing goes back to the event page --}}
                <form action="{{ route('events.sessions.end', [$event, $session]) }}" method="POST" class="mt-2"
                    onsubmit="return confirm(@js('End ' . strtolower($session->label()) . ' now? It closes at once and no more taps are recorded for it. Students who have not tapped out will get the No time out fine.'))">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">End {{ strtolower($session->label()) }} now</button>
                </form>
            @endif
        </li>
    @endforeach
</ul>

{{-- Scan result: sized to be read from across the table --}}
<div data-scan-area aria-live="assertive">
    @if($scan)
        @php [$colour, $label] = $resultLook($scan); @endphp
        <div data-scan-result class="rounded-2xl px-6 py-10 text-white sm:px-10 sm:py-14 {{ $colour }}">
            <p class="text-lg font-semibold text-white/80">{{ $label }}</p>
            <p class="mt-1 text-[clamp(2.25rem,6vw,4rem)] leading-[1.05] font-bold tracking-tight">{{ $scan['name'] }}</p>
            <p class="mt-3 text-white/80">{{ $scan['session'] }}, {{ $scan['at'] }}</p>
        </div>
    @endif
    @error('rfid_uid')
        <div data-scan-result class="rounded-2xl bg-alarm px-6 py-10 text-white sm:px-10 sm:py-14">
            <p class="text-lg font-semibold text-white/80">Not recorded</p>
            <p class="mt-1 text-[clamp(1.75rem,4.5vw,3rem)] leading-[1.1] font-bold tracking-tight">{{ $message }}</p>
        </div>
    @enderror

    <div data-scan-idle @class(['flex flex-col items-center rounded-2xl border-2 border-dashed border-rule bg-surface px-6 py-14 text-center', 'hidden' => session('scanned') || $errors->has('rfid_uid')])>
        <span class="relative flex size-16 items-center justify-center">
            <span class="absolute inset-0 rounded-full bg-seal/20 motion-safe:animate-ping"></span>
            <svg class="relative size-9 text-seal" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 0 1 7.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 0 1 1.06 0Z" />
            </svg>
        </span>
        <p class="mt-5 text-2xl font-bold">Ready. Students can tap one after another.</p>
        <p class="mt-1 text-ink-soft">The same tap records time in or time out, depending on which window is open.</p>
        <p id="nfc-status" class="reader-status mt-2">Connecting to the card reader…</p>
    </div>
</div>

<section class="mt-10" aria-labelledby="recent-heading">
    <h2 id="recent-heading" class="panel-title mb-4">Recently recorded</h2>
    <p data-recent-empty @class(['text-ink-soft', 'hidden' => $recent->isNotEmpty()])>No one yet.</p>
    <ul data-recent @class(['panel divide-y divide-rule', 'hidden' => $recent->isEmpty()])>
        @foreach($recent as $attendance)
            @php $out = (bool) $attendance->time_out_at; @endphp
            <li class="flex items-center justify-between gap-4 px-5 py-3">
                <span class="font-semibold">{{ $attendance->user->name }}</span>
                <span class="flex items-center gap-3 text-sm text-ink-soft">
                    <span class="badge badge-muted">{{ $attendance->session?->label() }} {{ $out ? 'out' : 'in' }}</span>
                    @if(!$out && $attendance->status === 'late') <x-badge tone="wait">Late</x-badge> @endif
                    {{ ($out ? $attendance->time_out_at : $attendance->time_in_at)->format('g:i A') }}
                </span>
            </li>
        @endforeach
    </ul>
</section>
@endsection

@push('scripts')
<script>
    // Fallback page loads (no JavaScript scanning) show a result; return to ready after a few seconds
    setTimeout(() => {
        document.querySelectorAll('[data-scan-result]').forEach(el => el.remove());
        document.querySelector('[data-scan-idle]')?.classList.remove('hidden');
    }, 4000);
</script>
@endpush
