@extends('layouts.app')

@section('content')
<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
    <div class="p-6 text-gray-900">
        <h1 class="text-2xl font-semibold mb-4">Welcome, {{ Auth::user()->name }}!</h1>
        <p>You are logged in as a <strong>{{ Auth::user()->getRoleNames()->first() }}</strong>.</p>
        
        @role('student')
            <div class="mt-6 flex flex-col md:flex-row gap-6">
                <div class="flex-1 bg-white border p-6 rounded-lg shadow-sm flex flex-col items-center justify-center">
                    <p class="text-sm font-semibold text-gray-500 uppercase mb-2">Clearance Status</p>
                    @if(Auth::user()->isCleared())
                        <div class="text-4xl font-black text-green-600">CLEARED</div>
                        <p class="text-xs text-green-700 mt-2">All obligations met.</p>
                    @else
                        <div class="text-4xl font-black text-red-600">NOT CLEARED</div>
                        <p class="text-xs text-red-700 mt-2">You have unpaid fines or pending surveys.</p>
                    @endif
                </div>
                
                <div class="flex-1 bg-white border p-6 rounded-lg shadow-sm">
                    <h3 class="font-bold text-gray-800 mb-4 border-b pb-2">Quick Actions</h3>
                    <div class="space-y-2">
                        <a href="{{ route('student.events') }}" class="block p-2 bg-blue-50 text-blue-700 rounded hover:bg-blue-100 font-medium">View Events & Attendance</a>
                        <p class="text-xs text-gray-500 italic">Check your attendance history and upload selfies here.</p>
                    </div>
                </div>
            </div>

            <div class="mt-8">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Your Recent Fines</h3>
                <div class="bg-white border rounded-lg overflow-hidden shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse(Auth::user()->fines()->with('event')->latest()->take(5)->get() as $fine)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $fine->event->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₱{{ number_format($fine->amount, 2) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $fine->status == 'paid' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ ucfirst($fine->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500 italic">No fines recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endrole

        @role('officer')
            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="bg-white border p-6 rounded-lg shadow-sm hover:shadow-md transition">
                    <h3 class="font-bold text-gray-800 mb-2">Events</h3>
                    <p class="text-sm text-gray-500 mb-4">Create and manage department events.</p>
                    <a href="{{ route('events.index') }}" class="text-blue-600 font-bold hover:underline">Manage Events &rarr;</a>
                </div>
                <div class="bg-white border p-6 rounded-lg shadow-sm hover:shadow-md transition">
                    <h3 class="font-bold text-gray-800 mb-2">Fines</h3>
                    <p class="text-sm text-gray-500 mb-4">Process student fine payments.</p>
                    <a href="{{ route('fines.index') }}" class="text-blue-600 font-bold hover:underline">Manage Fines &rarr;</a>
                </div>
            </div>
        @endrole

        @role('admin')
            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="bg-white border p-6 rounded-lg shadow-sm hover:shadow-md transition">
                    <h3 class="font-bold text-gray-800 mb-2">Departments</h3>
                    <p class="text-sm text-gray-500 mb-4">Manage academic departments.</p>
                    <a href="{{ route('admin.departments') }}" class="text-blue-600 font-bold hover:underline">Manage Departments &rarr;</a>
                </div>
                <div class="bg-white border p-6 rounded-lg shadow-sm hover:shadow-md transition">
                    <h3 class="font-bold text-gray-800 mb-2">Officers</h3>
                    <p class="text-sm text-gray-500 mb-4">Assign department officers.</p>
                    <a href="{{ route('admin.officers') }}" class="text-blue-600 font-bold hover:underline">Manage Officers &rarr;</a>
                </div>
            </div>
        @endrole
    </div>
</div>
@endsection
