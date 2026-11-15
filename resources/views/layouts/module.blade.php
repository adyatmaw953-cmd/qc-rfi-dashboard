<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>document.documentElement.classList.add('js');</script>
    <title>{{ $pageTitle }} - PT Mudalaya</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper font-sans text-ink antialiased">
    <div class="min-h-screen md:flex">
        <x-module-sidebar :active="$active" />
        <main class="min-w-0 flex-1 px-4 py-5 pb-32 md:px-10 md:py-8">
            <header class="mb-8 flex items-center justify-between">
                <div>
                    @if (isset($backUrl))
                        <div class="mb-1">
                            <a href="{{ $backUrl }}" class="inline-flex min-h-[44px] items-center gap-1.5 text-[14px] font-semibold text-brand transition hover:text-brand-dark">
                                &larr; {{ $backLabel ?? 'Kembali' }}
                            </a>
                        </div>
                    @endif
                    @if (isset($pageCaption))
                        <p class="text-[13px] font-medium text-brand">{{ $pageCaption }}</p>
                    @elseif (($active ?? '') === 'dashboard')
                        <p class="text-[13px] font-medium text-brand">{{ \Carbon\Carbon::now('Asia/Jakarta')->locale('id')->isoFormat('dddd, D MMM Y') }}</p>
                    @else
                        <p class="text-[13px] font-medium text-brand">PT Mudalaya Indo</p>
                    @endif
                    <h1 class="mt-1 text-[30px] font-semibold leading-tight tracking-tight text-ink {{ $pageTitleClass ?? '' }}">{{ $pageTitle }}</h1>
                </div>
                <div class="flex items-center gap-3">
                    @auth
                        @php
                            $unreadCount = $unreadNotificationsCount ?? 0;
                            $badgeDisplay = $unreadCount > 9 ? '9+' : (string) $unreadCount;
                            $headerUser = Auth::user();
                            $headerRole = $headerUser->role;
                            $headerRoleLabel = match ($headerRole) {
                                'admin' => 'Super Admin',
                                'production' => 'Production',
                                'qc_qa' => 'QC / QA',
                                'warehouse' => 'Warehouse',
                                'ppic' => 'PPIC',
                                default => $headerRole,
                            };
                            $headerUserInitials = collect(preg_split('/\s+/', trim($headerUser->name)) ?: [])
                                ->filter()
                                ->take(2)
                                ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
                                ->implode('');
                            $headerUserInitials = $headerUserInitials !== '' ? $headerUserInitials : 'U';
                        @endphp
                        <a href="{{ route('notifications.index') }}"
                           class="relative flex h-[44px] w-[44px] shrink-0 items-center justify-center rounded-full border border-line bg-white text-ink transition hover:border-muted"
                           aria-label="Notifikasi, {{ $unreadCount }} belum dibaca">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
                                <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
                            </svg>
                            @if ($unreadCount > 0)
                                <span class="absolute -right-1 -top-1 flex h-[20px] min-w-[20px] items-center justify-center rounded-full border-2 border-paper bg-alert px-1 font-mono text-[11px] font-semibold leading-none text-white">
                                    {{ $badgeDisplay }}
                                </span>
                            @endif
                        </a>

                        {{-- Akun Mobile (di bawah md) dengan pop up pure CSS details/summary --}}
                        <details class="mobile-account-details relative md:hidden">
                            <summary class="relative z-50 flex h-[44px] w-[44px] cursor-pointer list-none items-center justify-center rounded-full bg-forest text-[13px] font-semibold text-white select-none transition hover:bg-forest-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand [&::-webkit-details-marker]:hidden"
                                     aria-label="Akun: {{ $headerUser->name }}">
                                {{ $headerUserInitials }}
                            </summary>
                            <div class="absolute right-0 top-full z-50 mt-2 w-[272px] rounded-[18px] border border-line bg-white p-[18px] shadow-xl">
                                <div class="flex items-center gap-3">
                                    <div aria-hidden="true" class="flex h-[48px] w-[48px] min-w-[48px] shrink-0 aspect-square items-center justify-center rounded-full bg-forest text-[15px] font-semibold text-white">
                                        {{ $headerUserInitials }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-[14px] font-semibold text-ink leading-tight">{{ $headerUser->name }}</p>
                                        <span class="mt-1 inline-flex rounded-full bg-forest-line px-2 py-0.5 text-[11px] font-semibold text-forest-text">
                                            {{ $headerRoleLabel }}
                                        </span>
                                    </div>
                                </div>
                                <form method="POST" action="{{ route('logout') }}" class="mt-4">
                                    @csrf
                                    <button type="submit" class="flex h-[48px] w-full items-center justify-center gap-2 rounded-[12px] border border-alert text-[14px] font-semibold text-chip-nc-fg transition hover:bg-chip-nc-bg focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
                                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                            <polyline points="16 17 21 12 16 7"></polyline>
                                            <line x1="21" y1="12" x2="9" y2="12"></line>
                                        </svg>
                                        <span>Log out</span>
                                    </button>
                                </form>
                            </div>
                        </details>
                    @endauth
                    @yield('header_action')
                </div>
            </header>

            <style>
                details.mobile-account-details[open] > summary::before {
                    content: "";
                    position: fixed;
                    inset: 0;
                    background: rgba(20, 32, 26, 0.38);
                    z-index: 40;
                    cursor: default;
                }
            </style>

            @if (session('success'))
                <div role="status" class="mb-6 rounded-2xl border border-chip-close-fg/20 bg-chip-close-bg px-4 py-3 text-sm font-medium text-chip-close-fg">
                    {{ session('success') }}
                </div>
            @endif
            @if ($errors->any())
                <div role="alert" class="mb-6 rounded-[12px] border border-[#F0C7A8] bg-[#FCE8D8] px-[18px] py-[14px] text-[14px] text-[#8F3F08]">
                    <p class="font-semibold text-[#8F3F08]">Periksa kembali isian berikut:</p>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>
