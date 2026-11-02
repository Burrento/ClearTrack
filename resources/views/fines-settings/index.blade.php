@extends('layouts.app')

@section('content')
@php $hasTiers = $clearance && $clearance->fine_tiers; @endphp

<div class="mx-auto max-w-4xl">
    <x-page-header title="Fine tiers" description="Each student's penalty points for the semester are matched to a tier to set their fine." />

    @if($errors->any())
        <div role="alert" class="mb-6 rounded-lg border border-alarm/30 bg-alarm-wash px-4 py-3 font-semibold text-alarm">
            Check the tiers: {{ $errors->first() }}
        </div>
    @endif

    <form action="{{ route('fines-settings.storeTiers') }}" method="POST" class="panel">
        @csrf
        <div class="panel-body">
            <h2 class="panel-title">Tiers</h2>
            <p class="mt-1 text-ink-soft">Leave "Up to" empty on the last tier to cover everything above it.</p>

            <ul id="tiersContainer" class="mt-6 space-y-3">
                @foreach($clearance?->fine_tiers ?? [] as $index => $tier)
                    @include('fines-settings._tier', ['index' => $index, 'tier' => $tier])
                @endforeach
            </ul>

            <button type="button" onclick="addTier()" class="btn btn-secondary mt-4">Add tier</button>
        </div>
        <div class="flex justify-end border-t border-rule px-5 py-4 sm:px-6">
            <button type="submit" class="btn btn-primary">Save tiers</button>
        </div>
    </form>

    {{-- Opening clearance turns tiers into fines --}}
    <section class="panel panel-body mt-8" aria-labelledby="process-heading">
        <h2 id="process-heading" class="panel-title">Clearance</h2>
        @if($clearance && $clearance->isActive())
            <p class="mt-2"><x-badge tone="good">Open</x-badge> <span class="ml-2 text-ink-soft">Fines were created {{ $clearance->activated_at->format('M j, Y, g:i A') }}. Students can see their status.</span></p>
            <p class="mt-4 max-w-prose text-ink-soft">Points changed since then? Reprocessing recalculates unpaid fines. Students who already paid aren't charged again.</p>
            <div class="mt-5 flex flex-wrap gap-3">
                <form action="{{ route('fines-settings.process') }}" method="POST" onsubmit="return confirm('Recalculate unpaid fines from current penalty points?')">
                    @csrf
                    <button type="submit" class="btn btn-secondary">Reprocess fines</button>
                </form>
                <form action="{{ route('fines-settings.reset') }}" method="POST" onsubmit="return confirm('Close clearance and remove all unpaid clearance fines for this semester? Paid fines are kept.')">
                    @csrf
                    <button type="submit" class="btn btn-secondary text-alarm">Close clearance</button>
                </form>
            </div>
        @else
            <p class="mt-2"><x-badge tone="muted">Not open</x-badge></p>
            <p class="mt-4 max-w-prose text-ink-soft">When the semester's events are done, process fines. Each student gets a fine from their tier, students with no points are cleared, and everyone can see their status.</p>
            @if($hasTiers)
                <form action="{{ route('fines-settings.process') }}" method="POST" class="mt-5"
                    onsubmit="return confirm('Create fines for every student from their penalty points and open clearance?')">
                    @csrf
                    <button type="submit" class="btn btn-primary">Process fines and open clearance</button>
                </form>
            @else
                <p class="mt-4 font-semibold">Save at least one tier first.</p>
            @endif
        @endif
    </section>
</div>

<template id="tierTemplate">
    @include('fines-settings._tier', ['index' => '__INDEX__', 'tier' => []])
</template>
@endsection

@push('scripts')
<script>
    let tierIndex = {{ count($clearance?->fine_tiers ?? []) }};

    function addTier() {
        const html = document.getElementById('tierTemplate').innerHTML.replaceAll('__INDEX__', tierIndex++);
        document.getElementById('tiersContainer').insertAdjacentHTML('beforeend', html);
    }

    function removeTier(button) {
        button.closest('.tier-row').remove();
    }

    if (!document.querySelector('#tiersContainer .tier-row')) addTier();
</script>
@endpush
