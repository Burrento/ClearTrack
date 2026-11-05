@extends('layouts.app')

@section('content')
<x-page-header title="Departments" />

<div class="grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
    <div class="panel self-start overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($departments as $dept)
                    <tr @class(['text-ink-soft' => $dept->archived_at])>
                        <td class="font-bold">{{ $dept->code }}</td>
                        <td>{{ $dept->name }}</td>
                        <td>
                            @if($dept->archived_at)
                                <x-badge tone="muted">Archived</x-badge>
                            @else
                                <x-badge tone="good">Active</x-badge>
                            @endif
                        </td>
                        <td>
                            <div class="flex justify-end gap-4">
                                <a href="{{ route('admin.departments.edit', $dept) }}" class="action">Edit</a>
                                <form method="POST" action="{{ route('admin.departments.toggleArchive', $dept) }}"
                                    onsubmit="return confirm(@js($dept->archived_at
                                        ? 'Restore ' . $dept->name . '? Its officers and students can log in again.'
                                        : 'Archive ' . $dept->name . '? Its officers and students won\'t be able to log in. Nothing is deleted, and you can restore it later.'))">
                                    @csrf
                                    <button type="submit" @class(['action-danger' => !$dept->archived_at, 'action' => $dept->archived_at])>
                                        {{ $dept->archived_at ? 'Restore' : 'Archive' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">No departments yet.</td></tr>
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
