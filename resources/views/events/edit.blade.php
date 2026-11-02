@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl">
    <x-page-header title="Edit event" :back="route('events.show', $event)" :back-label="$event->name" />

    <form action="{{ route('events.update', $event) }}" method="POST" class="panel panel-body">
        @csrf
        @method('PUT')
        @include('events._form', ['event' => $event])

        <div class="mt-8 flex justify-end gap-3 border-t border-rule pt-6">
            <a href="{{ route('events.show', $event) }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
    </form>
</div>
@endsection
