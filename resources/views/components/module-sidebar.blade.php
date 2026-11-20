@props(['active' => ''])

@php
    $user = Auth::user();
    $role = $user->role;
    $roleLabel = match ($role) {
        'admin' => 'Super Admin',
        'production' => 'Production',
        'qc_qa' => 'QC / QA',
        'warehouse' => 'Warehouse',
        'ppic' => 'PPIC',
        default => $role,
    };
    $userInitials = collect(preg_split('/\s+/', trim($user->name)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
    $userInitials = $userInitials !== '' ? $userInitials : 'U';
    $menuItems = collect([
        [
            'key' => 'dashboard',
            'label' => 'Dashboard',
            'route' => 'dashboard',
            'icon' => 'dashboard',
            'roles' => config('mudalaya.modules.rfi.roles'),
        ],
    ])->merge(
        collect(config('mudalaya.modules'))->map(
            fn (array $module, string $key): array => [
                'key' => $key,
                'label' => $module['label'],
                'route' => $module['route'],
                'icon' => $key,
                'roles' => $module['roles'],
            ]
        )
    )->filter(fn (array $item): bool => in_array($role, $item['roles'], true));

    if ($role === 'admin') {
        $menuItems->push([
            'key' => 'users',
            'label' => 'Kelola Akun',
            'route' => 'users.index',
            'icon' => 'users',
            'roles' => ['admin'],
        ]);
    }
@endphp

<aside id="main-sidebar" class="hidden md:flex w-[248px] shrink-0 flex-col bg-forest px-4 py-6 text-white transition-[width,padding] duration-200">
    <script>
        (function(){
            try {
                if (localStorage.getItem('mudalaya_sidebar_state') === 'collapsed') {
                    document.currentScript.parentElement.setAttribute('data-collapsed', 'true');
                }
            } catch(e) {}
        })();
    </script>

    <div class="border-b border-forest-line pb-5">
        {{-- Header Terbuka (lebar 248px) --}}
        <div class="sidebar-expanded-content flex items-center justify-between gap-2">
            <div class="min-w-0 flex-1 overflow-hidden">
                <x-brand-identity subtitle="Sistem RFI" />
            </div>
            <button type="button"
                    id="sidebar-toggle-close"
                    class="sidebar-toggle-btn flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-forest-line bg-forest-hover/60 text-forest-muted transition hover:bg-forest-hover hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand"
                    aria-label="Tutup sidebar"
                    title="Tutup sidebar"
                    aria-expanded="true">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect width="18" height="18" x="3" y="3" rx="4" />
                    <path d="M9 3v18" />
                    <path d="m15 10-2 2 2 2" />
                </svg>
            </button>
        </div>

        {{-- Header Tertutup (lebar 76px, rail ikon) --}}
        <div class="sidebar-collapsed-content hidden flex-col items-center justify-center gap-3">
            <div class="flex items-center justify-center -space-x-1.5" title="PT. Mudalaya Energy Indonesia / PT. Mutiara Global Industry">
                <span class="relative z-10 flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-white ring-2 ring-forest" style="width: 28px; height: 28px; min-width: 28px; min-height: 28px; max-width: 28px; max-height: 28px; padding: 2px;">
                    <img src="{{ asset('images/mei.png') }}" alt="Logo MEI" style="width: 100%; height: 100%; max-width: 100%; max-height: 100%; object-fit: contain; display: block;">
                </span>
                <span class="relative z-0 flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-white ring-2 ring-forest" style="width: 28px; height: 28px; min-width: 28px; min-height: 28px; max-width: 28px; max-height: 28px; padding: 2px;">
                    <img src="{{ asset('images/mgi.png') }}" alt="Logo MGI" style="width: 100%; height: 100%; max-width: 100%; max-height: 100%; object-fit: contain; display: block;">
                </span>
            </div>
            <button type="button"
                    id="sidebar-toggle-open"
                    class="sidebar-toggle-btn group relative flex items-center justify-center text-white transition hover:bg-forest-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand"
                    style="width: 48px; height: 48px; border-radius: 14px; background-color: #233b29;"
                    aria-label="Buka sidebar"
                    title="Buka sidebar"
                    aria-expanded="false">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="3" width="18" height="18" rx="4" />
                    <path d="M9 3v18" />
                    <path d="m14 10 2 2-2 2" />
                </svg>
                <span class="sidebar-tooltip">
                    Buka sidebar
                </span>
            </button>
        </div>
    </div>

    <nav aria-label="Navigasi utama" class="flex-1 space-y-1.5 py-4">
        @foreach ($menuItems as $item)
            <a href="{{ route($item['route']) }}"
                @if ($active === $item['key']) aria-current="page" @endif
                class="sidebar-nav-item group relative flex min-h-[44px] items-center gap-3 rounded-[10px] px-[14px] py-2.5 text-[14px] transition {{ $active === $item['key'] ? 'bg-paper font-semibold text-ink' : 'font-medium text-forest-text hover:bg-forest-hover' }}">
                <x-module-menu-icon :name="$item['icon']" />
                <span class="sidebar-nav-label truncate">{{ $item['label'] }}</span>
                <span class="sidebar-tooltip">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="border-t border-forest-line pt-4">
        {{-- Footer Terbuka (lebar 248px) --}}
        <div class="sidebar-expanded-content flex-col">
            <div class="flex items-center gap-3">
                <div aria-hidden="true" class="flex h-10 w-10 min-w-10 shrink-0 aspect-square items-center justify-center rounded-full bg-paper text-[14px] font-bold leading-none whitespace-nowrap text-forest">
                    {{ $userInitials }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[14px] font-medium text-white">{{ $user->name }}</p>
                    <span class="mt-1 inline-flex rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold text-[#26501F]">
                        {{ $roleLabel }}
                    </span>
                </div>
            </div>

            <div class="mt-3 flex flex-col gap-1">
                <a href="{{ route('password.edit') }}" class="flex min-h-[36px] items-center gap-2 rounded-lg px-2 text-left text-[13px] text-forest-muted transition hover:bg-forest-hover hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <span>Ganti Password</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex min-h-[36px] w-full items-center gap-2 rounded-lg px-2 text-left text-[13px] text-forest-muted transition hover:bg-forest-hover hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </div>

        {{-- Footer Tertutup (lebar 76px, rail ikon) --}}
        <div class="sidebar-collapsed-content hidden flex-col items-center gap-3">
            <div class="group relative flex items-center justify-center" tabindex="0" aria-label="{{ $user->name }} ({{ $roleLabel }})">
                <div aria-hidden="true" class="flex h-10 w-10 min-w-10 shrink-0 aspect-square items-center justify-center rounded-full bg-paper text-[14px] font-bold leading-none whitespace-nowrap text-forest">
                    {{ $userInitials }}
                </div>
                <span class="sidebar-tooltip">
                    {{ $user->name }} ({{ $roleLabel }})
                </span>
            </div>

            <div class="flex flex-col items-center gap-2 w-full">
                <a href="{{ route('password.edit') }}"
                   class="group relative flex h-10 w-10 items-center justify-center rounded-lg text-forest-muted transition hover:bg-forest-hover hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand"
                   aria-label="Ganti Password"
                   title="Ganti Password">
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <span class="sidebar-tooltip">
                        Ganti Password
                    </span>
                </a>

                <form method="POST" action="{{ route('logout') }}" class="w-full flex justify-center">
                    @csrf
                    <button type="submit"
                            class="group relative flex h-10 w-10 items-center justify-center rounded-lg text-forest-muted transition hover:bg-forest-hover hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand"
                            aria-label="Logout"
                            title="Logout">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                        <span class="sidebar-tooltip">
                            Logout
                        </span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</aside>

<style>
    #main-sidebar {
        overflow: visible !important;
    }
    #main-sidebar nav {
        overflow: visible !important;
    }
    #main-sidebar[data-collapsed="true"] {
        width: 76px !important;
        padding-left: 12px !important;
        padding-right: 12px !important;
    }
    #main-sidebar[data-collapsed="true"] .sidebar-expanded-content {
        display: none !important;
    }
    #main-sidebar[data-collapsed="true"] .sidebar-collapsed-content {
        display: flex !important;
    }
    #main-sidebar[data-collapsed="true"] nav a.sidebar-nav-item {
        justify-content: center !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        width: 44px !important;
        margin-left: auto !important;
        margin-right: auto !important;
        gap: 0 !important;
        position: relative !important;
    }
    #main-sidebar[data-collapsed="true"] nav a.sidebar-nav-item .sidebar-nav-label {
        display: none !important;
    }

    /* Tooltip Default: Sembunyi */
    .sidebar-tooltip {
        display: none !important;
    }

    /* Saat Collapsed: Tooltip Muncul Saat Hover atau Fokus */
    #main-sidebar[data-collapsed="true"] nav a.sidebar-nav-item .sidebar-tooltip,
    #main-sidebar[data-collapsed="true"] .sidebar-collapsed-content .group .sidebar-tooltip {
        display: none !important;
        position: absolute;
        left: calc(100% + 12px);
        top: 50%;
        transform: translateY(-50%);
        background-color: #17291B;
        color: #FFFFFF;
        font-size: 13px;
        font-weight: 600;
        white-space: nowrap;
        padding: 8px 14px;
        border-radius: 8px;
        border: 1px solid #2C4A33;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4), 0 8px 10px -6px rgba(0, 0, 0, 0.4);
        z-index: 99999;
        pointer-events: none;
    }

    #main-sidebar[data-collapsed="true"] nav a.sidebar-nav-item .sidebar-tooltip::before,
    #main-sidebar[data-collapsed="true"] .sidebar-collapsed-content .group .sidebar-tooltip::before {
        content: "";
        position: absolute;
        right: 100%;
        top: 50%;
        transform: translateY(-50%);
        border-width: 6px;
        border-style: solid;
        border-color: transparent #17291B transparent transparent;
    }

    #main-sidebar[data-collapsed="true"] nav a.sidebar-nav-item:hover .sidebar-tooltip,
    #main-sidebar[data-collapsed="true"] nav a.sidebar-nav-item:focus-visible .sidebar-tooltip,
    #main-sidebar[data-collapsed="true"] .sidebar-collapsed-content .group:hover .sidebar-tooltip,
    #main-sidebar[data-collapsed="true"] .sidebar-collapsed-content .group:focus-visible .sidebar-tooltip {
        display: block !important;
        animation: sidebar-tooltip-fade 0.15s ease-out;
    }

    @keyframes sidebar-tooltip-fade {
        from {
            opacity: 0;
            transform: translateY(-50%) translateX(-4px);
        }
        to {
            opacity: 1;
            transform: translateY(-50%) translateX(0);
        }
    }

    #main-sidebar:not([data-collapsed="true"]) .sidebar-collapsed-content {
        display: none !important;
    }
    #main-sidebar:not([data-collapsed="true"]) .sidebar-expanded-content {
        display: flex !important;
    }
</style>

<script>
    (function () {
        const STORAGE_KEY = 'mudalaya_sidebar_state';
        const sidebar = document.getElementById('main-sidebar');
        if (!sidebar) return;

        const updateButtons = (isCollapsed) => {
            const closeBtn = document.getElementById('sidebar-toggle-close');
            const openBtn = document.getElementById('sidebar-toggle-open');
            if (closeBtn) closeBtn.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
            if (openBtn) openBtn.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
        };

        const applyState = (isCollapsed) => {
            if (isCollapsed) {
                sidebar.setAttribute('data-collapsed', 'true');
            } else {
                sidebar.removeAttribute('data-collapsed');
            }
            updateButtons(isCollapsed);
        };

        // Restore state from localStorage
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved === 'collapsed') {
                applyState(true);
            }
        } catch (e) {}

        const toggleSidebar = () => {
            const currentlyCollapsed = sidebar.getAttribute('data-collapsed') === 'true';
            const nextState = !currentlyCollapsed;
            applyState(nextState);
            try {
                localStorage.setItem(STORAGE_KEY, nextState ? 'collapsed' : 'expanded');
            } catch (e) {}
        };

        document.querySelectorAll('.sidebar-toggle-btn').forEach((btn) => {
            btn.addEventListener('click', toggleSidebar);
        });
    })();
</script>

<nav aria-label="Navigasi mobile" class="fixed inset-x-0 bottom-0 z-40 flex items-center justify-around border-t border-line bg-white px-2 pt-2 pb-[max(12px,env(safe-area-inset-bottom))] shadow-[0_-4px_16px_rgba(0,0,0,0.04)] md:hidden">
    @foreach ($menuItems as $item)
        @php
            $isItemActive = $active === $item['key'];
        @endphp
        <a href="{{ route($item['route']) }}"
           @if ($isItemActive) aria-current="page" @endif
           class="flex min-h-[56px] flex-1 min-w-0 flex-col items-center justify-center gap-1 rounded-[12px] px-1 text-[11px] transition {{ $isItemActive ? 'bg-[#E9EFE5] font-semibold text-ink' : 'font-medium text-[#5B665E] hover:bg-paper-soft hover:text-ink' }}">
            <x-module-menu-icon :name="$item['icon']" class="h-[22px] w-[22px] shrink-0" />
            <span class="max-w-[70px] truncate text-center leading-tight">{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
