@extends('layouts.app')

@section('content')
@php $clearedCount = $students->whereNull('unpaid_total')->count(); @endphp

<x-page-header title="Clearance"
    :description="$clearedCount . ' of ' . $students->count() . ' students cleared. Students are cleared automatically once all their fines are paid.'">
    <a href="{{ route('fines.index') }}" class="btn btn-secondary">Record payments</a>
</x-page-header>

<div x-data="{ show: '' }">
    <div class="mb-4 flex flex-wrap gap-2" role="group" aria-label="Show">
        @foreach(['' => 'Everyone', 'owes' => 'Not cleared', 'cleared' => 'Cleared'] as $value => $label)
            <button type="button" @click="show = @js($value)" :aria-pressed="show === @js($value)"
                class="btn btn-secondary btn-sm" :class="show === @js($value) && 'border-seal bg-seal-wash text-seal-deep'">{{ $label }}</button>
        @endforeach
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
                    <tr x-show="show === '' || show === @js($owes ? 'owes' : 'cleared')">
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
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endpush
