@extends('layouts.app')

@section('content')
<x-page-header title="Events and attendance" description="Students who miss an event get its penalty points once absences are processed.">
    <a href="{{ route('events.create') }}" class="btn btn-primary">Create event</a>
</x-page-header>

<div class="panel overflow-x-auto">
    <table class="data-table">
        <thead>
            <tr>
                <th>Event</th>
                <th>When</th>
                <th>Penalty</th>
                <th>Status</th>
                <th><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse($events as $event)
                <tr>
                    <td><a href="{{ route('events.show', $event) }}" class="font-semibold hover:underline">{{ $event->name }}</a></td>
                    <td class="whitespace-nowrap text-ink-soft">{{ $event->schedule() }}</td>
                    <td class="whitespace-nowrap">{{ $event->penalty_points }} {{ \Illuminate\Support\Str::plural('pt', $event->penalty_points) }}</td>
                    <td><x-event-phase :event="$event" /></td>
                    <td>
                        <div class="flex justify-end gap-4">
                            <a href="{{ route('events.edit', $event) }}" class="action">Edit</a>
                            <form action="{{ route('events.destroy', $event) }}" method="POST" onsubmit="return confirm('Delete {{ addslashes($event->name) }}? Its attendance records are deleted too.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="action-danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="empty">
                        No events yet. <a href="{{ route('events.create') }}" class="action">Create the first one</a> to start taking attendance.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
