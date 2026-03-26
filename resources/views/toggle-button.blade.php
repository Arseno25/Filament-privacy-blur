<div class="px-2"
     x-data="privacyToggle()"
     x-init="initToggle()"
     style="display: none;"
     x-show="hasGloballyRevealableFields">
    <x-filament::icon-button
        color="gray"
        icon="heroicon-o-eye"
        tooltip="Reveal Privacy Blur"
        label="Reveal Privacy Blur"
        x-show="!isRevealed"
        x-on:click="$dispatch('toggle-privacy-blur')"
        class="fi-topbar-btn"
    />

    <x-filament::icon-button
        color="gray"
        icon="heroicon-o-eye-slash"
        tooltip="Hide Privacy Blur"
        label="Hide Privacy Blur"
        x-show="isRevealed"
        x-cloak
        x-on:click="$dispatch('toggle-privacy-blur')"
        class="fi-topbar-btn"
    />
</div>

<script>
function privacyToggle() {
    return {
        isRevealed: false,
        hasGloballyRevealableFields: false,

        initToggle() {
            // Check if there are any fields that can be globally revealed
            this.checkRevealableFields();

            // Listen for global toggle events
            window.addEventListener('toggle-privacy-blur', () => {
                this.isRevealed = !this.isRevealed;
            });

            // Re-check when DOM updates (for Livewire navigation)
            const observer = new MutationObserver(() => {
                this.checkRevealableFields();
            });
            observer.observe(document.body, { childList: true, subtree: true });
        },

        checkRevealableFields() {
            // Check if any privacy-enabled element can be globally revealed
            const revealableElements = document.querySelectorAll('[data-privacy-can-globally-reveal="true"]');
            this.hasGloballyRevealableFields = revealableElements.length > 0;
        }
    }
}
</script>
