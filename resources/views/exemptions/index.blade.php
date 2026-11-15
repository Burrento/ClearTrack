@extends('layouts.app')

@section('content')
<x-page-header title="Exempted" description="Students excused from an event's fines, such as performers or students with an excuse letter. Exempted students aren't fined for that event and their fines for it don't block clearance." />

<form method="POST" action="{{ route('exemptions.store') }}" class="panel mb-8">
    @csrf
    <div class="panel-body grid gap-5 sm:grid-cols-2">
        <div>
            <label for="event_id" class="label">Event</label>
            <select id="event_id" name="event_id" required @class(['input', 'input-invalid' => $errors->has('event_id')])>
                <option value="">Choose an event</option>
                @foreach($events as $event)
                    <option value="{{ $event->id }}" @selected((string) old('event_id', $filters['event'] ?? '') === (string) $event->id)>{{ $event->name }}, {{ $event->start_time->format('M j, Y') }}</option>
                @endforeach
            </select>
            @error('event_id') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="student_id" class="label">Student ID</label>
            <input id="student_id" type="text" name="student_id" list="student-ids" value="{{ old('student_id') }}" required autocomplete="off"
                placeholder="Type the ID or name, e.g. LGC25-105680" @class(['input code', 'input-invalid' => $errors->has('student_id')])>
            <datalist id="student-ids">
                @foreach($students as $student)
                    <option value="{{ $student->username }}">{{ $student->name }}</option>
                @endforeach
            </datalist>
            @error('student_id') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-2">
            <label for="reason" class="label">Reason</label>
            <input id="reason" type="text" name="reason" value="{{ old('reason') }}" required maxlength="255" placeholder="Excuse letter, event performer…"
                @class(['input', 'input-invalid' => $errors->has('reason')])>
            @error('reason') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    </div>
    <div class="flex justify-end border-t border-rule px-5 py-4 sm:px-6">
        <button type="submit" class="btn btn-primary">Exempt student</button>
    </div>
</form>

<form method="GET" action="{{ route('exemptions.index') }}" data-auto-filter class="mb-4 flex flex-wrap items-center gap-3">
    <label class="w-full sm:w-64">
        <span class="sr-only">Search by name or ID</span>
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search name or ID" class="input">
    </label>
    <label class="w-full sm:w-64">
        <span class="sr-only">Event</span>
        <select name="event" class="input">
            <option value="">All events</option>
            @foreach($events as $event)
                <option value="{{ $event->id }}" @selected((string) ($filters['event'] ?? '') === (string) $event->id)>{{ $event->name }}, {{ $event->start_time->format('M j') }}</option>
            @endforeach
        </select>
    </label>
    @if(array_filter($filters))
        <a href="{{ route('exemptions.index') }}" class="action">Clear</a>
    @endif
    <p class="text-sm text-ink-soft sm:ml-auto">{{ number_format($exemptions->total()) }} {{ \Illuminate\Support\Str::plural('exemption', $exemptions->total()) }}</p>
</form>

<div class="panel overflow-x-auto">
    <table class="data-table">
        <thead>
            <tr>
                <th>Student</th>
                <th>Event</th>
                <th>Reason</th>
                <th>Exempted by</th>
                <th><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse($exemptions as $exemption)
                <tr>
                    <td>
                        <span class="block font-semibold">{{ $exemption->user->name }}</span>
                        <span class="code text-sm text-ink-soft">{{ $exemption->user->username }}</span>
                    </td>
                    <td>
                        <span class="block">{{ $exemption->event->name }}</span>
                        <span class="text-sm text-ink-soft">{{ $exemption->event->start_time->format('M j, Y') }}</span>
                    </td>
                    <td>{{ $exemption->reason }}</td>
                    <td>
                        <span class="block">{{ $exemption->createdBy?->name ?? '—' }}</span>
                        <span class="text-sm text-ink-soft">{{ $exemption->created_at->format('M j, g:i A') }}</span>
                    </td>
                    <td class="text-right">
                        <form method="POST" action="{{ route('exemptions.destroy', $exemption) }}"
                            onsubmit="return confirm(@js('Remove ' . $exemption->user->name . "'s exemption from " . $exemption->event->name . '? Their fines for it (if charged) become unpaid again.'))">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="action-danger">Remove</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">{{ array_filter($filters) ? 'No exemptions match these filters.' : 'No exemptions yet. Use the form above to excuse a student from an event.' }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<x-pager :items="$exemptions" label="Exemption pages" />
@endsection
