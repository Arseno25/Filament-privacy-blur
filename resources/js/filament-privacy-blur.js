export default function (Alpine) {
    Alpine.data('filamentPrivacyBlur', () => ({
        isRevealed: false,
        isGlobalRevealed: false,
        timeout: null,

        init() {
            // Listen for global reveal toggle events
            this.$el.addEventListener('toggle-privacy-blur', () => {
                this.isGlobalRevealed = !this.isGlobalRevealed;
            });
        },

        toggle() {
            this.isRevealed = !this.isRevealed;

            if (this.isRevealed) {
                clearTimeout(this.timeout);
                this.timeout = setTimeout(() => {
                    this.isRevealed = false;
                }, 5000);

                // Audit logging if enabled
                if (this.$el.dataset.privacyAudit === 'true') {
                    this.logReveal();
                }
            }
        },

        logReveal() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const column = this.$el.dataset.privacyColumn;
            const recordId = this.$el.dataset.privacyRecordId || '';
            const mode = this.$el.dataset.privacyMode || 'blur_click';
            const resource = this.$el.dataset.privacyResource || '';
            const panel = this.$el.dataset.privacyPanel || '';

            // Use a relative URL resolved at runtime — the audit URL is rendered
            // into the alpine-script.blade.php. This Alpine data component is a
            // fallback/alternative approach; the inline script handles most cases.
            const auditUrl = window.__privacyBlurAuditUrl || '/filament-privacy-blur/audit';

            fetch(auditUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    column: column,
                    record_id: recordId,
                    mode: mode,
                    resource: resource,
                    panel: panel,
                })
            }).catch(() => {
                // Silently fail if audit logging fails
            });
        },

        get isVisible() {
            return this.isRevealed || this.isGlobalRevealed;
        },

        get blurClass() {
            return this.isVisible ? '' : 'o-privacy-blur pb-' + (this.$el.dataset.privacyBlur || 4);
        }
    }));
}
