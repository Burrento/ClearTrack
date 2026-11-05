@extends('layouts.app')

@section('content')
@php $unpaid = $fines->where('status', 'unpaid'); @endphp

<x-page-header title="Fines"
    :description="$unpaid->count() . ' unpaid, ₱' . number_format($unpaid->sum('amount'), 2) . ' in total. Record a payment when a student pays you.'" />

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
            @forelse($fines as $fine)
                <tr>
                    <td class="font-semibold">{{ $fine->user->name }}</td>
                    <td>
                        <span class="block">{{ $fine->event?->name ?? 'Clearance' }}@if($fine->semester), {{ $fine->semester->name }}@endif</span>
                        @if($fine->reason)
                            <span class="block text-sm text-ink-soft">{{ $fine->reason }}</span>
                        @endif
                    </td>
                    <td class="text-right font-semibold whitespace-nowrap">₱{{ number_format($fine->amount, 2) }}</td>
                    <td>
                        @if($fine->status === 'paid')
                            <x-badge tone="good">Paid</x-badge>
                        @else
                            <x-badge tone="bad">Unpaid</x-badge>
                        @endif
                    </td>
                    <td class="text-right">
                        @if($fine->status === 'unpaid')
                            <form action="{{ route('fines.markAsPaid', $fine) }}" method="POST"
                                onsubmit="return confirm(@js('Record ₱' . number_format($fine->amount, 2) . ' as paid by ' . $fine->user->name . '?'))">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-sm">Record payment</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No fines yet. Fines are added for absent and late students when an event's absences are processed.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
