@props([
    'name',
])

<svg {{ $attributes->merge(['class' => 'h-5 w-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('dashboard')
            <rect x="3.5" y="3.5" width="7" height="7" rx="1.5" />
            <rect x="13.5" y="3.5" width="7" height="7" rx="1.5" />
            <rect x="3.5" y="13.5" width="7" height="7" rx="1.5" />
            <rect x="13.5" y="13.5" width="7" height="7" rx="1.5" />
            @break
        @case('rfi')
            <path d="M7 3.75h7l4.25 4.5v11A1.75 1.75 0 0 1 16.5 21h-9A1.75 1.75 0 0 1 5.75 19.25v-13.75A1.75 1.75 0 0 1 7.5 3.75Z" />
            <path d="M14 4v5h4.5M8.5 13h7M8.5 16.5h7" />
            @break
        @case('qc_finding')
            <circle cx="10.75" cy="10.75" r="6.5" />
            <path d="m16 16 4.25 4.25M8 10.75l1.8 1.8 3.8-4" />
            @break
        @case('ncr')
            <path d="m12 3.5 9 16H3l9-16Z" />
            <path d="M12 9v4.5M12 17h.01" />
            @break
        @case('mdr')
        @case('doc_check')
            <path d="M7 3.75h7l4.25 4.5v11A1.75 1.75 0 0 1 16.5 21h-9A1.75 1.75 0 0 1 5.75 19.25v-13.75A1.75 1.75 0 0 1 7.5 3.75Z" />
            <path d="M14 4v5h4.5M8.5 14l2 2 4.5-4.5" />
            @break
        @case('reminder')
        @case('clock')
            <circle cx="12" cy="12" r="9" />
            <polyline points="12 7 12 12 15 15" />
            @break
        @default
            <circle cx="12" cy="12" r="8" />
    @endswitch
</svg>
