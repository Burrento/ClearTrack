@props(['event'])

@switch($event->phase())
    @case('live')
        <x-badge tone="good"><span class="size-2 rounded-full bg-seal motion-safe:animate-pulse" aria-hidden="true"></span>Happening now</x-badge>
        @break
    @case('upcoming')
        <x-badge tone="muted">Upcoming</x-badge>
        @break
    @case('ended')
        <x-badge tone="wait">Ended, absences not processed</x-badge>
        @break
    @default
        <x-badge tone="muted">Absences processed</x-badge>
@endswitch
