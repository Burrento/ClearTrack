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
        @php $role = ucfirst(Auth::user()->getRoleNames()->first()) . (Auth::user()->department ? ', ' . Auth::user()->department->code : ''); @endphp
        <div class="min-h-screen">
            <header class="sticky top-0 z-30 flex items-center justify-between gap-4 border-b border-rule bg-surface px-4 py-2.5 sm:px-6">
                <div class="flex items-center gap-2">
                    <button type="button" aria-label="Open menu" aria-controls="nav-drawer" onclick="document.getElementById('nav-drawer').showModal()"
                        class="grid size-10 place-items-center rounded-lg text-ink hover:bg-paper">
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
                        <img src="{{ asset('images/lgc-nursing-logo.png') }}" alt="" class="size-9">
                        <span class="text-lg font-bold">ClearTrack</span>
                    </a>
                </div>
                <p class="hidden text-right text-sm leading-tight sm:block">
                    <span class="block font-semibold">{{ Auth::user()->name }}</span>
                    <span class="text-ink-soft">{{ $role }}</span>
                </p>
            </header>

            <dialog id="nav-drawer" aria-label="Menu" onclick="if (event.target === this) this.close()"
                class="nav-drawer fixed inset-y-0 left-0 m-0 h-full max-h-none w-72 max-w-[85vw] border-r border-rule bg-surface p-0 text-ink backdrop:bg-ink/50">
                <div class="flex h-full flex-col px-4 py-5">
                    <div class="mb-8 flex items-start justify-between gap-2">
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-2">
                            <img src="{{ asset('images/lgc-nursing-logo.png') }}" alt="" class="size-11">
                            <span class="leading-tight">
                                <span class="block text-lg font-bold">ClearTrack</span>
                                <span class="block text-sm text-ink-soft">LGC College of Nursing</span>
                            </span>
                        </a>
                        <button type="button" aria-label="Close menu" onclick="this.closest('dialog').close()"
                            class="grid size-9 shrink-0 place-items-center rounded-lg text-ink-soft hover:bg-paper hover:text-ink">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto">
                        @include('layouts.partials.nav')
                    </div>

                    <div class="mt-6 border-t border-rule px-2 pt-5">
                        <p class="font-semibold">{{ Auth::user()->name }}</p>
                        <p class="text-sm text-ink-soft">{{ $role }}</p>
                        <div class="mt-3 flex gap-4 text-sm">
                            <a href="{{ route('settings') }}" @class(['font-semibold hover:underline', 'text-seal' => request()->routeIs('settings'), 'text-ink-soft' => !request()->routeIs('settings')])>Account</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="font-semibold text-ink-soft hover:underline">Log out</button>
                            </form>
                        </div>
                    </div>
                </div>
            </dialog>

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
