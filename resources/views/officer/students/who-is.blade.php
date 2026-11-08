@extends('layouts.app')

@section('content')
@php $card = $uid ? implode(':', str_split($uid, 2)) : null; @endphp

<div class="mx-auto max-w-lg">
    <x-page-header title="Who-is" description="Tap a card to see whether it's registered, and to whom. Nothing is recorded." />

    {{-- A tap fills the field and looks it up straight away (data-nfc-autosubmit in resources/js/app.js) --}}
    <form method="GET" action="{{ route('students.whoIs') }}" class="panel panel-body">
        <label for="rfid" class="label">RFID card</label>
        <div class="flex gap-2">
            <input id="rfid" type="text" name="rfid" value="{{ $card }}" data-nfc data-nfc-autosubmit autocomplete="off"
                placeholder="Tap a card, or type its number" class="input code">
            <button type="submit" class="btn btn-secondary">Look up</button>
        </div>
        <p id="nfc-status" class="reader-status">Connecting to the card reader…</p>
    </form>

    @if($uid)
        <section class="mt-6" aria-live="polite">
            @if($student && $ownDepartment)
                <div class="rounded-xl border-2 border-seal bg-seal-wash px-5 py-4">
                    <p class="text-sm font-semibold text-seal-deep">Registered</p>
                    <p class="mt-1 text-2xl font-bold">{{ $student->name }}</p>
                    <p class="mt-1">
                        <span class="code">{{ $student->username }}</span>, {{ $student->year_level ?? 'no year level' }}
                        @unless($student->is_active) <x-badge tone="muted">Deactivated</x-badge> @endunless
                    </p>
                    <a href="{{ route('students.edit', $student) }}" class="action mt-3 inline-block">Edit this student</a>
                </div>
            @elseif($student)
                <div class="rounded-xl border border-rule bg-paper px-5 py-4">
                    <p class="text-lg font-bold">Registered to a student in another department</p>
                    <p class="mt-1 text-ink-soft">Card <span class="code">{{ $card }}</span> belongs to someone outside {{ Auth::user()->department->code }}, so it can't be given to one of your students.</p>
                </div>
            @else
                <div class="rounded-xl border border-wait/30 bg-wait-wash px-5 py-4 text-wait">
                    <p class="text-lg font-bold">No student has this card</p>
                    <p class="mt-1">Card <span class="code">{{ $card }}</span> isn't registered. Give it to a student from <a href="{{ route('students.index') }}" class="underline">Students</a>: Register student, or Edit an existing one.</p>
                </div>
            @endif
        </section>
    @endif
</div>
@endsection
