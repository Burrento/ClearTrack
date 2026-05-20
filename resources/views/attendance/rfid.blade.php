@extends('layouts.app')

@section('content')
<div class="max-w-md mx-auto bg-white shadow-sm sm:rounded-lg overflow-hidden">
    <div class="p-6 text-center">
        <h1 class="text-2xl font-bold text-gray-800 mb-2">RFID Attendance</h1>
        <p class="text-gray-600 mb-6">Event: <strong>{{ $event->name }}</strong></p>

        <div class="mb-8">
            <div class="inline-block p-4 rounded-full bg-blue-100 text-blue-600 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                </svg>
            </div>
            <p class="text-sm text-gray-500">Please tap student RFID card on the scanner.</p>
        </div>

        <form action="{{ route('attendance.logRfid', $event) }}" method="POST" id="rfid-form">
            @csrf
            <input type="text" name="rfid_uid" id="rfid_uid" autocomplete="off" autofocus
                class="opacity-0 absolute" style="pointer-events: none;">
        </form>

        <div id="status-display" class="mt-4">
            @if(session('status'))
                <div class="text-green-600 font-bold animate-bounce">
                    {{ session('status') }}
                </div>
            @endif
            @error('rfid_uid')
                <div class="text-red-600 font-bold">
                    {{ $message }}
                </div>
            @enderror
        </div>

        <div class="mt-8 border-t pt-6">
            <a href="{{ route('events.show', $event) }}" class="text-blue-600 hover:underline text-sm">Back to Event Details</a>
        </div>
    </div>
</div>

<script>
    // Keep focus on the input field
    const rfidInput = document.getElementById('rfid_uid');
    
    document.addEventListener('click', () => {
        rfidInput.focus();
    });

    // Auto-focus on load
    window.onload = () => {
        rfidInput.focus();
    };

    // If focus is lost, regain it
    rfidInput.onblur = () => {
        setTimeout(() => rfidInput.focus(), 10);
    };
</script>
@endsection
