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

                            $roleChipStyles = [
                                'admin' => ['bg' => 'bg-[#E3EEFB]', 'fg' => 'text-[#1B4C8C]'],
                                'qc_qa' => ['bg' => 'bg-[#E1F0E2]', 'fg' => 'text-[#1F5B2A]'],
                                'production' => ['bg' => 'bg-[#FCE8D8]', 'fg' => 'text-[#8F3F08]'],
                                'warehouse' => ['bg' => 'bg-[#FBF0C9]', 'fg' => 'text-[#6B4E00]'],
                                'ppic' => ['bg' => 'bg-[#F3E8FF]', 'fg' => 'text-[#6B21A8]'],
                            ];
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
                            <div class="absolute right-0 top-full z-50 mt-2 w-[276px] rounded-[18px] border border-line bg-white p-[18px] shadow-2xl">
                                <div class="flex items-center gap-3 mb-3.5">
                                    <div aria-hidden="true" class="flex h-[48px] w-[48px] min-w-[48px] shrink-0 aspect-square items-center justify-center rounded-full bg-forest text-[15px] font-semibold text-white">
                                        {{ $headerUserInitials }}
                                    </div>
                                    <div class="min-w-0 flex-1 leading-snug">
                                        <p class="truncate text-[15px] font-semibold text-ink leading-tight">{{ $headerUser->name }}</p>
                                        <span class="mt-1 inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $roleChipStyles[$headerRole]['bg'] ?? 'bg-[#E3EEFB]' }} {{ $roleChipStyles[$headerRole]['fg'] ?? 'text-[#1B4C8C]' }}">
                                            {{ $headerRoleLabel }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-2">
                                    @if ($headerRole === 'admin')
                                        <a href="{{ route('users.index') }}"
                                           role="menuitem"
                                           class="flex items-center gap-3 min-h-[48px] px-3.5 rounded-[12px] bg-[#E9EFE5] text-[#14201A] text-[15px] font-semibold transition hover:bg-[#dbe7d5]">
                                            <span class="text-brand">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                                    <circle cx="9" cy="7" r="4"></circle>
                                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                                </svg>
                                            </span>
                                            <span>Kelola Akun</span>
                                        </a>
                                    @endif

                                    <a href="{{ route('password.edit') }}"
                                       class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[12px] border border-line bg-paper-soft text-[13px] font-semibold text-ink transition hover:bg-line focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
                                        <svg class="h-4 w-4 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                        </svg>
                                        <span>Ganti Password</span>
                                    </a>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="flex min-h-[48px] w-full items-center justify-center gap-2 rounded-[12px] border border-[#B4470F] text-[15px] font-semibold text-[#8F3F08] bg-white transition hover:bg-[#FCE8D8]/50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
                                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3"></path>
                                                <path d="M16 17l5-5-5-5M21 12H9"></path>
                                            </svg>
                                            <span>Log out</span>
                                        </button>
                                    </form>
                                </div>
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
