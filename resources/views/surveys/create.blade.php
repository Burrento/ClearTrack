@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl">
    <x-page-header title="Attach a survey" :back="route('events.show', $event)" :back-label="$event->name"
        description="Students who attended answer it from their events page." />

    <form action="{{ route('surveys.store', $event) }}" method="POST" class="panel">
        @csrf
        <div class="panel-body space-y-6">
            <div>
                <label for="title" class="label">Title</label>
                <input type="text" name="title" id="title" value="{{ old('title', $event->name . ' feedback') }}" required class="input">
            </div>

            <div>
                <label for="description" class="label">Instructions <span class="font-normal text-ink-soft">(optional)</span></label>
                <textarea name="description" id="description" rows="3" class="input">{{ old('description') }}</textarea>
            </div>

            <fieldset>
                <legend class="label">Questions</legend>
                <ol id="questions" class="list-decimal space-y-3 pl-6 marker:font-semibold marker:text-ink-soft">
                    <li><input type="text" name="questions[]" required placeholder="What did you learn from this event?" aria-label="Question 1" class="input"></li>
                </ol>
                <button type="button" id="add-question" class="btn btn-secondary mt-4">Add question</button>
            </fieldset>
        </div>

        <div class="flex justify-end gap-3 border-t border-rule px-5 py-4 sm:px-6">
            <a href="{{ route('events.show', $event) }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Attach survey</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('add-question').addEventListener('click', () => {
        const list = document.getElementById('questions');
        const li = document.createElement('li');
        li.innerHTML = `<div class="flex gap-2">
            <input type="text" name="questions[]" required aria-label="Question ${list.children.length + 1}" class="input">
            <button type="button" class="btn btn-secondary text-alarm">Remove</button>
        </div>`;
        li.querySelector('button').addEventListener('click', () => li.remove());
        list.appendChild(li);
        li.querySelector('input').focus();
    });
</script>
@endpush
