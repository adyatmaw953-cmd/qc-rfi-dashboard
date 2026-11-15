document.addEventListener('DOMContentLoaded', () => {
    // NCR filter panel toggle
    const filterToggle = document.getElementById('ncr-filter-toggle');
    const filterPanel = document.getElementById('ncr-filter-panel');
    if (filterToggle && filterPanel) {
        filterToggle.addEventListener('click', () => {
            const isOpen = filterPanel.style.display !== 'none';
            filterPanel.style.display = isOpen ? 'none' : 'block';
            filterToggle.setAttribute('aria-expanded', String(!isOpen));
        });
    }
    // Dropzone logic for evidence uploads
    document.querySelectorAll('.evidence-dropzone-wrapper').forEach((wrapper) => {
        const dropzone = wrapper.querySelector('.evidence-dropzone');
        const input = wrapper.querySelector('.evidence-file-input');
        const fileList = wrapper.querySelector('.evidence-file-list');

        if (!dropzone || !input || !fileList) return;

        const updateFileList = () => {
            fileList.innerHTML = '';
            const files = Array.from(input.files || []);

            files.forEach((file, index) => {
                const chip = document.createElement('div');
                chip.className = 'inline-flex items-center gap-2 rounded-lg border border-[#E1DED3] bg-[#FBFAF6] px-3 py-1.5 text-[12px] text-[#14201A]';

                const nameSpan = document.createElement('span');
                nameSpan.className = 'font-medium truncate max-w-[180px]';
                nameSpan.textContent = file.name;

                const sizeSpan = document.createElement('span');
                sizeSpan.className = 'font-mono text-[11px] text-[#5B665E]';
                const sizeKb = (file.size / 1024).toFixed(0);
                const sizeMb = (file.size / (1024 * 1024)).toFixed(1);
                sizeSpan.textContent = file.size > 1024 * 1024 ? `${sizeMb} MB` : `${sizeKb} KB`;

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'ml-1 text-[#8F3F08] hover:text-[#B4470F] font-bold text-[14px] leading-none';
                removeBtn.setAttribute('aria-label', `Hapus ${file.name}`);
                removeBtn.innerHTML = '&times;';
                removeBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const dt = new DataTransfer();
                    Array.from(input.files).forEach((f, i) => {
                        if (i !== index) dt.items.add(f);
                    });
                    input.files = dt.files;
                    updateFileList();
                });

                chip.appendChild(nameSpan);
                chip.appendChild(sizeSpan);
                chip.appendChild(removeBtn);
                fileList.appendChild(chip);
            });
        };

        input.addEventListener('change', updateFileList);

        ['dragenter', 'dragover'].forEach((eventName) => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('border-[#366F3A]', 'bg-[#FBFAF6]');
            });
        });

        ['dragleave', 'drop'].forEach((eventName) => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('border-[#366F3A]', 'bg-[#FBFAF6]');
            });
        });

        dropzone.addEventListener('drop', (e) => {
            if (e.dataTransfer && e.dataTransfer.files.length > 0) {
                input.files = e.dataTransfer.files;
                updateFileList();
            }
        });
    });

    // NCR Form Logic: Navigation, Completeness, Project/Internal toggle, Cost formatting, Double-submit prevention
    const ncrForm = document.getElementById('ncr-form');
    if (ncrForm) {
        const navLinks = document.querySelectorAll('.ncr-nav-link');

        const updateCompleteness = () => {
            // S1: Temuan
            const findingDate = document.getElementById('finding_date')?.value;
            const department = document.getElementById('department')?.value;
            const areaFinding = document.getElementById('area_finding')?.value;
            const level = ncrForm.querySelector('input[name="level"]:checked')?.value;
            const type = ncrForm.querySelector('input[name="type"]:checked')?.value;
            const projectCode = document.getElementById('project_code')?.value;
            const pic = document.getElementById('responsible_pic')?.value?.trim();
            const prob = document.getElementById('problem_description')?.value?.trim();
            const s1ProjectValid = type === 'Internal' || (type === 'Project' && !!projectCode);
            const s1Complete = !!(findingDate && department && areaFinding && level && type && s1ProjectValid && pic && prob);

            // S2: Akar Masalah
            const rootCat = document.getElementById('root_cause_category')?.value;
            const rootCause = document.getElementById('root_cause')?.value?.trim();
            const s2Complete = !!(rootCat && rootCause);

            // S3: Corrective Action (Opsional - lengkap bila ada isian)
            const ca = document.getElementById('corrective_action')?.value?.trim();
            const picCa = document.getElementById('pic_ca')?.value?.trim();
            const caDate = document.getElementById('ca_closed_date')?.value;
            const s3Complete = !!(ca || picCa || caDate);

            // S4: Preventive Action & Status
            const status = ncrForm.querySelector('input[name="status"]:checked')?.value;
            const pa = document.getElementById('preventive_action')?.value?.trim();
            const picPa = document.getElementById('pic_pa')?.value?.trim();
            const paDate = document.getElementById('pa_closed_date')?.value;
            const s4Complete = !!(status || pa || picPa || paDate);

            // S5: NC Cost
            const ncCost = document.getElementById('nc_cost')?.value;
            const s5Complete = ncCost !== undefined && ncCost !== '';

            const completionMap = {
                s1: s1Complete,
                s2: s2Complete,
                s3: s3Complete,
                s4: s4Complete,
                s5: s5Complete,
            };

            navLinks.forEach((link) => {
                const secId = link.getAttribute('data-nav-sec');
                const isComplete = completionMap[secId];
                const indicator = link.querySelector('.ncr-nav-indicator');
                if (!indicator) return;

                const secNum = link.getAttribute('data-sec-num');
                if (isComplete) {
                    indicator.className = 'ncr-nav-indicator flex h-[22px] w-[22px] shrink-0 items-center justify-center rounded-full bg-[#E9EFE5] text-[#1F5B2A]';
                    indicator.innerHTML = '<svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
                } else {
                    indicator.className = 'ncr-nav-indicator flex h-[22px] w-[22px] shrink-0 items-center justify-center rounded-full border-2 border-[#B9B5A6] font-mono text-[11px] font-semibold text-muted';
                    indicator.textContent = secNum;
                }
            });
        };

        ncrForm.addEventListener('input', updateCompleteness);
        ncrForm.addEventListener('change', updateCompleteness);
        updateCompleteness();

        // IntersectionObserver for active section highlight
        const sections = document.querySelectorAll('section[id^="s"]');
        if ('IntersectionObserver' in window && sections.length > 0) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        const id = entry.target.id;
                        navLinks.forEach((link) => {
                            const isTarget = link.getAttribute('data-nav-sec') === id;
                            link.classList.toggle('bg-[#F4F2EB]', isTarget);
                            link.classList.toggle('font-semibold', isTarget);
                            link.classList.toggle('text-ink', isTarget);
                            link.classList.toggle('text-muted', !isTarget);
                        });
                    }
                });
            }, { threshold: 0.25 });

            sections.forEach((s) => observer.observe(s));
        }

        // Project vs Internal type toggle
        const typeRadios = ncrForm.querySelectorAll('input[name="type"]');
        const projectWrapper = document.getElementById('project_code_wrapper');
        const projectSelect = document.getElementById('project_code');
        const customerHint = document.getElementById('customer_hint');
        const customerInput = document.getElementById('customer');

        const handleTypeChange = () => {
            const selectedType = ncrForm.querySelector('input[name="type"]:checked')?.value || 'Project';
            if (selectedType === 'Internal') {
                if (projectWrapper) projectWrapper.style.display = 'none';
                if (projectSelect) {
                    projectSelect.value = '';
                    projectSelect.required = false;
                }
                if (customerHint) customerHint.textContent = 'Tipe Internal tidak memakai project';
            } else {
                if (projectWrapper) projectWrapper.style.display = 'block';
                if (customerHint) customerHint.textContent = 'Terisi otomatis dari project, tetap bisa diubah';
            }
            updateCompleteness();
        };

        typeRadios.forEach((r) => r.addEventListener('change', handleTypeChange));

        if (projectSelect) {
            projectSelect.addEventListener('change', () => {
                const opt = projectSelect.options[projectSelect.selectedIndex];
                const cust = opt ? opt.getAttribute('data-customer') : '';
                if (cust && customerInput && !customerInput.value) {
                    customerInput.value = cust;
                }
                updateCompleteness();
            });
        }

        // NC Cost thousand separator
        const costInput = document.getElementById('nc_cost');
        if (costInput) {
            costInput.addEventListener('input', function() {
                let val = this.value.replace(/[^\d]/g, '');
                if (val) {
                    this.value = new Intl.NumberFormat('id-ID').format(parseInt(val, 10));
                } else {
                    this.value = '';
                }
            });
        }

        // Prevent double-submit
        ncrForm.addEventListener('submit', () => {
            const submitBtn = document.getElementById('ncr-submit-btn');
            if (submitBtn) {
                submitBtn.disabled = true;
            }
        });

        // Toggle deselect: track checked state before click (pointerdown), then on click unchecked if was checked
        let _radioWasChecked = false;
        ncrForm.addEventListener('pointerdown', (e) => {
            const input = e.target.closest('.choice-card')?.querySelector('input[type="radio"]')
                ?? (e.target.type === 'radio' ? e.target : null);
            _radioWasChecked = input ? input.checked : false;
        }, true);

        ncrForm.addEventListener('click', (e) => {
            const card = e.target.closest('.choice-card');
            if (!card) return;
            const input = card.querySelector('input[type="radio"]');
            if (!input) return;
            if (_radioWasChecked) {
                // Deselect: uncheck and fire change so all listeners update
                input.checked = false;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
            _radioWasChecked = false;
        });

        // Keyboard toggle: Space on focused+checked radio deselects it
        ncrForm.addEventListener('keydown', (e) => {
            if (e.key !== ' ') return;
            const input = e.target;
            if (input.type !== 'radio' || !input.closest('.choice-card')) return;
            if (input.checked) {
                e.preventDefault();
                input.checked = false;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        // Sync choice-card visual state on change (radio & checkbox-pill)
        const syncCardClasses = (radio, isChecked) => {
            const card = radio.closest('.choice-card') || radio.closest('label');
            if (!card) return;
            if (isChecked) {
                card.classList.remove('border', 'border-[#8C978B]', 'bg-white', 'font-medium');
                card.classList.add('border-2', 'border-brand', 'bg-[#E9EFE5]', 'font-semibold');
            } else {
                card.classList.remove('border-2', 'border-brand', 'bg-[#E9EFE5]', 'font-semibold');
                card.classList.add('border', 'border-[#8C978B]', 'bg-white', 'font-medium');
            }
        };

        ncrForm.addEventListener('change', (e) => {
            if (e.target && e.target.type === 'radio' && e.target.name) {
                const radios = ncrForm.querySelectorAll(`input[type="radio"][name="${e.target.name}"]`);
                radios.forEach((radio) => syncCardClasses(radio, radio.checked));
            }
            if (e.target && e.target.type === 'checkbox') {
                const card = e.target.closest('.checkbox-pill') || e.target.closest('label');
                if (card) {
                    if (e.target.checked) {
                        card.classList.remove('border', 'border-[#8C978B]', 'bg-white', 'font-medium');
                        card.classList.add('border-2', 'border-brand', 'bg-[#E9EFE5]', 'font-semibold');
                    } else {
                        card.classList.remove('border-2', 'border-brand', 'bg-[#E9EFE5]', 'font-semibold');
                        card.classList.add('border', 'border-[#8C978B]', 'bg-white', 'font-medium');
                    }
                }
            }
        });
    }

    // MDR Create & Show Form Logic: Bulk Status, Live Summary, Toggle Deselect, Double-Submit Prevention
    const mdrForm = document.getElementById('mdr-form');
    if (mdrForm) {
        // Bulk status buttons
        const bulkButtons = mdrForm.querySelectorAll('[data-bulk]');
        bulkButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const val = btn.getAttribute('data-bulk');
                const reportList = mdrForm.querySelector('[data-report-list]');
                if (!reportList || !val) return;
                const radios = reportList.querySelectorAll(`input[type="radio"][value="${val}"]`);
                radios.forEach((r) => {
                    r.checked = true;
                    r.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });
        });

        // Toggle deselect on radio buttons (click again / click 2x to deselect back to gray/neutral)
        let _mdrRadioWasChecked = false;
        mdrForm.addEventListener('pointerdown', (e) => {
            const group = e.target.closest('[role="radiogroup"]');
            if (!group) return;
            const input = e.target.type === 'radio'
                ? e.target
                : e.target.closest('label')?.querySelector('input[type="radio"]');
            _mdrRadioWasChecked = input ? input.checked : false;
        }, true);

        mdrForm.addEventListener('click', (e) => {
            const group = e.target.closest('[role="radiogroup"]');
            if (!group) return;
            const input = e.target.type === 'radio'
                ? e.target
                : e.target.closest('label')?.querySelector('input[type="radio"]');
            if (!input) return;
            if (_mdrRadioWasChecked) {
                // Deselect: uncheck and trigger change to reset to gray and update summary
                input.checked = false;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
            _mdrRadioWasChecked = false;
        });

        // Keyboard toggle: Space on focused+checked radio deselects it
        mdrForm.addEventListener('keydown', (e) => {
            if (e.key !== ' ') return;
            const input = e.target;
            if (input.type !== 'radio' || !input.closest('[role="radiogroup"]')) return;
            if (input.checked) {
                e.preventDefault();
                input.checked = false;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        // Summary bar live update
        const updateMdrSummary = () => {
            const reportList = mdrForm.querySelector('[data-report-list]');
            if (!reportList) return;
            const completedCount = reportList.querySelectorAll('input[type="radio"][value="close"]:checked, input[type="radio"][value="na"]:checked').length;
            const total = parseInt(mdrForm.getAttribute('data-total-reports') || '16', 10);

            const summaryText = mdrForm.querySelector('[data-mdr-summary-text]');
            if (summaryText) summaryText.textContent = `${completedCount} dari ${total} report selesai`;

            const frac = mdrForm.querySelector('[data-mdr-fraction]');
            if (frac) frac.textContent = `${completedCount} dari ${total}`;

            const progressBar = mdrForm.querySelector('[data-mdr-progress-bar]');
            if (progressBar) {
                const pct = total > 0 ? (completedCount / total) * 100 : 0;
                progressBar.style.width = `${pct}%`;
            }

            const chipGray = document.getElementById('mdr-summary-chip-gray');
            const chipOpen = document.getElementById('mdr-summary-chip-open');
            const chipClose = document.getElementById('mdr-summary-chip-close');
            if (chipGray && chipOpen && chipClose) {
                if (completedCount === total && total > 0) {
                    chipClose.classList.remove('hidden');
                    chipClose.classList.add('inline-flex');
                    chipOpen.classList.add('hidden');
                    chipOpen.classList.remove('inline-flex');
                    chipGray.classList.add('hidden');
                    chipGray.classList.remove('inline-flex');
                } else if (completedCount > 0) {
                    chipOpen.classList.remove('hidden');
                    chipOpen.classList.add('inline-flex');
                    chipClose.classList.add('hidden');
                    chipClose.classList.remove('inline-flex');
                    chipGray.classList.add('hidden');
                    chipGray.classList.remove('inline-flex');
                } else {
                    chipGray.classList.remove('hidden');
                    chipGray.classList.add('inline-flex');
                    chipOpen.classList.add('hidden');
                    chipOpen.classList.remove('inline-flex');
                    chipClose.classList.add('hidden');
                    chipClose.classList.remove('inline-flex');
                }
            }
        };

        mdrForm.addEventListener('change', updateMdrSummary);

        // Prevent double submit
        mdrForm.addEventListener('submit', () => {
            const submitBtn = document.getElementById('mdr-submit-btn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Menyimpan...';
            }
        });
    }

    // Count-up numbers and Chart entrance animation with IntersectionObserver
    const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const animateCountUp = (el) => {
        if (el.dataset.counted === 'true') return;
        el.dataset.counted = 'true';

        if (prefersReducedMotion) return;

        const rawTarget = el.getAttribute('data-count-to');
        if (rawTarget === null || isNaN(Number(rawTarget))) return;

        const targetVal = parseFloat(rawTarget);
        const decimals = parseInt(el.getAttribute('data-decimals') || '0', 10);
        const prefix = el.getAttribute('data-prefix') || '';
        const suffix = el.getAttribute('data-suffix') || '';
        const originalText = el.textContent;

        const duration = 900;
        const startTime = performance.now();
        const formatter = new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });

        const easeOutCubic = (t) => 1 - Math.pow(1 - t, 3);

        const update = (now) => {
            const elapsed = now - startTime;
            const progress = Math.min(1, elapsed / duration);
            const currentVal = targetVal * easeOutCubic(progress);

            if (progress < 1) {
                el.textContent = `${prefix}${formatter.format(currentVal)}${suffix}`;
                requestAnimationFrame(update);
            } else {
                el.textContent = originalText;
            }
        };

        el.textContent = `${prefix}${formatter.format(0)}${suffix}`;
        requestAnimationFrame(update);
    };

    const countUpElements = document.querySelectorAll('[data-count-to]');
    const chartElements = document.querySelectorAll('.chart-animate');

    if ('IntersectionObserver' in window) {
        const animationObserver = new IntersectionObserver((entries, obs) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const el = entry.target;
                    if (el.classList.contains('chart-animate')) {
                        el.classList.add('is-visible');
                    }
                    if (el.hasAttribute('data-count-to')) {
                        animateCountUp(el);
                    }
                    obs.unobserve(el);
                }
            });
        }, { threshold: 0.2 });

        chartElements.forEach((el) => animationObserver.observe(el));
        countUpElements.forEach((el) => animationObserver.observe(el));
    } else {
        chartElements.forEach((el) => el.classList.add('is-visible'));
    }
});
