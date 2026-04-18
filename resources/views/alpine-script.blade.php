<script>
    (function() {
        'use strict';

        const auditUrl = @json(route('filament-privacy-blur.audit'));
        window.__privacyBlurAuditUrl = auditUrl;

        const AUDIT_DEBOUNCE_MS = 2000;
        const auditQueue = [];
        let auditFlushTimer = null;

        let isGlobalRevealed = false;

        window.addEventListener('toggle-privacy-blur', () => {
            isGlobalRevealed = !isGlobalRevealed;
            updateAllPrivacyElements();
        });

        window.addEventListener('beforeunload', flushAuditQueue);

        /**
         * Update all privacy elements based on global reveal state.
         * Only reveals elements where the server has explicitly allowed global reveal.
         */
        function updateAllPrivacyElements() {
            document.querySelectorAll('[data-privacy-enabled="true"]').forEach(el => {
                const canGloballyReveal = el.getAttribute('data-privacy-can-globally-reveal') === 'true';
                const neverReveal = el.getAttribute('data-privacy-never-reveal') === 'true';

                const shouldReveal = isGlobalRevealed
                    && canGloballyReveal
                    && !neverReveal;

                const blurSpan = el.querySelector('span.fi-privacy-blur');
                if (blurSpan) {
                    toggleBlurState(blurSpan, shouldReveal);
                }

                if (el.classList.contains('fi-privacy-blur')) {
                    toggleBlurState(el, shouldReveal);
                }
            });
        }

        function toggleBlurState(el, shouldReveal) {
            if (shouldReveal) {
                el.classList.remove('fi-text-transparent');
                el.setAttribute('data-is-revealed', 'true');
            } else {
                el.classList.add('fi-text-transparent');
                el.removeAttribute('data-is-revealed');
            }
        }

        /**
         * Handle click-to-reveal elements.
         * Only allows reveal if server has explicitly permitted interactive reveal.
         */
        document.addEventListener('click', (e) => {
            let target = e.target.closest('[data-privacy-click]');

            if (!target) {
                const span = e.target.closest('span.fi-privacy-blur');
                if (span && span.closest('[data-privacy-click]')) {
                    target = span.closest('[data-privacy-click]');
                }
            }

            if (!target) return;

            const canRevealInteractively = target.getAttribute('data-privacy-can-reveal-interactively') === 'true';
            const neverReveal = target.getAttribute('data-privacy-never-reveal') === 'true';

            if (!canRevealInteractively || neverReveal) {
                return;
            }

            // Preserve Filament's row clicks, actions, and modals — no stopPropagation
            e.preventDefault();

            const blurSpan = target.querySelector('span.fi-privacy-blur') || target;
            const isRevealed = blurSpan.getAttribute('data-is-revealed') === 'true';

            if (!isRevealed) {
                blurSpan.classList.remove('fi-text-transparent');
                blurSpan.setAttribute('data-is-revealed', 'true');

                const timeoutId = setTimeout(() => {
                    blurSpan.classList.add('fi-text-transparent');
                    blurSpan.removeAttribute('data-is-revealed');
                }, 5000);

                blurSpan.dataset.privacyTimeout = timeoutId;

                if (target.dataset.privacyAudit === 'true') {
                    queueAudit(target);
                }
            } else {
                blurSpan.classList.add('fi-text-transparent');
                blurSpan.removeAttribute('data-is-revealed');

                if (blurSpan.dataset.privacyTimeout) {
                    clearTimeout(parseInt(blurSpan.dataset.privacyTimeout));
                    delete blurSpan.dataset.privacyTimeout;
                }
            }
        });

        /**
         * Queue a reveal event for batched audit logging.
         * Flushes after AUDIT_DEBOUNCE_MS of inactivity to reduce HTTP overhead.
         */
        function queueAudit(el) {
            const payload = {
                column: el.dataset.privacyColumn || '',
                record_id: el.dataset.privacyRecordId || '',
                mode: el.dataset.privacyMode || 'blur_click',
                resource: el.dataset.privacyResource || '',
                panel: el.dataset.privacyPanel || '',
            };

            const tenantId = el.dataset.privacyTenantId;
            if (tenantId) {
                payload.tenant_id = tenantId;
            }

            auditQueue.push(payload);
            clearTimeout(auditFlushTimer);
            auditFlushTimer = setTimeout(flushAuditQueue, AUDIT_DEBOUNCE_MS);
        }

        function flushAuditQueue() {
            if (auditQueue.length === 0) return;

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (!csrfToken) return;

            const batch = auditQueue.splice(0, auditQueue.length);

            fetch(auditUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ batch: batch }),
                keepalive: true,
            }).catch(() => {
                // Silently fail on audit errors
            });
        }
    })();
</script>
