@props(['name', 'size' => 24])

<svg {{ $attributes->class('et-icon') }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
    @switch($name)
        @case('cart')
            <path d="M3 4h2.2l1.35 10.15a2 2 0 0 0 1.98 1.74h8.92a2 2 0 0 0 1.95-1.56L21 7H6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="9" cy="20" r="1.35" fill="currentColor"/>
            <circle cx="18" cy="20" r="1.35" fill="currentColor"/>
            @break
        @case('arrow')
            <path d="M5 12h14M14 7l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('search')
            <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.8"/>
            <path d="m16 16 4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            @break
        @case('mobile-service')
            <path d="M3 15V8.8A1.8 1.8 0 0 1 4.8 7h9.7v8H3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
            <path d="M14.5 10h3l3 3v2h-6v-5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
            <circle cx="7" cy="17" r="2" stroke="currentColor" stroke-width="1.7"/>
            <circle cx="17.5" cy="17" r="2" stroke="currentColor" stroke-width="1.7"/>
            @break
        @case('workshop')
            <path d="M3 10 12 4l9 6v10H3V10Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
            <path d="M7 20v-7h10v7M7 16h10" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
            @break
        @case('tire')
            <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/>
            <circle cx="12" cy="12" r="4.25" stroke="currentColor" stroke-width="1.7"/>
            <path d="M12 7.75v8.5M7.75 12h8.5M9 9l6 6M15 9l-6 6" stroke="currentColor" stroke-width="1.15"/>
            @break
        @case('wheel')
            <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/>
            <circle cx="12" cy="12" r="2.15" stroke="currentColor" stroke-width="1.7"/>
            <path d="m12 9.85 1.4-4.55M14.05 11.34l4.78-1.04M13.26 13.75l3.15 3.6M10.74 13.75l-3.15 3.6M9.95 11.34 5.17 10.3" stroke="currentColor" stroke-width="1.45" stroke-linecap="round"/>
            @break
        @case('car')
            <path d="M4 14.5v-3l1.7-4.1A2.2 2.2 0 0 1 7.73 6h8.54a2.2 2.2 0 0 1 2.03 1.4l1.7 4.1v3H4Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
            <path d="M6.3 11h11.4M7 17v1M17 17v1" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
            <circle cx="7.3" cy="14" r="1" fill="currentColor"/><circle cx="16.7" cy="14" r="1" fill="currentColor"/>
            @break
        @case('package')
            <path d="m4 8 8-4 8 4-8 4-8-4Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
            <path d="M4 8v8l8 4 8-4V8M12 12v8" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
            @break
        @case('sensor')
            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7"/>
            <path d="M7.75 7.75a6 6 0 0 0 0 8.5M16.25 7.75a6 6 0 0 1 0 8.5M5 5a9.9 9.9 0 0 0 0 14M19 5a9.9 9.9 0 0 1 0 14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            @break
        @case('calendar')
            <rect x="4" y="5.5" width="16" height="14" rx="1" stroke="currentColor" stroke-width="1.7"/>
            <path d="M8 3.5v4M16 3.5v4M4 10h16" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
            @break
        @case('phone')
            <path d="M8.1 3.5 10 7.8 7.7 9.5c1.25 2.75 3.05 4.55 5.8 5.8l1.7-2.3 4.3 1.9-.7 4c-.15.9-.95 1.55-1.85 1.5C9.6 20 4 14.4 3.6 7.05c-.05-.9.6-1.7 1.5-1.85l3-.7Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('location')
            <path d="M20 10c0 5.2-8 11-8 11s-8-5.8-8-11a8 8 0 1 1 16 0Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
            <circle cx="12" cy="10" r="2.5" stroke="currentColor" stroke-width="1.7"/>
            @break
        @case('check')
            <path d="m5 12.5 4.2 4L19 6.8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('filter')
            <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            @break
        @default
            <circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.7"/>
    @endswitch
</svg>
