@extends('layouts.app')

@section('content')
@php
    $recent = $event->attendances()->where('status', 'present')->with('user')->latest('updated_at')->take(8)->get();
    $presentCount = $event->attendances()->where('status', 'present')->count();
@endphp

<x-page-header :title="$event->name" :back="route('events.show', $event)" back-label="Event details"
    :description="'Until ' . $event->endsAt() . '. ' . $presentCount . ' present.'" />

<form action="{{ route('attendance.logRfid', $event) }}" method="POST" id="rfid-form">
    @csrf
    <input type="hidden" name="rfid_uid" data-nfc data-nfc-submit>
</form>

{{-- Scan result: sized to be read from across the table --}}
<div aria-live="assertive">
    @if(session('scanned'))
        <div data-scan-result class="rounded-2xl bg-seal px-6 py-10 text-white sm:px-10 sm:py-14">
            <p class="text-lg font-semibold text-white/80">Present</p>
            <p class="mt-1 text-[clamp(2.25rem,6vw,4rem)] leading-[1.05] font-bold tracking-tight">{{ session('scanned') }}</p>
            <p class="mt-3 text-white/80">Recorded at {{ now()->format('g:i:s A') }}</p>
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
                    <span class="text-sm text-ink-soft">{{ $attendance->updated_at->format('g:i A') }}</span>
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
