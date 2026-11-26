<dialog
    id="logout-dialog"
    class="backdrop:bg-[#14201A]/45 backdrop:backdrop-blur-xs"
    aria-labelledby="logout-dialog-title"
    aria-describedby="logout-dialog-desc"
>
    <div class="flex flex-col items-center text-center w-[calc(100vw-2rem)] max-w-[380px] rounded-[24px] border border-line bg-white p-6 sm:p-7 shadow-2xl">
        {{-- Icon Log Out --}}
        <div aria-hidden="true" class="flex h-12 w-12 min-h-[48px] min-w-[48px] shrink-0 aspect-square items-center justify-center rounded-full bg-[#FCE8D8] text-[#8F3F08]">
            <svg class="h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
        </div>

        {{-- Judul & Deskripsi --}}
        <h3 id="logout-dialog-title" class="mt-4 text-[18px] font-semibold text-ink leading-snug">
            Keluar dari akun?
        </h3>
        <p id="logout-dialog-desc" class="mt-1.5 text-[14px] text-muted leading-relaxed">
            Anda akan keluar dari aplikasi.
        </p>

        {{-- Tombol Aksi --}}
        <div class="mt-6 flex w-full flex-col-reverse sm:flex-row items-center justify-center gap-3">
            <button
                type="button"
                id="logout-dialog-cancel"
                autofocus
                class="flex min-h-[48px] w-full sm:flex-1 items-center justify-center rounded-xl border border-line bg-paper px-5 text-[14px] font-semibold text-ink transition hover:bg-paper-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand cursor-pointer"
            >
                Batal
            </button>
            <button
                type="button"
                id="logout-dialog-confirm"
                class="flex min-h-[48px] w-full sm:flex-1 items-center justify-center rounded-xl border border-[#B4470F] bg-[#B4470F] px-5 text-[14px] font-semibold text-white transition hover:bg-[#8F3F08] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#B4470F] cursor-pointer"
            >
                Ya, Log Out
            </button>
        </div>
    </div>
</dialog>

<style>
    #logout-dialog {
        margin: auto;
        border: none;
        padding: 0;
        background: transparent;
        max-height: 100vh;
        overflow: visible;
    }
    #logout-dialog:not([open]) {
        display: none !important;
    }
    #logout-dialog::backdrop {
        background-color: rgba(20, 32, 26, 0.45);
        backdrop-filter: blur(2px);
    }
</style>

<script>
    (function () {
        const dialog = document.getElementById('logout-dialog');
        const cancelBtn = document.getElementById('logout-dialog-cancel');
        const confirmBtn = document.getElementById('logout-dialog-confirm');
        const logoutUrl = @json(route('logout'));
        let pendingForm = null;

        if (!dialog) return;

        function openDialog(form) {
            pendingForm = form;
            // Tutup dropdown akun mobile jika sedang terbuka
            document.querySelectorAll('.mobile-account-details[open]').forEach(function (details) {
                details.removeAttribute('open');
            });
            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            } else {
                dialog.setAttribute('open', '');
            }
            if (cancelBtn) {
                cancelBtn.focus();
            }
        }

        function closeDialog() {
            if (typeof dialog.close === 'function') {
                dialog.close();
            } else {
                dialog.removeAttribute('open');
            }
            pendingForm = null;
        }

        // Tangani submit & click tombol form logout di sidebar desktop & menu mobile
        document.addEventListener('submit', function (e) {
            const form = e.target.closest('form');
            if (form && form.getAttribute('action') === logoutUrl) {
                e.preventDefault();
                openDialog(form);
            }
        });

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('form[action="' + logoutUrl + '"] button');
            if (btn) {
                const form = btn.closest('form');
                if (form) {
                    e.preventDefault();
                    openDialog(form);
                }
            }
        });

        if (cancelBtn) {
            cancelBtn.addEventListener('click', closeDialog);
        }

        if (confirmBtn) {
            confirmBtn.addEventListener('click', function () {
                if (pendingForm) {
                    pendingForm.submit();
                } else {
                    const fallbackForm = document.querySelector('form[action="' + logoutUrl + '"]');
                    if (fallbackForm) {
                        fallbackForm.submit();
                    }
                }
            });
        }

        // Klik di luar dialog (pada backdrop) untuk menutup dialog
        dialog.addEventListener('click', function (e) {
            const rect = dialog.getBoundingClientRect();
            const isInDialog = (
                rect.top <= e.clientY && e.clientY <= rect.top + rect.height &&
                rect.left <= e.clientX && e.clientX <= rect.left + rect.width
            );
            if (!isInDialog) {
                closeDialog();
            }
        });

        // Event close (misal saat ditekan tombol Esc)
        dialog.addEventListener('close', function () {
            pendingForm = null;
        });
    })();
</script>
