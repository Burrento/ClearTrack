@extends('layouts.app')

@section('content')
<div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Department Events</h1>
            <a href="{{ route('events.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Create Event</a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fine</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($events as $event)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $event->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $event->start_time }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $event->end_time }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">₱{{ number_format($event->fine_amount, 2) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium flex space-x-3">
                                <a href="{{ route('events.show', $event) }}" class="text-blue-600 hover:text-blue-900">View</a>
                                <a href="{{ route('events.edit', $event) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                <form action="{{ route('events.destroy', $event) }}" method="POST" onsubmit="return confirm('Are you sure?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">No events found for your department.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
