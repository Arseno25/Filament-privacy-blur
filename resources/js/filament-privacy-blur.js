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

            fetch('/filament-privacy-blur/audit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    column: column,
                    record_id: recordId,
                    mode: 'blur_click'
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
