@php
    /** @var \Capell\Socials\Filament\Pages\SocialsPage $livewire */
    $renderData = $livewire->followPreview;
@endphp

@if ($renderData->shouldRender())
    @include ('capell-socials::blocks.socials', compact('renderData'))
@else
    <p>{{ __('capell-socials::socials.admin.preview_empty') }}</p>
@endif
