@extends('layouts.app')

@section('content')
<x-page-header title="Officers" description="Officers run attendance, fines and clearance for their own department." />

<div class="grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
    <div class="panel overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Department</th>
                </tr>
            </thead>
            <tbody>
                @forelse($officers as $officer)
                    <tr>
                        <td class="font-semibold">{{ $officer->name }}</td>
                        <td>{{ $officer->username }}</td>
                        <td>{{ $officer->department->code }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">No officers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <form action="{{ route('admin.officers') }}" method="POST" class="panel panel-body space-y-5 self-start">
        @csrf
        <h2 class="panel-title">Add officer</h2>
        <div>
            <label for="name" class="label">Full name</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required @class(['input', 'input-invalid' => $errors->has('name')])>
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="username" class="label">Username</label>
            <input type="text" name="username" id="username" value="{{ old('username') }}" required autocomplete="off" @class(['input', 'input-invalid' => $errors->has('username')])>
            @error('username') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <x-password-input name="password" label="Password" autocomplete="new-password" />
        <div>
            <label for="department_id" class="label">Department</label>
            <select name="department_id" id="department_id" required class="input">
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}" @selected(old('department_id') == $dept->id)>{{ $dept->code }}, {{ $dept->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary w-full">Add officer</button>
    </form>
</div>
@endsection
