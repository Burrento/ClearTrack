@extends('layouts.app')

@section('content')
<div class="max-w-lg">
    <x-page-header :title="$student->name" :back="route('students.index')" back-label="Students"
        :description="$student->username . ', ' . $student->year_level" />

    <form method="POST" action="{{ route('students.update', $student) }}" class="panel">
        @csrf
        @method('PUT')
        <div class="panel-body space-y-6">
            <div>
                <label for="name" class="label">Full name</label>
                <input id="name" type="text" name="name" value="{{ old('name', $student->name) }}" required
                    @class(['input', 'input-invalid' => $errors->has('name')])>
                <p class="hint">Changing the name doesn't change their password.</p>
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="rfid_uid" class="label">RFID card</label>
                <div class="flex gap-2">
                    <input id="rfid_uid" type="text" name="rfid_uid" value="{{ old('rfid_uid', $student->formattedRfid()) }}" data-nfc readonly
                        placeholder="Tap the new card on the reader" autocomplete="off"
                        @class(['input code cursor-not-allowed bg-paper', 'input-invalid' => $errors->has('rfid_uid')])>
                    <button type="button" onclick="document.getElementById('rfid_uid').value = ''" class="btn btn-secondary">Remove</button>
                </div>
                <p id="nfc-status" class="hint"></p>
                <p class="hint">Tap a card to replace the current one, for example when a card is lost.</p>
                @error('rfid_uid') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end gap-3 border-t border-rule px-5 py-4 sm:px-6">
            <a href="{{ route('students.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
    </form>
</div>
@endsection
