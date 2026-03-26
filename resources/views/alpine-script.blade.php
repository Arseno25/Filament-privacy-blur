<script>
    (function() {
        'use strict';

        // Audit URL from Laravel route — avoids hardcoding paths
        const auditUrl = @json(route('filament-privacy-blur.audit'));
        window.__privacyBlurAuditUrl = auditUrl;

        // Global reveal state
        let isGlobalRevealed = false;

        // Listen for global toggle events
        window.addEventListener('toggle-privacy-blur', () => {
            isGlobalRevealed = !isGlobalRevealed;
            updateAllPrivacyElements();
        });

        function updateAllPrivacyElements() {
            // Support both old and new attribute names for backward compatibility
            document.querySelectorAll('[data-privacy-enabled="true"], [data-privacy-blur="true"]').forEach(el => {
                // New explicit attributes (primary)
                const canRevealInteractively = el.getAttribute('data-privacy-can-reveal-interactively') === 'true';
                const canGloballyReveal = el.getAttribute('data-privacy-can-globally-reveal') === 'true';
                const neverReveal = el.getAttribute('data-privacy-never-reveal') === 'true';

                // Legacy attribute support (for backward compatibility)
                const legacyRevealAllowed = el.getAttribute('data-privacy-reveal-allowed') === 'true';

                // Use new explicit attributes if present, fall back to legacy
                const supportsGlobalReveal = canGloballyReveal !== null ? canGloballyReveal : legacyRevealAllowed;

                // Only reveal if ALL conditions are met
                const shouldReveal = isGlobalRevealed
                    && supportsGlobalReveal      // Server says global reveal is OK
                    && !neverReveal;             // Never-reveal flag is not set

                // Only toggle the inner spans with fi-privacy-blur class
                const spans = el.querySelectorAll('span.fi-privacy-blur');
                spans.forEach(span => {
                    if (shouldReveal) {
                        span.classList.remove('fi-text-transparent');
                        span.setAttribute('data-is-revealed', 'true');
                    } else {
                        span.classList.add('fi-text-transparent');
                        span.removeAttribute('data-is-revealed');
                    }
                });

                // Also handle the element itself if it has fi-privacy-blur class
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

        // Handle click-to-reveal elements
        // Use bubble phase to avoid intercepting events before Filament's handlers
        document.addEventListener('click', (e) => {
            // Find clickable privacy element — check both the wrapper and inner spans
            let target = e.target.closest('[data-privacy-click]');

            // Also check if we clicked on a span inside a wrapper
            if (!target) {
                const span = e.target.closest('span.fi-privacy-blur');
                if (span && span.closest('[data-privacy-click]')) {
                    target = span.closest('[data-privacy-click]');
                }
            }

            if (!target) return;

            // NEW: Check explicit authorization attributes
            const canRevealInteractively = target.getAttribute('data-privacy-can-reveal-interactively') === 'true';
            const neverReveal = target.getAttribute('data-privacy-never-reveal') === 'true';

            // Only allow reveal if explicitly permitted and neverReveal is not set
            if (!canRevealInteractively || neverReveal) {
                // Don't allow reveal - unauthorized or blocked
                return;
            }

            // Only prevent default to avoid following links, but don't stop propagation
            // to preserve Filament's table row clicks, actions, links, and modals
            e.preventDefault();

            // Find the inner span that actually has the blur classes
            const blurSpan = target.querySelector('span.fi-privacy-blur') || target;
            const isRevealed = blurSpan.getAttribute('data-is-revealed') === 'true';

            if (!isRevealed) {
                // Reveal
                blurSpan.classList.remove('fi-text-transparent');
                blurSpan.setAttribute('data-is-revealed', 'true');

                // Auto-hide after 5 seconds
                const timeoutId = setTimeout(() => {
                    blurSpan.classList.add('fi-text-transparent');
                    blurSpan.removeAttribute('data-is-revealed');
                }, 5000);

                // Store timeout ID to clear if clicked again
                blurSpan.dataset.privacyTimeout = timeoutId;

                // Audit logging
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
        }); // Bubble phase — does NOT block other handlers

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
        // The blur state is already rendered server-side via formatStateUsing in ColumnPrivacyMacros.
        // The fi-text-transparent class is included in the HTML spans from PHP.
        // This avoids conflicts with Livewire/wire:navigate SPA navigation.
    })();
</script>
