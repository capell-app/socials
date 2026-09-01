<x-filament-panels::page>
    <style>
        [data-capell-socials-admin-preview] .capell-socials ul {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        [data-capell-socials-admin-preview] .capell-socials a {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            color: light-dark(#176b55, #7dd3b8);
            font-weight: 600;
            text-decoration: none;
            border: 1px solid light-dark(#b8d9cf, #286452);
            border-radius: 9999px;
        }

        [data-capell-socials-admin-preview] .capell-socials a:hover {
            background: light-dark(#eff8f4, #173d33);
        }
    </style>

    @if ($showSiteSwitchPrompt)
        <div
            role="alertdialog"
            aria-labelledby="capell-socials-switch-heading"
            aria-describedby="capell-socials-switch-body"
            class="fi-section rounded-xl bg-amber-50 p-4 ring-1 ring-amber-500/30 dark:bg-amber-500/10 dark:ring-amber-400/30"
        >
            <h2
                id="capell-socials-switch-heading"
                class="text-base font-semibold text-amber-900 dark:text-amber-200"
            >
                {{ __('capell-socials::socials.admin.switch_site.heading', ['site' => $activeSiteName]) }}
            </h2>
            <p id="capell-socials-switch-body" class="mt-1 text-sm text-amber-800 dark:text-amber-200/80">
                {{ __('capell-socials::socials.admin.switch_site.body', ['site' => $activeSiteName]) }}
            </p>

            <div class="mt-4 flex flex-wrap gap-3">
                <x-filament::button
                    wire:click="saveAndSwitchSite"
                    wire:target="saveAndSwitchSite"
                    wire:loading.attr="disabled"
                >
                    {{ __('capell-socials::socials.admin.switch_site.save') }}
                </x-filament::button>
                <x-filament::button
                    color="gray"
                    wire:click="discardAndSwitchSite"
                >
                    {{ __('capell-socials::socials.admin.switch_site.discard') }}
                </x-filament::button>
                <x-filament::button
                    color="gray"
                    wire:click="stayOnCurrentSite"
                >
                    {{ __('capell-socials::socials.admin.switch_site.stay', ['site' => $activeSiteName]) }}
                </x-filament::button>
            </div>
        </div>
    @endif

    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex flex-wrap items-center gap-x-3 gap-y-2">
            <x-filament::button
                data-capell-socials-save
                type="submit"
                wire:target="save"
                wire:loading.attr="disabled"
                :color="$saveState === 'error' ? 'danger' : 'primary'"
            >
                <span
                    wire:loading.remove
                    wire:target="save"
                    >{{ __('capell-socials::socials.admin.save') }}</span
                >
                <span
                    wire:loading
                    wire:target="save"
                    >{{ __('capell-socials::socials.admin.save_states.saving') }}</span
                >
            </x-filament::button>

            <p
                data-capell-socials-save-status="{{ $saveState === 'error' ? 'error' : ($this->isDirty() ? 'dirty' : $saveState) }}"
                class="text-sm text-gray-500 dark:text-gray-400"
                role="status"
                aria-live="polite"
                wire:loading.remove
                wire:target="save"
            >
                @if ($saveState === 'error')
                    {{ __('capell-socials::socials.admin.save_states.error') }}
                @elseif ($this->isDirty())
                    {{ __('capell-socials::socials.admin.save_states.dirty') }}
                @elseif ($saveState === 'saved')
                    {{ __('capell-socials::socials.admin.save_states.saved') }}
                @endif
            </p>
        </div>
    </form>
</x-filament-panels::page>
