@extends('layouts.app')

@section('content')
<div class="max-w-2xl">
    <x-page-header title="Account" />

    <dl class="panel panel-body mb-8 grid gap-5 sm:grid-cols-3">
        <div>
            <dt class="text-sm text-ink-soft">Name</dt>
            <dd class="font-semibold">{{ auth()->user()->name }}</dd>
        </div>
        <div>
            <dt class="text-sm text-ink-soft">{{ auth()->user()->hasRole('student') ? 'Student ID' : 'Username' }}</dt>
            <dd class="font-semibold">{{ auth()->user()->username }}</dd>
        </div>
        @role('student')
            <div>
                <dt class="text-sm text-ink-soft">RFID card</dt>
                <dd class="code font-semibold">{{ auth()->user()->formattedRfid() ?? 'None registered' }}</dd>
            </div>
        @endrole
    </dl>

    <form method="POST" action="{{ route('settings.password') }}" class="panel">
        @csrf
        <div class="panel-body space-y-5">
            <div>
                <h2 class="panel-title">Change password</h2>
                <p class="mt-1 text-ink-soft">If you're still using the default password, change it now.</p>
            </div>
            <x-password-input name="current_password" label="Current password" autocomplete="current-password" />
            <x-password-input name="password" label="New password" autocomplete="new-password" />
            <x-password-input name="password_confirmation" label="Confirm new password" autocomplete="new-password" />
        </div>
        <div class="flex justify-end border-t border-rule px-5 py-4 sm:px-6">
            <button type="submit" class="btn btn-primary">Change password</button>
        </div>
    </form>
</div>
@endsection
