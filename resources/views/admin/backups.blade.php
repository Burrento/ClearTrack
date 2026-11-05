@extends('layouts.app')

@section('content')
@php
    $size = fn ($bytes) => $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : max(1, round($bytes / 1024)) . ' KB';
    $kind = fn ($name) => match (true) {
        str_starts_with($name, 'manual-') => 'Made here',
        str_starts_with($name, 'pre-deploy-') => 'Before an update',
        default => 'Nightly',
    };
@endphp

<x-page-header title="Backups" description="Copies of the whole database: students, events, attendance and fines. Download one now and then to keep a copy off the server.">
    <form method="POST" action="{{ route('admin.backups.store') }}">
        @csrf
        <button type="submit" class="btn btn-primary">Back up now</button>
    </form>
</x-page-header>

<div class="panel overflow-x-auto">
    <table class="data-table">
        <thead>
            <tr>
                <th>Backup</th>
                <th>Made</th>
                <th>Type</th>
                <th class="text-right">Size</th>
                <th><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse($backups as $backup)
                <tr>
                    <td class="code whitespace-nowrap">{{ $backup['name'] }}</td>
                    <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::createFromTimestamp($backup['time'], config('app.timezone'))->format('M j, Y, g:i A') }}</td>
                    <td class="whitespace-nowrap text-ink-soft">{{ $kind($backup['name']) }}</td>
                    <td class="text-right whitespace-nowrap">{{ $size($backup['size']) }}</td>
                    <td class="text-right"><a href="{{ route('admin.backups.download', $backup['name']) }}" class="action">Download</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No backups yet. Click Back up now to make the first one.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<p class="hint mt-4">
    The server also backs up every night at 2:00 AM and right before each update. To restore a backup, ask for help: restoring replaces everything entered since that backup.
</p>
@endsection
