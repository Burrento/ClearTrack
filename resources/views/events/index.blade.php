@extends('layouts.app')

@section('content')
<x-page-header title="Events and attendance" description="Absent and late students are fined once an event's absences are processed.">
    <a href="{{ route('events.create') }}" class="btn btn-primary">Create event</a>
</x-page-header>

<div class="panel overflow-x-auto">
    <table class="data-table">
        <thead>
            <tr>
                <th>Event</th>
                <th>When</th>
                <th>Fines</th>
                <th>Status</th>
                <th><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse($events as $event)
                <tr>
                    <td class="min-w-48 align-top">
                        <a href="{{ route('events.show', $event) }}" class="font-semibold hover:underline">{{ $event->name }}</a>
                        @if($event->year_levels)
                            <span class="mt-1 block text-sm text-ink-soft">{{ $event->audience() }}</span>
                        @endif
                    </td>
                    <td class="align-top whitespace-nowrap">
                        <span class="block font-semibold">{{ $event->start_time->format('D, M j, Y') }}</span>
                        @foreach($event->sessions as $session)
                            <span class="block text-sm text-ink-soft">
                                <span class="inline-block w-20 text-ink">{{ $session->label() }}</span>{{ $session->times() }}
                            </span>
                        @endforeach
                    </td>
                    <td class="align-top whitespace-nowrap text-sm">
                        <dl class="grid grid-cols-[auto_auto] gap-x-3">
                            @foreach(['No time in' => $event->missed_in_fine, 'No time out' => $event->missed_out_fine, 'Late' => $event->late_fine] as $label => $amount)
                                <dt class="text-ink-soft">{{ $label }}</dt>
                                <dd class="text-right font-semibold">₱{{ number_format($amount, 2) }}</dd>
                            @endforeach
                        </dl>
                    </td>
                    <td class="align-top"><x-event-phase :event="$event" /></td>
                    <td class="align-top">
                        <div class="flex justify-end gap-4">
                            <a href="{{ route('events.show', $event) }}" class="action">Open</a>
                            @unless($event->fines_processed)
                                <a href="{{ route('events.edit', $event) }}" class="action">Edit</a>
                            @endunless
                            <form action="{{ route('events.destroy', $event) }}" method="POST" onsubmit="return confirm(@js('Delete ' . $event->name . '? Its attendance records and fines, paid or not, are deleted too.'))">
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

<x-pager :items="$events" label="Event pages" />
@endsection
