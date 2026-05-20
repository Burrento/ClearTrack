@extends('layouts.app')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="md:col-span-1">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h2 class="text-xl font-bold mb-4">Add Department</h2>
            <form action="{{ route('admin.departments') }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-700">Department Name</label>
                    <input type="text" name="name" id="name" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                </div>
                <div class="mb-6">
                    <label for="code" class="block text-sm font-medium text-gray-700">Code (e.g., SIT)</label>
                    <input type="text" name="code" id="code" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white font-bold py-2 rounded hover:bg-blue-700">Create</button>
            </form>
        </div>
    </div>

    <div class="md:col-span-2">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h2 class="text-xl font-bold mb-4">Existing Departments</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($departments as $dept)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">{{ $dept->code }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $dept->name }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
