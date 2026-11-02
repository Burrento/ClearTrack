@extends('layouts.app')

@section('content')
<div class="mx-auto w-full max-w-[26rem]">
    {{-- The sign-in card reads as a clearance slip with the college seal pressed into its top edge --}}
    <div class="panel relative px-6 pt-20 pb-8 sm:px-9">
        <img src="{{ asset('images/lgc-nursing-logo.png') }}" alt="Luna Goco Colleges, Inc. College of Nursing seal"
            class="absolute -top-14 left-1/2 size-28 -translate-x-1/2 rounded-full bg-surface p-1 ring-1 ring-rule">

        <div class="text-center">
            <p class="text-ink-soft">Luna Goco Colleges, Inc. College of Nursing</p>
            <h1 class="mt-1 text-[2.25rem] leading-tight font-bold tracking-tight">ClearTrack</h1>
            <p class="mt-2 text-ink-soft">Event attendance, fines and clearance.</p>
        </div>

        <hr class="my-7 border-dashed border-rule">

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            <div>
                <label for="username" class="label">Student ID or username</label>
                <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus autocomplete="username"
                    placeholder="LGC25-105680" @class(['input', 'input-invalid' => $errors->has('username')])>
                @error('username')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <x-password-input name="password" label="Password" autocomplete="current-password" />

            <label class="flex items-center gap-2.5">
                <input type="checkbox" name="remember" class="checkbox">
                <span>Keep me signed in on this device</span>
            </label>

            <button type="submit" class="btn btn-primary btn-lg w-full">Sign in</button>
        </form>
    </div>
</div>
@endsection
