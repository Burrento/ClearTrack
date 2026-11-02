@extends('layouts.app')

@section('content')
<x-page-header title="Departments" />

<div class="grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
    <div class="panel overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                </tr>
            </thead>
            <tbody>
                @forelse($departments as $dept)
                    <tr>
                        <td class="font-bold">{{ $dept->code }}</td>
                        <td>{{ $dept->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="empty">No departments yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <form action="{{ route('admin.departments') }}" method="POST" class="panel panel-body space-y-5 self-start">
        @csrf
        <h2 class="panel-title">Add department</h2>
        <div>
            <label for="name" class="label">Name</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required placeholder="School of Information and Technology"
                @class(['input', 'input-invalid' => $errors->has('name')])>
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="code" class="label">Short code</label>
            <input type="text" name="code" id="code" value="{{ old('code') }}" required placeholder="SIT"
                @class(['input', 'input-invalid' => $errors->has('code')])>
            @error('code') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn btn-primary w-full">Add department</button>
    </form>
</div>
@endsection
