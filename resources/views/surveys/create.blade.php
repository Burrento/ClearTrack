@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto bg-white shadow-sm sm:rounded-lg overflow-hidden">
    <div class="p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-2">Create Survey</h1>
        <p class="text-gray-600 mb-6">Event: <strong>{{ $event->name }}</strong></p>

        <form action="{{ route('surveys.store', $event) }}" method="POST">
            @csrf

            <div class="mb-4">
                <label for="title" class="block text-sm font-medium text-gray-700">Survey Title</label>
                <input type="text" name="title" id="title" value="{{ old('title', $event->name . ' Feedback') }}" required
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="mb-6">
                <label for="description" class="block text-sm font-medium text-gray-700">Description (Optional)</label>
                <textarea name="description" id="description" rows="3"
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">{{ old('description') }}</textarea>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Survey Questions</label>
                <div id="questions-container" class="space-y-3">
                    <div class="flex items-center space-x-2">
                        <input type="text" name="questions[]" required placeholder="Enter question text"
                            class="flex-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <button type="button" id="add-question" class="mt-3 text-sm text-blue-600 font-bold hover:text-blue-800 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Another Question
                </button>
            </div>

            <div class="flex justify-end space-x-3 border-t pt-6">
                <a href="{{ route('events.show', $event) }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">Cancel</a>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">Save Survey</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('add-question').addEventListener('click', function() {
        const container = document.getElementById('questions-container');
        const div = document.createElement('div');
        div.className = 'flex items-center space-x-2';
        div.innerHTML = `
            <input type="text" name="questions[]" required placeholder="Enter question text"
                class="flex-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            <button type="button" class="text-red-500 hover:text-red-700 remove-question">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </button>
        `;
        container.appendChild(div);

        div.querySelector('.remove-question').addEventListener('click', function() {
            div.remove();
        });
    });
</script>
@endsection
