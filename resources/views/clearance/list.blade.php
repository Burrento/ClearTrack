@extends('layouts.app')

@section('content')
@php
    $clearedCount = $students->whereNull('unpaid_total')->count();
    $yearLevels = ['First Year', 'Second Year', 'Third Year', 'Fourth Year', 'Fifth Year'];
    // What the search and filters match against, one entry per row
    $rows = $students->map(fn ($s) => [
        'text' => strtolower($s->name . ' ' . $s->username),
        'year' => $s->year_level,
        'state' => $s->unpaid_total !== null ? 'owes' : 'cleared',
    ])->values();
@endphp

<x-page-header title="Clearance"
    :description="$clearedCount . ' of ' . $students->count() . ' students cleared. Students are cleared automatically once all their fines are paid.'">
    <a href="{{ route('fines.index') }}" class="btn btn-secondary">Record payments</a>
</x-page-header>

<div x-data="{
        search: '', year: '', show: '', rows: @js($rows),
        matches(row) {
            return (this.search === '' || row.text.includes(this.search.toLowerCase()))
                && (this.year === '' || row.year === this.year)
                && (this.show === '' || row.state === this.show);
        },
        get shown() { return this.rows.filter((row) => this.matches(row)).length },
    }">
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <label class="relative w-full sm:w-72">
            <span class="sr-only">Search by name or ID</span>
            <svg class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-soft" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
            <input type="search" x-model="search" placeholder="Search name or ID" class="input pl-9">
        </label>
        <label class="w-full sm:w-48">
            <span class="sr-only">Year level</span>
            <select x-model="year" class="input">
                <option value="">All year levels</option>
                @foreach($yearLevels as $level)
                    <option value="{{ $level }}">{{ $level }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex flex-wrap gap-2" role="group" aria-label="Clearance">
            @foreach(['' => 'Everyone', 'owes' => 'Not cleared', 'cleared' => 'Cleared'] as $value => $label)
                <button type="button" @click="show = @js($value)" :aria-pressed="show === @js($value)"
                    class="btn btn-secondary btn-sm" :class="show === @js($value) && 'border-seal bg-seal-wash text-seal-deep'">{{ $label }}</button>
            @endforeach
        </div>
        <p class="text-sm text-ink-soft sm:ml-auto" aria-live="polite">Showing <span x-text="shown">{{ $students->count() }}</span> of {{ $students->count() }}</p>
    </div>

    <div class="panel overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Year level</th>
                    <th class="text-right">Unpaid fines</th>
                    <th>Clearance</th>
                </tr>
            </thead>
            <tbody>
                @forelse($students as $student)
                    @php $owes = $student->unpaid_total !== null; @endphp
                    <tr x-show="matches(rows[{{ $loop->index }}])">
                        <td>
                            <span class="block font-semibold">{{ $student->name }}</span>
                            <span class="text-sm text-ink-soft">{{ $student->username }}</span>
                        </td>
                        <td class="whitespace-nowrap">{{ $student->year_level ?? '—' }}</td>
                        <td class="text-right font-semibold whitespace-nowrap">
                            {{ $owes ? '₱' . number_format($student->unpaid_total, 2) : '—' }}
                        </td>
                        <td>
                            @if($owes)
                                <x-badge tone="bad">Not cleared</x-badge>
                            @else
                                <x-badge tone="good">Cleared</x-badge>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">No active students.</td></tr>
                @endforelse
                @if($students->isNotEmpty())
                    {{-- Shown when the search and filters hide every row --}}
                    <tr x-show="shown === 0" style="display: none">
                        <td colspan="4" class="empty">No students match. Clear the search or choose another filter.</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endpush
