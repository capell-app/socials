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
