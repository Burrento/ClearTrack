@extends('layouts.app')

@section('content')
<div class="max-w-md mx-auto bg-white shadow-sm sm:rounded-lg overflow-hidden">
    <div class="p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-2">Upload Selfie</h1>
        <p class="text-gray-600 mb-6">Event: <strong>{{ $event->name }}</strong></p>

        <form action="{{ route('attendance.upload', $event) }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Take or select a photo</label>
                <div class="flex items-center justify-center w-full">
                    <label class="flex flex-col items-center justify-center w-full h-64 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                            <svg class="w-10 h-10 mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                            <p class="mb-2 text-sm text-gray-500 font-semibold">Click to upload or take a photo</p>
                            <p class="text-xs text-gray-500">PNG, JPG or JPEG (MAX. 2MB)</p>
                        </div>
                        <input type="file" name="photo" id="photo" class="hidden" accept="image/*" capture="user" required />
                    </label>
                </div>
                @error('photo')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 px-4 rounded-md hover:bg-blue-700 transition">
                Submit Selfie
            </button>
        </form>

        <div class="mt-6 border-t pt-4 text-center">
            <a href="{{ route('student.events') }}" class="text-gray-500 hover:underline text-sm">Cancel</a>
        </div>
    </div>
</div>

<script>
    // Preview image (optional enhancement)
    document.getElementById('photo').onchange = evt => {
        const [file] = evt.target.files
        if (file) {
            // You could add a preview here if desired
        }
    }
</script>
@endsection
