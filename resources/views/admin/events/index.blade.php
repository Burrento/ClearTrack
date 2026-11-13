@extends('layouts.app')

@section('content')
<x-page-header title="Events" description="Every department's events. A processed event can't be changed by officers; fix it here and its fines are charged again." />

<form method="GET" action="{{ route('admin.events') }}" class="mb-4 flex flex-wrap items-center gap-3">
    <label class="w-full sm:w-64">
        <span class="sr-only">Search by event name</span>
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search event name" class="input">
    </label>
    <label class="w-full sm:w-44">
        <span class="sr-only">Department</span>
        <select name="department" class="input">
            <option value="">All departments</option>
            @foreach($departments as $department)
                <option value="{{ $department->id }}" @selected((string) ($filters['department'] ?? '') === (string) $department->id)>{{ $department->code }}</option>
            @endforeach
        </select>
    </label>
    <button type="submit" class="btn btn-secondary">Filter</button>
    @if(array_filter($filters))
        <a href="{{ route('admin.events') }}" class="action">Clear</a>
    @endif
</form>

<div class="panel overflow-x-auto">
    <table class="data-table">
        <thead>
            <tr>
                <th>Event</th>
                <th>Department</th>
                <th>Date and sessions</th>
                <th>Status</th>
                <th><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse($events as $event)
                <tr>
                    <td class="font-semibold">{{ $event->name }}</td>
                    <td class="whitespace-nowrap">{{ $event->department?->code ?? '—' }}</td>
                    <td>
                        <span class="block whitespace-nowrap">{{ $event->start_time->format('D, M j, Y') }}</span>
                        <span class="text-sm text-ink-soft">{{ $event->sessions->map(fn ($s) => $s->label() . ' (' . $s->taps() . ')')->implode(', ') }}</span>
                    </td>
                    <td><x-event-phase :event="$event" /></td>
                    <td class="text-right">
                        @if($event->fines_processed)
                            <a href="{{ route('admin.events.edit', $event) }}" class="action">Fix</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">{{ array_filter($filters) ? 'No events match these filters.' : 'No events yet.' }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($events->hasPages())
    <nav class="mt-4 flex items-center justify-between gap-3" aria-label="Event pages">
        @if($events->onFirstPage())
            <span class="btn btn-secondary btn-sm opacity-50">Previous</span>
        @else
            <a href="{{ $events->previousPageUrl() }}" class="btn btn-secondary btn-sm">Previous</a>
        @endif
        <span class="text-sm text-ink-soft">Page {{ $events->currentPage() }} of {{ $events->lastPage() }}</span>
        @if($events->hasMorePages())
            <a href="{{ $events->nextPageUrl() }}" class="btn btn-secondary btn-sm">Next</a>
        @else
            <span class="btn btn-secondary btn-sm opacity-50">Next</span>
        @endif
    </nav>
@endif
@endsection
