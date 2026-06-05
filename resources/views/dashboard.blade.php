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
                    @if(Auth::user()->isClearanceActive())
                        @if(Auth::user()->isCleared())
                            <div class="text-4xl font-black text-green-600">CLEARED</div>
                            <p class="text-xs text-green-700 mt-2">All obligations met.</p>
                        @else
                            <div class="text-4xl font-black text-red-600">NOT CLEARED</div>
                            <p class="text-xs text-red-700 mt-2">You have unpaid fines. See details below.</p>
                        @endif
                    @else
                        <div class="text-2xl font-bold text-gray-400">PENDING</div>
                        <p class="text-xs text-gray-500 mt-2">Clearance period has not started yet.</p>
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

            @php
                $totalPoints = Auth::user()->getTotalPenaltyPoints();
                $matchedTier = Auth::user()->getMatchedTier();
                $activeClearance = Auth::user()->getActiveClearancePeriod();
            @endphp

            @if($totalPoints > 0)
            <div class="mt-6 bg-white border rounded-lg shadow-sm overflow-hidden">
                <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Penalty Points Summary</h3>
                    <div class="flex items-center gap-4 mb-4">
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center min-w-[120px]">
                            <p class="text-3xl font-black text-red-600">{{ $totalPoints }}</p>
                            <p class="text-xs font-semibold text-red-500 uppercase">Total Points</p>
                        </div>
                        @if($matchedTier)
                        <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 text-center min-w-[120px]">
                            <p class="text-3xl font-black text-orange-600">₱{{ number_format($matchedTier['fine_amount'], 2) }}</p>
                            <p class="text-xs font-semibold text-orange-500 uppercase">Fine Amount</p>
                        </div>
                        @endif
                    </div>
                    @if($matchedTier && !empty($matchedTier['requirements']))
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                        <p class="text-sm font-medium text-yellow-800">
                            <span class="font-bold">Additional Requirement:</span> {{ $matchedTier['requirements'] }}
                        </p>
                    </div>
                    @endif
                </div>
            </div>
            @endif

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
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $fine->event ? $fine->event->name : 'Clearance' }}</td>
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
                <div class="bg-white border p-6 rounded-lg shadow-sm hover:shadow-md transition">
                    <h3 class="font-bold text-gray-800 mb-2">Fines Settings</h3>
                    <p class="text-sm text-gray-500 mb-4">Define fine tiers and process student fines.</p>
                    <a href="{{ route('fines-settings.index') }}" class="text-blue-600 font-bold hover:underline">Fines Settings &rarr;</a>
                </div>
                <div class="bg-white border p-6 rounded-lg shadow-sm hover:shadow-md transition">
                    <h3 class="font-bold text-gray-800 mb-2">Clearance</h3>
                    <p class="text-sm text-gray-500 mb-4">Clear students after settling fines.</p>
                    <a href="{{ route('clearance.index') }}" class="text-blue-600 font-bold hover:underline">Manage Clearance &rarr;</a>
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

{{-- NOT CLEARED Popup Modal for Students --}}
@role('student')
    @php
        $showPopup = Auth::user()->isClearanceActive() && !Auth::user()->isCleared();
    @endphp

    @if($showPopup)
    <div id="clearanceModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background-color: rgba(0,0,0,0.6);">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden transform transition-all">
            {{-- Header --}}
            <div class="bg-red-600 px-6 py-5 text-center">
                <svg class="mx-auto h-12 w-12 text-white mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <h2 class="text-2xl font-black text-white tracking-wide">NOT CLEARED</h2>
                <p class="text-red-100 text-sm mt-1">Clearance period is active</p>
            </div>

            {{-- Body --}}
            <div class="px-6 py-5 space-y-4">
                <div class="text-center">
                    <p class="text-gray-600 text-sm">Your accumulated penalty points:</p>
                    <p class="text-5xl font-black text-red-600 my-2">{{ $totalPoints }}</p>
                    <p class="text-xs text-gray-500 uppercase font-semibold">Total Points</p>
                </div>

                @if($matchedTier)
                <div class="border-t pt-4">
                    <p class="text-sm font-bold text-gray-800 mb-3 text-center">To be cleared, you must complete:</p>
                    
                    <div class="space-y-3">
                        <div class="flex items-center justify-between bg-red-50 rounded-lg p-3">
                            <span class="text-sm font-medium text-gray-700">Monetary Fine</span>
                            <span class="text-lg font-black text-red-600">₱{{ number_format($matchedTier['fine_amount'], 2) }}</span>
                        </div>

                        @if(!empty($matchedTier['requirements']))
                        <div class="flex items-start bg-orange-50 rounded-lg p-3">
                            <svg class="h-5 w-5 text-orange-500 mr-2 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                            <div>
                                <span class="text-sm font-medium text-gray-700">Additional Requirement</span>
                                <p class="text-sm text-orange-700 font-bold">{{ $matchedTier['requirements'] }}</p>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                @else
                <div class="border-t pt-4 text-center">
                    <p class="text-sm text-gray-600">Please contact your department officer for clearance details.</p>
                </div>
                @endif
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 bg-gray-50 text-center">
                <button onclick="document.getElementById('clearanceModal').style.display='none'" class="w-full px-4 py-2 bg-gray-800 text-white font-bold rounded-lg hover:bg-gray-900 transition">
                    I Understand
                </button>
            </div>
        </div>
    </div>
    @endif
@endrole
@endsection
