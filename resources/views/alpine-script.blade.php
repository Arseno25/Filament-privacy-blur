<script>
    (function() {
        'use strict';

        // Audit URL from Laravel route
        const auditUrl = @json(route('filament-privacy-blur.audit'));
        window.__privacyBlurAuditUrl = auditUrl;

        // Global reveal state
        let isGlobalRevealed = false;

        // Listen for global toggle events
        window.addEventListener('toggle-privacy-blur', () => {
            isGlobalRevealed = !isGlobalRevealed;
            updateAllPrivacyElements();
        });

        /**
         * Update all privacy elements based on global reveal state.
         * Only reveals elements where the server has explicitly allowed global reveal.
         */
        function updateAllPrivacyElements() {
            // Select all privacy-enabled elements
            document.querySelectorAll('[data-privacy-enabled="true"]').forEach(el => {
                // Read server-rendered authorization attributes
                const canGloballyReveal = el.getAttribute('data-privacy-can-globally-reveal') === 'true';
                const neverReveal = el.getAttribute('data-privacy-never-reveal') === 'true';

                // Only reveal if ALL conditions are met:
                // 1. Global reveal is active
                // 2. Server explicitly allows global reveal for this field
                // 3. Never-reveal flag is not set
                const shouldReveal = isGlobalRevealed
                    && canGloballyReveal
                    && !neverReveal;

                // Toggle blur on the inner span
                const blurSpan = el.querySelector('span.fi-privacy-blur');
                if (blurSpan) {
                    if (shouldReveal) {
                        blurSpan.classList.remove('fi-text-transparent');
                        blurSpan.setAttribute('data-is-revealed', 'true');
                    } else {
                        blurSpan.classList.add('fi-text-transparent');
                        blurSpan.removeAttribute('data-is-revealed');
                    }
                }

                // Handle element itself if it has fi-privacy-blur class
                if (el.classList.contains('fi-privacy-blur')) {
                    if (shouldReveal) {
                        el.classList.remove('fi-text-transparent');
                        el.setAttribute('data-is-revealed', 'true');
                    } else {
                        el.classList.add('fi-text-transparent');
                        el.removeAttribute('data-is-revealed');
                    }
                }
            });
        }

        /**
         * Handle click-to-reveal elements.
         * Only allows reveal if server has explicitly permitted interactive reveal.
         */
        document.addEventListener('click', (e) => {
            // Find clickable privacy element
            let target = e.target.closest('[data-privacy-click]');

            // Check if clicked on a span inside a wrapper
            if (!target) {
                const span = e.target.closest('span.fi-privacy-blur');
                if (span && span.closest('[data-privacy-click]')) {
                    target = span.closest('[data-privacy-click]');
                }
            }

            if (!target) return;

            // Read server-rendered authorization
            const canRevealInteractively = target.getAttribute('data-privacy-can-reveal-interactively') === 'true';
            const neverReveal = target.getAttribute('data-privacy-never-reveal') === 'true';

            // Only allow reveal if explicitly permitted and neverReveal is not set
            if (!canRevealInteractively || neverReveal) {
                return;
            }

            // Prevent default to avoid link navigation, but don't stop propagation
            // to preserve Filament's table row clicks, actions, and modals
            e.preventDefault();

            // Find the inner span that has the blur classes
            const blurSpan = target.querySelector('span.fi-privacy-blur') || target;
            const isRevealed = blurSpan.getAttribute('data-is-revealed') === 'true';

            if (!isRevealed) {
                // Reveal the field
                blurSpan.classList.remove('fi-text-transparent');
                blurSpan.setAttribute('data-is-revealed', 'true');

                // Auto-hide after 5 seconds
                const timeoutId = setTimeout(() => {
                    blurSpan.classList.add('fi-text-transparent');
                    blurSpan.removeAttribute('data-is-revealed');
                }, 5000);

                // Store timeout ID to clear if clicked again
                blurSpan.dataset.privacyTimeout = timeoutId;

                // Audit logging if enabled for this field
                if (target.dataset.privacyAudit === 'true') {
                    logReveal(target);
                }
            } else {
                // Manual re-blur
                blurSpan.classList.add('fi-text-transparent');
                blurSpan.removeAttribute('data-is-revealed');

                // Clear auto-hide timeout if exists
                if (blurSpan.dataset.privacyTimeout) {
                    clearTimeout(parseInt(blurSpan.dataset.privacyTimeout));
                    delete blurSpan.dataset.privacyTimeout;
                }
            }
        });

        /**
         * Log reveal action to server for audit trail.
         */
        function logReveal(el) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (!csrfToken) return;

            const payload = {
                column: el.dataset.privacyColumn || '',
                record_id: el.dataset.privacyRecordId || '',
                mode: el.dataset.privacyMode || 'blur_click',
                resource: el.dataset.privacyResource || '',
                panel: el.dataset.privacyPanel || '',
            };

            // Include tenant_id if available (for multi-tenant apps)
            const tenantId = el.dataset.privacyTenantId;
            if (tenantId) {
                payload.tenant_id = tenantId;
            }

            fetch(auditUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            }).catch(() => {
                // Silently fail on audit errors
            });
        }

        // No DOMContentLoaded init needed!
        // The blur state is rendered server-side via formatStateUsing in ColumnPrivacyMacros.
        // This avoids conflicts with Livewire/wire:navigate SPA navigation.
    })();
</script>
