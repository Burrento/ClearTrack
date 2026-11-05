@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-lg">
    <x-page-header :title="$department->name" :back="route('admin.departments')" back-label="Departments" />

    <form method="POST" action="{{ route('admin.departments.update', $department) }}" class="panel">
        @csrf
        @method('PUT')
        <div class="panel-body space-y-6">
            <div>
                <label for="name" class="label">Name</label>
                <input id="name" type="text" name="name" value="{{ old('name', $department->name) }}" required
                    @class(['input', 'input-invalid' => $errors->has('name')])>
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="code" class="label">Short code</label>
                <input id="code" type="text" name="code" value="{{ old('code', $department->code) }}" required
                    @class(['input', 'input-invalid' => $errors->has('code')])>
                <p class="hint">Shown in the menu and on officers' and students' pages.</p>
                @error('code') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end gap-3 border-t border-rule px-5 py-4 sm:px-6">
            <a href="{{ route('admin.departments') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
    </form>
</div>
@endsection
