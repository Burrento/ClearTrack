@extends('layouts.app')

@section('content')
@php
    $recorded = $event->attendances()->whereIn('status', ['present', 'late']);
    $recent = (clone $recorded)->with('user')->latest('updated_at')->take(8)->get();
    $counts = (clone $recorded)->get()->countBy('status');
    $scan = session('scanned');
    $late = ($scan['status'] ?? null) === 'late';
    $startsLater = $event->start_time->isFuture();
@endphp

<x-page-header :title="$event->name" :back="route('events.show', $event)" back-label="Event details"
    :description="($startsLater ? 'Starts ' . $event->start_time->format('g:i A') . '. Taps after that count as late. ' : 'Started ' . $event->start_time->format('g:i A') . ', taps now count as late. ')
        . ($counts['present'] ?? 0) . ' present, ' . ($counts['late'] ?? 0) . ' late.'" />

<form action="{{ route('attendance.logRfid', $event) }}" method="POST" id="rfid-form">
    @csrf
    <input type="hidden" name="rfid_uid" data-nfc data-nfc-submit>
</form>

{{-- Scan result: sized to be read from across the table --}}
<div aria-live="assertive">
    @if($scan)
        <div data-scan-result @class(['rounded-2xl px-6 py-10 text-white sm:px-10 sm:py-14', 'bg-wait' => $late, 'bg-seal' => !$late])>
            <p class="text-lg font-semibold text-white/80">{{ $scan['repeat'] ? 'Already recorded' : ($late ? 'Late' : 'Present') }}</p>
            <p class="mt-1 text-[clamp(2.25rem,6vw,4rem)] leading-[1.05] font-bold tracking-tight">{{ $scan['name'] }}</p>
            <p class="mt-3 text-white/80">{{ $scan['repeat'] ? ucfirst($scan['status']) . ', tapped at' : 'Recorded at' }} {{ $scan['at'] }}</p>
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
        <p class="mt-5 text-2xl font-bold">Ready. Tap a student ID on the reader.</p>
        <p id="nfc-status" class="reader-status mt-2">Connecting to the card reader…</p>
    </div>
</div>

<section class="mt-10" aria-labelledby="recent-heading">
    <h2 id="recent-heading" class="panel-title mb-4">Recently recorded</h2>
    @if($recent->isEmpty())
        <p class="text-ink-soft">No one yet.</p>
    @else
        <ul class="panel divide-y divide-rule">
            @foreach($recent as $attendance)
                <li class="flex items-center justify-between gap-4 px-5 py-3">
                    <span class="font-semibold">{{ $attendance->user->name }}</span>
                    <span class="flex items-center gap-3 text-sm text-ink-soft">
                        @if($attendance->status === 'late') <x-badge tone="wait">Late</x-badge> @endif
                        {{ $attendance->updated_at->format('g:i A') }}
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endsection

@push('scripts')
<script>
    // Return to the ready state after a few seconds so an old result isn't mistaken for the next student's
    setTimeout(() => {
        document.querySelectorAll('[data-scan-result]').forEach(el => el.remove());
        document.querySelector('[data-scan-idle]')?.classList.remove('hidden');
    }, 4000);
</script>
@endpush
