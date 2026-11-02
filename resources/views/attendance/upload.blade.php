@extends('layouts.app')

@section('content')
<div class="max-w-lg">
    <x-page-header title="Send a selfie" :back="route('student.events')" back-label="My events"
        :description="'For ' . $event->name . '. An officer checks it before you\'re marked present.'" />

    <form action="{{ route('attendance.upload', $event) }}" method="POST" enctype="multipart/form-data" class="panel panel-body">
        @csrf

        <label for="photo" class="flex aspect-[4/3] cursor-pointer flex-col items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-rule bg-paper/50 text-center hover:border-seal focus-within:border-seal">
            <img id="photo-preview" alt="Your selfie" class="hidden size-full object-cover">
            <span id="photo-prompt" class="px-6">
                <svg class="mx-auto size-10 text-ink-soft" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                </svg>
                <span class="mt-3 block font-semibold">Take a photo at the event</span>
                <span class="mt-1 block text-sm text-ink-soft">Show your face and the venue. JPG or PNG, up to 2 MB.</span>
            </span>
            <input type="file" name="photo" id="photo" class="sr-only" accept="image/*" capture="user" required>
        </label>
        @error('photo')
            <p class="field-error">{{ $message }}</p>
        @enderror

        <button type="submit" class="btn btn-primary btn-lg mt-6 w-full">Send selfie</button>
    </form>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('photo').addEventListener('change', (e) => {
        const [file] = e.target.files;
        if (!file) return;
        const preview = document.getElementById('photo-preview');
        preview.src = URL.createObjectURL(file);
        preview.classList.remove('hidden');
        document.getElementById('photo-prompt').classList.add('hidden');
    });
</script>
@endpush
