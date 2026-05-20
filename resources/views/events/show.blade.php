@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
        <div class="p-6">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">{{ $event->name }}</h1>
                    <p class="text-gray-600 mt-1">Department: {{ Auth::user()->department->name }}</p>
                </div>
                <div class="flex space-x-2">
                    <a href="{{ route('events.edit', $event) }}" class="bg-gray-100 text-gray-700 px-4 py-2 rounded hover:bg-gray-200">Edit Event</a>
                </div>
            </div>

            @if(session('error'))
                <div class="mt-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                    {{ session('error') }}
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-4 bg-blue-50 rounded-lg">
                    <p class="text-xs font-semibold text-blue-600 uppercase">Start Time</p>
                    <p class="text-sm font-bold text-gray-800">{{ $event->start_time }}</p>
                </div>
                <div class="p-4 bg-blue-50 rounded-lg">
                    <p class="text-xs font-semibold text-blue-600 uppercase">End Time</p>
                    <p class="text-sm font-bold text-gray-800">{{ $event->end_time }}</p>
                </div>
                <div class="p-4 bg-blue-50 rounded-lg">
                    <p class="text-xs font-semibold text-blue-600 uppercase">Fine Amount</p>
                    <p class="text-sm font-bold text-gray-800">₱{{ number_format($event->fine_amount, 2) }}</p>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap gap-4">
                <a href="{{ route('attendance.rfid', $event) }}" class="flex items-center bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 font-bold transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                    </svg>
                    Open RFID Scanner
                </a>
                
                <a href="{{ route('attendance.verify', $event) }}" class="flex items-center bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 font-bold transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Verify Selfie Uploads
                </a>

                @if($event->survey)
                    <div class="flex items-center bg-purple-100 text-purple-700 px-6 py-3 rounded-lg font-bold">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        Survey Attached
                    </div>
                @else
                    <a href="{{ route('surveys.create', $event) }}" class="flex items-center bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 font-bold transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        Attach Survey
                    </a>
                @endif

                @if($event->fines_processed)
                    <div class="flex items-center bg-gray-100 text-gray-500 px-6 py-3 rounded-lg font-bold cursor-not-allowed">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Fines Processed
                    </div>
                @else
                    <form action="{{ route('events.processFines', $event) }}" method="POST" onsubmit="return confirm('This will mark all students without attendance as absent and issue fines. Continue?')">
                        @csrf
                        <button type="submit" class="flex items-center bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 font-bold transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Process Fines
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
        <div class="p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Attendance Log</h2>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($event->attendances()->with('user')->get() as $attendance)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $attendance->user->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        {{ $attendance->status == 'present' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ ucfirst($attendance->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $attendance->updated_at }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $attendance->photo_path ? 'Selfie' : 'RFID' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500 italic">No attendance records yet for this event.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
