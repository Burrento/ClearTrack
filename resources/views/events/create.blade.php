@extends('layouts.app')

@section('content')
<div class="max-w-2xl">
    <x-page-header title="Create event" :back="route('events.index')" back-label="Events" />

    <form action="{{ route('events.store') }}" method="POST" class="panel panel-body">
        @csrf
        @include('events._form')

        <div class="mt-8 flex justify-end gap-3 border-t border-rule pt-6">
            <a href="{{ route('events.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Create event</button>
        </div>
    </form>
</div>
@endsection
