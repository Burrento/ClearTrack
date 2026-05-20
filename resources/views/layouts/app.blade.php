<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'ClearTrack') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 font-sans antialiased">
    <div class="min-h-screen">
        <nav class="bg-white border-b border-gray-100 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="shrink-0 flex items-center">
                            <a href="{{ route('dashboard') }}" class="text-2xl font-bold text-blue-600">ClearTrack</a>
                        </div>
                        <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">
                            @auth
                                <a href="{{ route('dashboard') }}" class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('dashboard') ? 'border-blue-400' : 'border-transparent' }} text-sm font-medium leading-5 text-gray-900 focus:outline-none focus:border-blue-700 transition duration-150 ease-in-out">
                                    Dashboard
                                </a>
                                @role('officer')
                                    <a href="{{ route('events.index') }}" class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('events.*') ? 'border-blue-400' : 'border-transparent' }} text-sm font-medium leading-5 text-gray-900 focus:outline-none focus:border-blue-700 transition duration-150 ease-in-out">
                                        Events
                                    </a>
                                    <a href="{{ route('students.index') }}" class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('students.*') ? 'border-blue-400' : 'border-transparent' }} text-sm font-medium leading-5 text-gray-900 focus:outline-none focus:border-blue-700 transition duration-150 ease-in-out">
                                        Students
                                    </a>
                                    <a href="{{ route('fines.index') }}" class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('fines.*') ? 'border-blue-400' : 'border-transparent' }} text-sm font-medium leading-5 text-gray-900 focus:outline-none focus:border-blue-700 transition duration-150 ease-in-out">
                                        Fines
                                    </a>
                                @endrole
                                @role('student')
                                    <a href="{{ route('student.events') }}" class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('student.events') ? 'border-blue-400' : 'border-transparent' }} text-sm font-medium leading-5 text-gray-900 focus:outline-none focus:border-blue-700 transition duration-150 ease-in-out">
                                        My Attendance
                                    </a>
                                @endrole
                                @role('admin')
                                    <a href="{{ route('admin.departments') }}" class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('admin.departments') ? 'border-blue-400' : 'border-transparent' }} text-sm font-medium leading-5 text-gray-900 focus:outline-none focus:border-blue-700 transition duration-150 ease-in-out">
                                        Departments
                                    </a>
                                    <a href="{{ route('admin.officers') }}" class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('admin.officers') ? 'border-blue-400' : 'border-transparent' }} text-sm font-medium leading-5 text-gray-900 focus:outline-none focus:border-blue-700 transition duration-150 ease-in-out">
                                        Officers
                                    </a>
                                @endrole
                            @endauth
                        </div>
                    </div>
                    <div class="flex items-center">
                        @auth
                            <div class="flex items-center">
                                <span class="text-gray-900 font-bold" style="margin-right: 2rem;">{{ Auth::user()->name }}</span>
                                <a href="{{ route('settings') }}" class="text-sm text-gray-600 hover:text-blue-600 font-medium transition-colors" style="margin-right: 2rem;">Settings</a>
                                <form method="POST" action="{{ route('logout') }}" class="flex items-center">
                                    @csrf
                                    <button type="submit" class="text-sm text-gray-600 hover:text-red-600 font-medium transition-colors">Logout</button>
                                </form>
                            </div>
                        @else
                            <a href="{{ route('login') }}" class="text-gray-500 hover:text-gray-700">Login</a>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>

        <main class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                @if (session('status'))
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        {{ session('status') }}
                    </div>
                @endif
                @yield('content')
            </div>
        </main>
    </div>
    @stack('scripts')
</body>
</html>
