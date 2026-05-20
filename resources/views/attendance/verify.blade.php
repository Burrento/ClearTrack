@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto bg-white shadow-sm sm:rounded-lg overflow-hidden">
    <div class="p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-2">Verify Selfie Uploads</h1>
        <p class="text-gray-600 mb-6">Event: <strong>{{ $event->name }}</strong></p>

        @if($pendingAttendances->isEmpty())
            <div class="text-center py-12">
                <p class="text-gray-500 italic">No pending selfie uploads for this event.</p>
                <a href="{{ route('events.show', $event) }}" class="text-blue-600 hover:underline mt-4 inline-block">Back to Event</a>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach($pendingAttendances as $attendance)
                    <div class="border rounded-lg overflow-hidden flex flex-col">
                        <img src="{{ asset('storage/' . $attendance->photo_path) }}" alt="Selfie" class="h-64 w-full object-cover">
                        <div class="p-4 flex-grow">
                            <h3 class="font-bold text-lg">{{ $attendance->user->name }}</h3>
                            <p class="text-sm text-gray-500 mb-4">Uploaded at: {{ $attendance->updated_at }}</p>
                            
                            <div class="flex space-x-2">
                                <form action="{{ route('attendance.approve', [$attendance, 'present']) }}" method="POST" class="flex-1">
                                    @csrf
                                    <button type="submit" class="w-full bg-green-600 text-white font-bold py-2 rounded hover:bg-green-700 transition">
                                        Approve
                                    </button>
                                </form>
                                <form action="{{ route('attendance.approve', [$attendance, 'absent']) }}" method="POST" class="flex-1">
                                    @csrf
                                    <button type="submit" class="w-full bg-red-600 text-white font-bold py-2 rounded hover:bg-red-700 transition">
                                        Reject
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
