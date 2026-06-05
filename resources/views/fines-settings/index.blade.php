@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    {{-- Status Banner --}}
    @if($clearance && $clearance->isActive())
        <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-bold text-green-800">Fines Processed — Clearance is ACTIVE</p>
                    <p class="text-xs text-green-700">Processed: {{ $clearance->activated_at->format('M d, Y h:i A') }}. Students can now see their clearance status.</p>
                </div>
            </div>
        </div>
    @else
        <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-r-lg">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-bold text-yellow-800">Fines Not Yet Processed</p>
                    <p class="text-xs text-yellow-700">Define fine tiers below, then press "Process Student Fines" when ready.</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Fine Tier Builder --}}
    <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
        <div class="p-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Fines Settings</h1>
            <p class="text-sm text-gray-500 mb-6">Define penalty rules that map accumulated points to fines and requirements.</p>

            <form action="{{ route('fines-settings.storeTiers') }}" method="POST" id="tierForm">
                @csrf

                <div id="tiersContainer">
                    @if($clearance && $clearance->fine_tiers)
                        @foreach($clearance->fine_tiers as $index => $tier)
                            <div class="tier-row bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4" data-index="{{ $index }}">
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-sm font-bold text-gray-700">Tier {{ $index + 1 }}</span>
                                    <button type="button" onclick="removeTier(this)" class="text-red-500 hover:text-red-700 text-sm font-medium">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Min Points</label>
                                        <input type="number" name="tiers[{{ $index }}][min_points]" value="{{ $tier['min_points'] }}" required min="1"
                                            class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Max Points <span class="text-gray-400">(blank = no limit)</span></label>
                                        <input type="number" name="tiers[{{ $index }}][max_points]" value="{{ $tier['max_points'] }}" min="1"
                                            class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Fine Amount (₱)</label>
                                        <input type="number" step="0.01" name="tiers[{{ $index }}][fine_amount]" value="{{ $tier['fine_amount'] }}" required min="0"
                                            class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Additional Requirements</label>
                                        <input type="text" name="tiers[{{ $index }}][requirements]" value="{{ $tier['requirements'] ?? '' }}" placeholder="e.g. Sorry Letter"
                                            class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                <button type="button" onclick="addTier()" class="mb-6 flex items-center text-blue-600 hover:text-blue-800 font-medium text-sm transition">
                    <svg class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Add Tier
                </button>

                <div class="flex items-center border-t pt-4">
                    <button type="submit" class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition">
                        Save Fine Tiers
                    </button>
                </div>
            </form>

            @if($clearance && $clearance->fine_tiers)
                <div class="mt-4 pt-4 border-t flex justify-end">
                    @if($clearance->isActive())
                        <form action="{{ route('fines-settings.reset') }}" method="POST" onsubmit="return confirm('Reset clearance? This will deactivate the clearance period and remove all clearance fines.')">
                            @csrf
                            <button type="submit" class="px-6 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 transition">
                                Reset Clearance
                            </button>
                        </form>
                    @else
                        <form action="{{ route('fines-settings.process') }}" method="POST" onsubmit="return confirm('This will calculate fines for all students based on their accumulated penalty points and the fine tiers. Continue?')">
                            @csrf
                            <button type="submit" class="px-6 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 transition">
                                Process Student Fines
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Fine Tier Preview --}}
    @if($clearance && $clearance->fine_tiers)
    <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
        <div class="p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Current Fine Tiers Preview</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Points Range</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fine Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requirements</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($clearance->fine_tiers as $tier)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $tier['min_points'] }} pts
                                    @if(isset($tier['max_points']) && $tier['max_points'])
                                        — {{ $tier['max_points'] }} pts
                                    @else
                                        and above
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-red-600">₱{{ number_format($tier['fine_amount'], 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    {{ $tier['requirements'] ?: '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    let tierIndex = {{ ($clearance && $clearance->fine_tiers) ? count($clearance->fine_tiers) : 0 }};

    function addTier() {
        const container = document.getElementById('tiersContainer');
        const html = `
            <div class="tier-row bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4" data-index="${tierIndex}">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-bold text-gray-700">New Tier</span>
                    <button type="button" onclick="removeTier(this)" class="text-red-500 hover:text-red-700 text-sm font-medium">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Min Points</label>
                        <input type="number" name="tiers[${tierIndex}][min_points]" required min="1"
                            class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Max Points <span class="text-gray-400">(blank = no limit)</span></label>
                        <input type="number" name="tiers[${tierIndex}][max_points]" min="1"
                            class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Fine Amount (₱)</label>
                        <input type="number" step="0.01" name="tiers[${tierIndex}][fine_amount]" required min="0"
                            class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Additional Requirements</label>
                        <input type="text" name="tiers[${tierIndex}][requirements]" placeholder="e.g. Sorry Letter"
                            class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        tierIndex++;
    }

    function removeTier(button) {
        const row = button.closest('.tier-row');
        row.remove();
    }

    // Start with one empty tier if none exist
    if (document.querySelectorAll('.tier-row').length === 0) {
        addTier();
    }
</script>
@endpush
