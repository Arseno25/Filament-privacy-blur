<script>
    (function() {
        'use strict';

        // Global reveal state
        let isGlobalRevealed = false;

        // Listen for global toggle events
        window.addEventListener('toggle-privacy-blur', () => {
            isGlobalRevealed = !isGlobalRevealed;
            updateAllPrivacyElements();
        });

        function updateAllPrivacyElements() {
            document.querySelectorAll('[data-privacy-blur="true"]').forEach(el => {
                if (!el.hasAttribute('data-privacy-click')) {
                    updatePrivacyElement(el);
                }
            });
        }

        function updatePrivacyElement(el) {
            const shouldShow = isGlobalRevealed;
            if (shouldShow) {
                el.classList.remove('fi-text-transparent');
                el.setAttribute('data-is-revealed', 'true');
            } else {
                el.classList.add('fi-text-transparent');
                el.removeAttribute('data-is-revealed');
            }
        }

        // Handle click-to-reveal elements
        // Use capture phase to intercept clicks before Filament's table row handlers
        document.addEventListener('click', (e) => {
            const target = e.target.closest('[data-privacy-click]');
            if (!target) return;

            // Stop propagation to prevent triggering Filament's edit modal
            e.stopImmediatePropagation();
            e.stopPropagation();
            e.preventDefault();

            const isRevealed = target.getAttribute('data-is-revealed') === 'true';

            if (!isRevealed) {
                // Reveal
                target.classList.remove('fi-text-transparent');
                target.setAttribute('data-is-revealed', 'true');

                // Auto-hide after 5 seconds
                const timeoutId = setTimeout(() => {
                    target.classList.add('fi-text-transparent');
                    target.removeAttribute('data-is-revealed');
                }, 5000);

                // Store timeout ID to clear if clicked again
                target.dataset.privacyTimeout = timeoutId;

                // Audit logging
                if (target.dataset.privacyAudit === 'true') {
                    logReveal(target);
                }
            } else {
                // Manual re-blur
                target.classList.add('fi-text-transparent');
                target.removeAttribute('data-is-revealed');

                // Clear auto-hide timeout if exists
                if (target.dataset.privacyTimeout) {
                    clearTimeout(parseInt(target.dataset.privacyTimeout));
                    delete target.dataset.privacyTimeout;
                }
            }
        }, true); // Use capture phase

        function logReveal(el) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (!csrfToken) return;

            fetch('/filament-privacy-blur/audit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    column: el.dataset.privacyColumn || '',
                    record_id: el.dataset.privacyRecordId || '',
                    mode: 'blur_click'
                })
            }).catch(() => {
                // Silently fail on audit errors
            });
        }

        // Initialize: hide all privacy-blur elements by default
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-privacy-blur="true"]').forEach(el => {
                if (!el.hasAttribute('data-privacy-hover')) {
                    el.classList.add('fi-text-transparent');
                }
            });
        });
    })();
</script>
