@extends('layouts.app')

@section('content')
<x-page-header title="Logs" description="Everything people have done in ClearTrack: logins, changes, payments and more. Newest first." />

<form method="GET" action="{{ route('admin.logs') }}" class="mb-4 flex flex-wrap items-center gap-3">
    <label class="relative w-full sm:w-72">
        <span class="sr-only">Search</span>
        <svg class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-soft" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
        </svg>
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search person, action, student, event" class="input pl-9">
    </label>
    <label class="w-full sm:w-40">
        <span class="sr-only">Role</span>
        <select name="role" class="input">
            <option value="">Everyone</option>
            @foreach(['admin' => 'Admins', 'officer' => 'Officers', 'student' => 'Students'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="w-full sm:w-44">
        <span class="sr-only">Day</span>
        <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="input">
    </label>
    <button type="submit" class="btn btn-secondary">Filter</button>
    @if(array_filter($filters))
        <a href="{{ route('admin.logs') }}" class="action">Clear</a>
    @endif
    <p class="text-sm text-ink-soft sm:ml-auto">{{ number_format($logs->total()) }} {{ \Illuminate\Support\Str::plural('entry', $logs->total()) }}</p>
</form>

<div class="panel overflow-x-auto">
    <table class="data-table">
        <thead>
            <tr>
                <th>When</th>
                <th>Who</th>
                <th>What</th>
                <th>IP address</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                <tr>
                    <td class="align-top whitespace-nowrap text-sm">
                        <span class="block">{{ $log->created_at->format('M j, Y') }}</span>
                        <span class="text-ink-soft">{{ $log->created_at->format('g:i:s A') }}</span>
                    </td>
                    <td class="align-top">
                        @if($log->user)
                            <span class="block font-semibold">{{ $log->user->name }}</span>
                            <span class="text-sm text-ink-soft">{{ ucfirst($log->user->getRoleNames()->first() ?? 'user') }}@if($log->user->department), {{ $log->user->department->code }}@endif</span>
                        @else
                            <span class="text-ink-soft">Not logged in</span>
                        @endif
                    </td>
                    <td class="align-top">
                        <span @class(['block font-semibold', 'text-alarm' => $log->action === 'Failed login'])>{{ $log->action }}</span>
                        @if($log->subject) <span class="block">{{ $log->subject }}</span> @endif
                        @if($log->details) <span class="block text-sm text-ink-soft">{{ $log->details }}</span> @endif
                    </td>
                    <td class="code align-top text-sm whitespace-nowrap text-ink-soft">{{ $log->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">{{ array_filter($filters) ? 'Nothing matches these filters.' : 'Nothing has been logged yet.' }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($logs->hasPages())
    <nav class="mt-4 flex items-center justify-between gap-3" aria-label="Log pages">
        @if($logs->onFirstPage())
            <span class="btn btn-secondary btn-sm opacity-50">Newer</span>
        @else
            <a href="{{ $logs->previousPageUrl() }}" class="btn btn-secondary btn-sm">Newer</a>
        @endif
        <span class="text-sm text-ink-soft">Page {{ $logs->currentPage() }} of {{ $logs->lastPage() }}</span>
        @if($logs->hasMorePages())
            <a href="{{ $logs->nextPageUrl() }}" class="btn btn-secondary btn-sm">Older</a>
        @else
            <span class="btn btn-secondary btn-sm opacity-50">Older</span>
        @endif
    </nav>
@endif
@endsection
