@extends('layouts.app')

@section('content')
<x-page-header title="Students" description="Every student in every department. Officers manage their own department's students; you can edit or delete any of them here." />

<form method="GET" action="{{ route('admin.students') }}" class="mb-4 flex flex-wrap items-center gap-3">
    <label class="relative w-full sm:w-64">
        <span class="sr-only">Search by name or ID</span>
        <svg class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-soft" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
        </svg>
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search name or ID" class="input pl-9">
    </label>
    <label class="w-full sm:w-44">
        <span class="sr-only">Department</span>
        <select name="department" class="input">
            <option value="">All departments</option>
            @foreach($departments as $department)
                <option value="{{ $department->id }}" @selected((string) ($filters['department'] ?? '') === (string) $department->id)>{{ $department->code }}{{ $department->archived_at ? ' (archived)' : '' }}</option>
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
    <label class="w-full sm:w-40">
        <span class="sr-only">Status</span>
        <select name="status" class="input">
            <option value="">Any status</option>
            @foreach(['active' => 'Active', 'deactivated' => 'Deactivated', 'owes' => 'Owes fines', 'cleared' => 'Cleared', 'no_card' => 'No RFID card'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <button type="submit" class="btn btn-secondary">Filter</button>
    @if(array_filter($filters))
        <a href="{{ route('admin.students') }}" class="action">Clear</a>
    @endif
    <p class="text-sm text-ink-soft sm:ml-auto">{{ number_format($students->total()) }} {{ \Illuminate\Support\Str::plural('student', $students->total()) }}</p>
</form>

<div class="panel overflow-x-auto">
    <table class="data-table">
        <thead>
            <tr>
                <th>Student</th>
                <th>Department</th>
                <th>Year level</th>
                <th>RFID card</th>
                <th>Status</th>
                <th><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse($students as $student)
                <tr @class(['text-ink-soft' => !$student->is_active])>
                    <td>
                        <span class="block font-semibold">{{ $student->name }}</span>
                        <span class="code text-sm text-ink-soft">{{ $student->username }}</span>
                    </td>
                    <td class="whitespace-nowrap">{{ $student->department?->code ?? '—' }}</td>
                    <td class="whitespace-nowrap">{{ $student->year_level ?? '—' }}</td>
                    <td class="whitespace-nowrap">
                        @if($student->rfid_uid) <span class="code">{{ $student->formattedRfid() }}</span> @else <span class="text-ink-soft">No card</span> @endif
                    </td>
                    <td class="whitespace-nowrap">
                        @if(!$student->is_active)
                            <x-badge tone="muted">Deactivated</x-badge>
                        @elseif($student->unpaid_total !== null)
                            <x-badge tone="bad">Owes ₱{{ number_format($student->unpaid_total, 2) }}</x-badge>
                        @else
                            <x-badge tone="good">Cleared</x-badge>
                        @endif
                    </td>
                    <td>
                        <div class="flex justify-end gap-4">
                            <a href="{{ route('admin.students.edit', $student) }}" class="action">Edit</a>
                            <form method="POST" action="{{ route('admin.students.destroy', $student) }}"
                                onsubmit="return confirm(@js('Permanently delete ' . $student->name . ' (' . $student->username . ')?' . "\n\n"
                                    . 'This also deletes their ' . $student->attendances_count . ' attendance ' . \Illuminate\Support\Str::plural('record', $student->attendances_count)
                                    . ' and ' . $student->fines_count . ' ' . \Illuminate\Support\Str::plural('fine', $student->fines_count) . ', paid ones too. It cannot be undone.'
                                    . "\n\n" . 'For graduates or dropouts, use Edit and untick Active instead, which keeps their history.'))">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="action-danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">{{ array_filter($filters) ? 'No students match these filters.' : 'No students yet. Officers register them from their Students page.' }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($students->hasPages())
    <nav class="mt-4 flex items-center justify-between gap-3" aria-label="Student pages">
        @if($students->onFirstPage())
            <span class="btn btn-secondary btn-sm opacity-50">Previous</span>
        @else
            <a href="{{ $students->previousPageUrl() }}" class="btn btn-secondary btn-sm">Previous</a>
        @endif
        <span class="text-sm text-ink-soft">Page {{ $students->currentPage() }} of {{ $students->lastPage() }}</span>
        @if($students->hasMorePages())
            <a href="{{ $students->nextPageUrl() }}" class="btn btn-secondary btn-sm">Next</a>
        @else
            <span class="btn btn-secondary btn-sm opacity-50">Next</span>
        @endif
    </nav>
@endif
@endsection
