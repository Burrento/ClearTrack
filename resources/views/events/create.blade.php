@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto bg-white shadow-sm sm:rounded-lg overflow-hidden">
    <div class="p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Create New Event</h1>

        <form action="{{ route('events.store') }}" method="POST">
            @csrf

            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700">Event Name</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="start_time" class="block text-sm font-medium text-gray-700">Start Time</label>
                    <input type="datetime-local" name="start_time" id="start_time" value="{{ old('start_time') }}" required
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="end_time" class="block text-sm font-medium text-gray-700">End Time</label>
                    <input type="datetime-local" name="end_time" id="end_time" value="{{ old('end_time') }}" required
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            <div class="mb-4">
                <label for="fine_amount" class="block text-sm font-medium text-gray-700">Fine Amount (₱)</label>
                <input type="number" step="0.01" name="fine_amount" id="fine_amount" value="{{ old('fine_amount', 0) }}" required
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="mb-6 flex items-center">
                <input type="checkbox" name="requires_survey" id="requires_survey" value="1" {{ old('requires_survey') ? 'checked' : '' }}
                    class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <label for="requires_survey" class="ml-2 block text-sm text-gray-700">Requires Survey Completion for Clearance</label>
            </div>

            <div class="flex justify-end space-x-3">
                <a href="{{ route('events.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">Cancel</a>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">Create Event</button>
            </div>
        </form>
    </div>
</div>
@endsection
