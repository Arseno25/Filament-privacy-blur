const auditQueue = []
let auditFlushTimer = null
const AUDIT_DEBOUNCE_MS = 2000

function flushAuditQueue() {
    if (auditQueue.length === 0) return

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    if (!csrfToken) return

    const auditUrl = window.__privacyBlurAuditUrl || '/filament-privacy-blur/audit'
    const batch = auditQueue.splice(0, auditQueue.length)

    fetch(auditUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({ batch }),
        keepalive: true,
    }).catch(() => {
        // Silently fail if audit logging fails
    })
}

function queueAudit(payload) {
    auditQueue.push(payload)
    clearTimeout(auditFlushTimer)
    auditFlushTimer = setTimeout(flushAuditQueue, AUDIT_DEBOUNCE_MS)
}

if (typeof window !== 'undefined') {
    window.addEventListener('beforeunload', flushAuditQueue)
}

export default function (Alpine) {
    Alpine.data('filamentPrivacyBlur', () => ({
        isRevealed: false,
        isGlobalRevealed: false,
        timeout: null,

        init() {
            this.$el.addEventListener('toggle-privacy-blur', () => {
                this.isGlobalRevealed = !this.isGlobalRevealed
            })
        },

        toggle() {
            this.isRevealed = !this.isRevealed

            if (this.isRevealed) {
                clearTimeout(this.timeout)
                this.timeout = setTimeout(() => {
                    this.isRevealed = false
                }, 5000)

                if (this.$el.dataset.privacyAudit === 'true') {
                    queueAudit({
                        column: this.$el.dataset.privacyColumn,
                        record_id: this.$el.dataset.privacyRecordId || '',
                        mode: this.$el.dataset.privacyMode || 'blur_click',
                        resource: this.$el.dataset.privacyResource || '',
                        panel: this.$el.dataset.privacyPanel || '',
                    })
                }
            }
        },

        get isVisible() {
            return this.isRevealed || this.isGlobalRevealed
        },

        get blurClass() {
            return this.isVisible ? '' : 'o-privacy-blur pb-' + (this.$el.dataset.privacyBlur || 4)
        },
    }))
}
