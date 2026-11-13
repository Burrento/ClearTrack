@extends('layouts.app')

@section('content')
<x-page-header title="Fines"
    :description="$owingCount . ' ' . \Illuminate\Support\Str::plural('student', $owingCount) . ' owe ₱' . number_format($unpaidTotal, 2) . ' in total. Each row is one student\'s fines for one event; record a payment when they pay.'">
    @if($owingCount)
        <a href="{{ route('fines.export', 'csv') }}" class="btn btn-secondary">Export CSV</a>
        <a href="{{ route('fines.export', 'pdf') }}" target="_blank" class="btn btn-secondary">Export PDF</a>
    @endif
</x-page-header>

<form method="GET" action="{{ route('fines.index') }}" class="mb-4 flex flex-wrap items-center gap-3">
    <label class="relative w-full sm:w-64">
        <span class="sr-only">Search by name or ID</span>
        <svg class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-soft" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
        </svg>
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search name or ID" class="input pl-9">
    </label>
    <label class="w-full sm:w-56">
        <span class="sr-only">Event</span>
        <select name="event" class="input">
            <option value="">All events</option>
            @foreach($eventOptions as $option)
                <option value="{{ $option->id }}" @selected((string) ($filters['event'] ?? '') === (string) $option->id)>{{ $option->name }}, {{ $option->start_time->format('M j') }}</option>
            @endforeach
        </select>
    </label>
    <label class="w-full sm:w-40">
        <span class="sr-only">Year level</span>
        <select name="year" class="input">
            <option value="">All year levels</option>
            @foreach(\App\Models\User::YEAR_LEVELS as $level)
                <option value="{{ $level }}" @selected(($filters['year'] ?? '') === $level)>{{ $level }}</option>
            @endforeach
        </select>
    </label>
    <label class="w-full sm:w-36">
        <span class="sr-only">Status</span>
        <select name="status" class="input">
            <option value="">Paid and unpaid</option>
            <option value="unpaid" @selected(($filters['status'] ?? '') === 'unpaid')>Unpaid</option>
            <option value="paid" @selected(($filters['status'] ?? '') === 'paid')>Paid</option>
        </select>
    </label>
    <button type="submit" class="btn btn-secondary">Filter</button>
    @if(array_filter($filters))
        <a href="{{ route('fines.index') }}" class="action">Clear</a>
    @endif
    <p class="text-sm text-ink-soft sm:ml-auto">{{ number_format($groups->total()) }} {{ \Illuminate\Support\Str::plural('row', $groups->total()) }}</p>
</form>

<div class="panel overflow-x-auto">
    <table class="data-table">
        <thead>
            <tr>
                <th>Student</th>
                <th>For</th>
                <th class="text-right">Amount</th>
                <th>Status</th>
                <th><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse($groups as $group)
                @php
                    $student = $users->get($group->user_id);
                    $event = $events->get($group->event_id);
                    $rowFines = $fines->get($group->user_id . '|' . $group->event_id, collect());
                @endphp
                <tr>
                    <td>
                        <span class="block font-semibold">{{ $student?->name }}</span>
                        <span class="code text-sm text-ink-soft">{{ $student?->username }}</span>
                    </td>
                    <td>
                        <span class="block">{{ $event?->name ?? 'Clearance' }}@if($event), {{ $event->start_time->format('M j') }}@endif</span>
                        <span class="block text-sm text-ink-soft">
                            {{ $rowFines->map(fn ($f) => ($f->reason ?? 'Fine') . ' ₱' . number_format($f->amount, 2) . ($f->status === 'paid' ? ' (paid)' : ''))->implode(', ') }}
                        </span>
                    </td>
                    <td class="text-right font-semibold whitespace-nowrap">
                        ₱{{ number_format($group->total, 2) }}
                        @if($group->unpaid > 0 && $group->unpaid < $group->total)
                            <span class="block text-sm font-normal text-ink-soft">₱{{ number_format($group->unpaid, 2) }} unpaid</span>
                        @endif
                    </td>
                    <td>
                        @if($group->unpaid > 0)
                            <x-badge tone="bad">Unpaid</x-badge>
                        @else
                            <x-badge tone="good">Paid</x-badge>
                        @endif
                    </td>
                    <td class="text-right">
                        @if($group->unpaid > 0)
                            <form action="{{ route('fines.payGroup') }}" method="POST"
                                onsubmit="return confirm(@js('Record ₱' . number_format($group->unpaid, 2) . ' as paid by ' . $student?->name . ' for ' . ($event?->name ?? 'clearance') . '?'))">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $group->user_id }}">
                                <input type="hidden" name="event_id" value="{{ $group->event_id }}">
                                <button type="submit" class="btn btn-secondary btn-sm whitespace-nowrap">Record payment</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">{{ array_filter($filters) ? 'No fines match. Clear the search or choose another filter.' : 'No fines yet. Fines are added when an event\'s absences are processed.' }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($groups->hasPages())
    <nav class="mt-4 flex items-center justify-between gap-3" aria-label="Fine pages">
        @if($groups->onFirstPage())
            <span class="btn btn-secondary btn-sm opacity-50">Previous</span>
        @else
            <a href="{{ $groups->previousPageUrl() }}" class="btn btn-secondary btn-sm">Previous</a>
        @endif
        <span class="text-sm text-ink-soft">Page {{ $groups->currentPage() }} of {{ $groups->lastPage() }}</span>
        @if($groups->hasMorePages())
            <a href="{{ $groups->nextPageUrl() }}" class="btn btn-secondary btn-sm">Next</a>
        @else
            <span class="btn btn-secondary btn-sm opacity-50">Next</span>
        @endif
    </nav>
@endif
@endsection
