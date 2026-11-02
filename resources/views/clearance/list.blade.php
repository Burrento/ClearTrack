@extends('layouts.app')

@section('content')
@php $clearedCount = $students->filter->isCleared()->count(); @endphp

<x-page-header title="Clearance"
    :description="$clearanceActive ? $clearedCount . ' of ' . $students->count() . ' students cleared. A student can be cleared once their fines are paid.' : null" />

@unless($clearanceActive)
    <div class="mb-6 rounded-lg border border-wait/30 bg-wait-wash px-4 py-3 text-wait">
        <p class="font-semibold">Clearance isn't open yet.</p>
        <p>Process fines on the <a href="{{ route('fines-settings.index') }}" class="underline">Fine tiers</a> page to open it.</p>
    </div>
@endunless

<div class="panel overflow-x-auto">
    <table class="data-table">
        <thead>
            <tr>
                <th>Student</th>
                <th>Year level</th>
                <th class="text-right">Points</th>
                <th>Fines</th>
                <th>Clearance</th>
                <th><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse($students as $student)
                @php $owes = $student->hasUnpaidFines(); @endphp
                <tr>
                    <td>
                        <span class="block font-semibold">{{ $student->name }}</span>
                        <span class="text-sm text-ink-soft">{{ $student->username }}</span>
                    </td>
                    <td class="whitespace-nowrap">{{ $student->year_level ?? '—' }}</td>
                    <td class="text-right">{{ $student->getTotalPenaltyPoints() }}</td>
                    <td>
                        @if($owes)
                            <x-badge tone="bad">Unpaid</x-badge>
                        @else
                            <x-badge tone="good">Settled</x-badge>
                        @endif
                    </td>
                    <td>
                        @if($student->isCleared())
                            <x-badge tone="good">Cleared</x-badge>
                        @else
                            <x-badge tone="muted">Not cleared</x-badge>
                        @endif
                    </td>
                    <td class="text-right">
                        @if($student->isCleared())
                            <form action="{{ route('clearance.unclear', $student) }}" method="POST" onsubmit="return confirm(@js('Revoke clearance for ' . $student->name . '?'))">
                                @csrf
                                <button type="submit" class="action-danger">Revoke</button>
                            </form>
                        @elseif($owes)
                            <span class="text-sm text-ink-soft">Fines unpaid</span>
                        @else
                            <form action="{{ route('clearance.clear', $student) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm">Mark cleared</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No active students.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
