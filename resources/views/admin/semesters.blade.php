@extends('layouts.app')

@section('content')
<x-page-header title="Semesters" description="Starting a semester applies to every department. Past fines and attendance stay on record." />

<div class="grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
    <div class="panel overflow-x-auto self-start">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Semester</th>
                    <th>Started</th>
                    <th><span class="sr-only">Status</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($semesters as $semester)
                    <tr>
                        <td class="font-semibold">{{ $semester->name }}</td>
                        <td>{{ $semester->starts_at->format('M j, Y') }}</td>
                        <td class="text-right">
                            @if($loop->first)
                                <x-badge tone="good">Current</x-badge>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">No semesters yet. Until one is started, penalty points count every event.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <form action="{{ route('admin.semesters') }}" method="POST" class="panel panel-body space-y-5 self-start"
        onsubmit="return confirm('Start a new semester for every department? All students will be set to not cleared.')">
        @csrf
        <div>
            <h2 class="panel-title">Start new semester</h2>
            <p class="mt-1 text-ink-soft">Penalty points start from zero, fine tiers carry over, and every student is set to not cleared.</p>
        </div>
        <div>
            <label for="name" class="label">Name</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required placeholder="2nd Sem 2026–2027"
                @class(['input', 'input-invalid' => $errors->has('name')])>
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="starts_at" class="label">Start date</label>
            <input type="date" name="starts_at" id="starts_at" value="{{ old('starts_at', now()->toDateString()) }}" required
                @class(['input', 'input-invalid' => $errors->has('starts_at')])>
            @error('starts_at') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="deactivate_year_level" class="label">Graduating students</label>
            <select name="deactivate_year_level" id="deactivate_year_level" class="input">
                <option value="">Keep everyone active</option>
                @foreach($yearLevels as $level)
                    <option value="{{ $level }}" @selected(old('deactivate_year_level') === $level)>Deactivate all {{ $level }} students</option>
                @endforeach
            </select>
            <p class="hint">Deactivated students can't sign in. Officers can reactivate them from Students.</p>
            @error('deactivate_year_level') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn btn-primary w-full">Start semester</button>
    </form>
</div>
@endsection
