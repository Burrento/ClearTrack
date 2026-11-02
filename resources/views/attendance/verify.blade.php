@extends('layouts.app')

@section('content')
<x-page-header title="Review selfies" :back="route('events.show', $event)" :back-label="$event->name"
    description="Approve a selfie to mark the student present. Rejecting marks them absent." />

@if($pendingAttendances->isEmpty())
    <div class="panel px-6 py-14 text-center">
        <p class="text-lg font-semibold">No selfies waiting for review.</p>
        <a href="{{ route('events.show', $event) }}" class="action mt-3 inline-block">Back to {{ $event->name }}</a>
    </div>
@else
    <ul class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($pendingAttendances as $attendance)
            <li class="panel overflow-hidden">
                <img src="{{ asset('storage/' . $attendance->photo_path) }}" alt="Selfie from {{ $attendance->user->name }}" class="aspect-[4/3] w-full bg-paper object-cover">
                <div class="p-5">
                    <p class="text-lg font-bold">{{ $attendance->user->name }}</p>
                    <p class="text-sm text-ink-soft">{{ $attendance->user->username }}, sent {{ $attendance->updated_at->format('g:i A') }}</p>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <form action="{{ route('attendance.approve', [$attendance, 'absent']) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-secondary w-full text-alarm">Reject</button>
                        </form>
                        <form action="{{ route('attendance.approve', [$attendance, 'present']) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-primary w-full">Approve</button>
                        </form>
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
@endif
@endsection
