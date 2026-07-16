<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <x-filament::button
            class="mt-6"
            type="submit"
        >
            {{ __('capell-socials::socials.admin.save') }}
        </x-filament::button>
    </form>
</x-filament-panels::page>
