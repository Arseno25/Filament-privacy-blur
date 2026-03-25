<div class="px-2" x-data="{ isRevealed: false }" x-on:toggle-privacy-blur.window="isRevealed = !isRevealed">
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
