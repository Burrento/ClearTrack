@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }">
    <div class="flex justify-between items-center">
        <h2 class="text-2xl font-bold text-gray-800">Student Management</h2>
        <button @click="open = true" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-md hover:bg-blue-700 transition duration-150">
            Register New Student
        </button>
    </div>

    <!-- Registration Modal Wrapper -->
    <div x-show="open" 
         class="fixed inset-0 z-[999] overflow-y-auto" 
         style="display: none;">
        
        <!-- Darker Background Overlay -->
        <div x-show="open"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="open = false"
             class="fixed inset-0 bg-gray-900 bg-opacity-90 backdrop-blur-sm"
             aria-hidden="true"></div>

        <!-- Modal Positioning Container -->
        <div class="flex items-center justify-center min-h-screen p-4">
            <!-- Modal Panel (Fixed Width) -->
            <div x-show="open"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-auto overflow-hidden z-[1000]"
                 @click.stop>
                
                <form method="POST" action="{{ route('students.store') }}">
                    @csrf
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-xl font-bold text-gray-900">Register New Student</h3>
                            <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label for="name" class="block text-sm font-semibold text-gray-700 mb-1">Full Name</label>
                                <input id="name" type="text" name="name" value="{{ old('name') }}" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none @error('name') border-red-500 @enderror">
                                @error('name')
                                    <p class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="username" class="block text-sm font-semibold text-gray-700 mb-1">ID Number</label>
                                <input id="username" type="text" name="username" value="{{ old('username') }}" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none @error('username') border-red-500 @enderror">
                                @error('username')
                                    <p class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="rfid_uid" class="block text-sm font-semibold text-gray-700 mb-1">RFID</label>
                                <input id="rfid_uid" type="text" name="rfid_uid" value="{{ old('rfid_uid') }}"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none @error('rfid_uid') border-red-500 @enderror">
                                @error('rfid_uid')
                                    <p class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="year_level" class="block text-sm font-semibold text-gray-700 mb-1">Year Level</label>
                                <select id="year_level" name="year_level" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none @error('year_level') border-red-500 @enderror">
                                    <option value="" disabled {{ old('year_level') ? '' : 'selected' }}>Select Year Level</option>
                                    @foreach(['First Year', 'Second Year', 'Third Year', 'Fourth Year', 'Fifth Year'] as $level)
                                        <option value="{{ $level }}" {{ old('year_level') === $level ? 'selected' : '' }}>{{ $level }}</option>
                                    @endforeach
                                </select>
                                @error('year_level')
                                    <p class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-3">
                        <button type="submit" class="w-full sm:w-auto inline-flex justify-center items-center rounded-lg px-5 py-2.5 bg-blue-600 text-white font-bold hover:bg-blue-700 transition-colors text-sm uppercase tracking-wider">
                            Register
                        </button>
                        <button type="button" @click="open = false" class="w-full sm:w-auto inline-flex justify-center items-center rounded-lg px-5 py-2.5 bg-white border border-gray-300 text-gray-700 font-bold hover:bg-gray-50 transition-colors text-sm uppercase tracking-wider">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Student List -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Number</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year Level</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RFID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($students as $student)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $student->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $student->username }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $student->department->code ?? 'N/A' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $student->year_level ?? 'N/A' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $student->rfid_uid ?? 'None' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($student->isCleared())
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Cleared</span>
                                @else
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Not Cleared</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No students found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endpush
@endsection
