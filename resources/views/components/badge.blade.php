@props(['tone' => 'muted'])

<span {{ $attributes->class(['badge', 'badge-' . $tone]) }}>{{ $slot }}</span>
