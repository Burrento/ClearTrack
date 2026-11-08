@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-lg">
    <x-page-header :title="$student->name" :back="route('admin.students')" back-label="All students"
        :description="$student->username . ', ' . $student->year_level . ', ' . ($student->department?->code ?? 'no department')" />

    <form method="POST" action="{{ route('admin.students.update', $student) }}" class="panel">
        @csrf
        @method('PUT')
        <div class="panel-body space-y-6">
            <div>
                <label for="name" class="label">Full name</label>
                <input id="name" type="text" name="name" value="{{ old('name', $student->name) }}" required
                    @class(['input', 'input-invalid' => $errors->has('name')])>
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <label for="username" class="label">Student ID</label>
                    <input id="username" type="text" name="username" value="{{ old('username', $student->username) }}" required
                        pattern="[Ll][Gg][Cc][0-9]{2}(-[0-9]+)+" title="LGC, the 2-digit year, then numbers, e.g. LGC26-6578"
                        @class(['input code', 'input-invalid' => $errors->has('username')])>
                    @error('username') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="year_level" class="label">Year level</label>
                    <select id="year_level" name="year_level" required @class(['input', 'input-invalid' => $errors->has('year_level')])>
                        @foreach(\App\Models\User::YEAR_LEVELS as $level)
                            <option value="{{ $level }}" @selected(old('year_level', $student->year_level) === $level)>{{ $level }}</option>
                        @endforeach
                    </select>
                    @error('year_level') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="hint -mt-3">The Student ID is also their username for logging in.</p>

            <div>
                <label for="department_id" class="label">Department</label>
                <select id="department_id" name="department_id" required @class(['input', 'input-invalid' => $errors->has('department_id')])>
                    @foreach($departments as $department)
                        @continue($department->archived_at && $department->id !== $student->department_id)
                        <option value="{{ $department->id }}" @selected((int) old('department_id', $student->department_id) === $department->id)>
                            {{ $department->code }}, {{ $department->name }}{{ $department->archived_at ? ' (archived)' : '' }}
                        </option>
                    @endforeach
                </select>
                <p class="hint">Moving a student keeps their past attendance and fines; from now on they appear in the new department's lists and events.</p>
                @error('department_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-start gap-3">
                <input type="checkbox" name="is_active" value="1" class="checkbox mt-0.5" @checked(old('is_active', $student->is_active))>
                <span>
                    <span class="block font-semibold">Active</span>
                    <span class="text-sm text-ink-soft">Untick for graduates or dropouts: they can't log in and aren't counted in events, but their history is kept.</span>
                </span>
            </label>

            <label class="flex items-start gap-3">
                <input type="checkbox" name="reset_password" value="1" class="checkbox mt-0.5" @checked(old('reset_password'))>
                <span>
                    <span class="block font-semibold">Reset password to the default</span>
                    <span class="text-sm text-ink-soft">Their last name plus the number at the end of their ID. The new password is shown after saving.</span>
                </span>
            </label>

            <div>
                <label for="rfid_uid" class="label">RFID card</label>
                <div class="flex gap-2">
                    <input id="rfid_uid" type="text" name="rfid_uid" value="{{ old('rfid_uid', $student->formattedRfid()) }}" data-nfc readonly
                        placeholder="Tap the new card on the reader" autocomplete="off"
                        @class(['input code cursor-not-allowed bg-paper', 'input-invalid' => $errors->has('rfid_uid')])>
                    <button type="button" onclick="document.getElementById('rfid_uid').value = ''" class="btn btn-secondary">Remove</button>
                </div>
                <p id="nfc-status" class="reader-status">Connecting to the card reader…</p>
                @error('rfid_uid') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end gap-3 border-t border-rule px-5 py-4 sm:px-6">
            <a href="{{ route('admin.students') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
    </form>
</div>
@endsection
