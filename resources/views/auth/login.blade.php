@extends('layouts.app')

@section('content')
<div class="flex flex-col items-center justify-center">
    <div class="w-full max-w-md bg-white rounded-lg shadow-md p-8">
        <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Login to ClearTrack</h2>
        
        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mb-4">
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username / ID Number</label>
                <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus
                    class="w-full px-4 py-2 border rounded-md focus:ring-blue-500 focus:border-blue-500 @error('username') border-red-500 @enderror">
                @error('username')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input id="password" type="password" name="password" required
                    class="w-full px-4 py-2 border rounded-md focus:ring-blue-500 focus:border-blue-500 @error('password') border-red-500 @enderror">
                @error('password')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center">
                    <input id="remember" type="checkbox" name="remember" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="remember" class="ml-2 block text-sm text-gray-900">Remember Me</label>
                </div>
            </div>

            <div>
                <button type="submit" class="w-full bg-blue-600 text-white font-bold py-2 px-4 rounded-md hover:bg-blue-700 transition duration-150">
                    Sign In
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
