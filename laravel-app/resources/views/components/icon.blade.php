@props(['name' => 'grid'])
<svg {{ $attributes->class(['size-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('settings') <path d="m9 3-1 3-3 1v4l2 1-2 1v4l3 1 1 3h6l1-3 3-1v-4l-2-1 2-1V7l-3-1-1-3z"/><circle cx="12" cy="12" r="3"/> @break
        @case('review') <path d="M9 5H6v16h13V5h-3M9 3h7v4H9zM9 13l2 2 4-4"/> @break
        @case('services') <path d="m14 6 4 4M3 21l7-7m-4-4L3 7l4-4 3 3m3 7 8 8M14 3a6 6 0 0 0-7 7l7 7a6 6 0 0 0 7-7l-4 3-6-6z"/> @break
        @case('templates') <rect x="3" y="3" width="14" height="14" rx="2"/><path d="M7 7h6M7 11h3m11-4v14H7"/> @break
        @case('activity') <path d="M3 12h4l3-8 4 16 3-8h4"/> @break
        @case('status') <path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6zM8 12l3 3 5-6"/> @break
        @case('profile') <circle cx="10" cy="7" r="4"/><path d="M3 21v-2a7 7 0 0 1 10-6m2 8 6-6-3-3-6 6-1 4z"/> @break
        @case('logout') <path d="M9 4H4v16h5m5-13 5 5-5 5m-6-5h11"/> @break
        @case('people') <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.9M15 3.1a4 4 0 0 1 0 7.8"/><circle cx="9" cy="7" r="4"/> @break
        @case('job') @case('briefcase') <rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V3h8v4M3 12h18m-9-2v4"/> @break
        @case('file') <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Zm0 0v6h6M8 13h8m-8 4h6"/> @break
        @case('arrow') <path d="M5 12h14m-6-6 6 6-6 6"/> @break
        @case('check') <path d="m5 12 4 4L19 6"/> @break
        @case('menu') <path d="M4 6h16M4 12h16M4 18h16"/> @break
        @case('lock') <rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/> @break
        @case('bell') <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-11 12h4"/> @break
        @case('alert') <path d="M12 3 2 20h20zM12 10v4m0 3h.01"/> @break
        @case('calendar') <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4m8-4v4"/> @break
        @case('clock') <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/> @break
        @case('upload') <path d="M12 16V4m-5 5 5-5 5 5M4 16v4h16v-4"/> @break
        @case('print') <path d="M7 9V3h10v6M7 17H4v-7h16v7h-3M7 14h10v7H7z"/> @break
        @case('mail') <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/> @break
        @case('search') <circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/> @break
        @case('plus') <path d="M12 5v14M5 12h14"/> @break
        @case('camera') <path d="M4 8h3l2-3h6l2 3h3v12H4z"/><circle cx="12" cy="13" r="4"/> @break
        @case('money') <rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M6 12h.01M18 12h.01"/> @break
        @case('note') <path d="M4 4h16v12l-4 4H4zM16 20v-4h4M8 9h8m-8 4h5"/> @break
        @case('trash') <path d="M4 7h16M9 7V4h6v3m-8 0 1 13h8l1-13"/> @break
        @default <rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>
    @endswitch
</svg>
