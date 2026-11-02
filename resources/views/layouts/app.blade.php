<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ClearTrack</title>
    <link rel="icon" type="image/png" href="{{ asset('images/lgc-nursing-logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible+Mono:wght@400;600&family=Atkinson+Hyperlegible+Next:ital,wght@0,400..800;1,400&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2 focus:font-semibold">Skip to content</a>

    @auth
        <div class="min-h-screen lg:grid lg:grid-cols-[17rem_1fr]">
            {{-- Desktop sidebar --}}
            <aside class="sticky top-0 hidden h-screen flex-col border-r border-rule bg-surface px-4 py-6 lg:flex">
                <a href="{{ route('dashboard') }}" class="mb-8 flex items-center gap-3 px-2">
                    <img src="{{ asset('images/lgc-nursing-logo.png') }}" alt="" class="size-11">
                    <span class="leading-tight">
                        <span class="block text-lg font-bold">ClearTrack</span>
                        <span class="block text-sm text-ink-soft">LGC College of Nursing</span>
                    </span>
                </a>

                <div class="flex-1 overflow-y-auto">
                    @include('layouts.partials.nav')
                </div>

                <div class="mt-6 border-t border-rule px-2 pt-5">
                    <p class="font-semibold">{{ Auth::user()->name }}</p>
                    <p class="text-sm text-ink-soft">
                        {{ ucfirst(Auth::user()->getRoleNames()->first()) }}@if(Auth::user()->department), {{ Auth::user()->department->code }}@endif
                    </p>
                    <div class="mt-3 flex gap-4 text-sm">
                        <a href="{{ route('settings') }}" @class(['font-semibold hover:underline', 'text-seal' => request()->routeIs('settings'), 'text-ink-soft' => !request()->routeIs('settings')])>Account</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="font-semibold text-ink-soft hover:underline">Log out</button>
                        </form>
                    </div>
                </div>
            </aside>

            {{-- Mobile top bar --}}
            <header class="sticky top-0 z-30 border-b border-rule bg-surface lg:hidden">
                <details class="group">
                    <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 [&::-webkit-details-marker]:hidden">
                        <span class="flex items-center gap-2.5">
                            <img src="{{ asset('images/lgc-nursing-logo.png') }}" alt="" class="size-9">
                            <span class="text-lg font-bold">ClearTrack</span>
                        </span>
                        <span class="btn btn-secondary btn-sm">
                            <span class="group-open:hidden">Menu</span>
                            <span class="hidden group-open:inline">Close</span>
                        </span>
                    </summary>
                    <div class="border-t border-rule px-4 pt-5 pb-6">
                        @include('layouts.partials.nav')
                        <div class="mt-6 flex items-center justify-between border-t border-rule pt-5 text-sm">
                            <span>
                                <span class="block font-semibold">{{ Auth::user()->name }}</span>
                                <a href="{{ route('settings') }}" class="font-semibold text-ink-soft hover:underline">Account</a>
                            </span>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-sm">Log out</button>
                            </form>
                        </div>
                    </div>
                </details>
            </header>

            <main id="main" class="px-4 py-8 sm:px-8 lg:px-12 lg:py-12">
                <div class="mx-auto max-w-6xl">
                    @include('layouts.partials.flash')
                    @yield('content')
                </div>
            </main>
        </div>
    @else
        <main id="main" class="grid min-h-screen place-items-center px-4 py-16">
            <div class="w-full">
                @yield('content')
            </div>
        </main>
    @endauth

    <script>
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', show);
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            button.querySelector('.eye-open').classList.toggle('hidden', show);
            button.querySelector('.eye-closed').classList.toggle('hidden', !show);
        }
    </script>
    @stack('scripts')
</body>
</html>
