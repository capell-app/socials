<div data-capell-socials-admin-preview>
    @if ($renderData->shouldRender())
        @include ('capell-socials::blocks.socials', compact('renderData'))
    @else
        <p>{{ __('capell-socials::socials.admin.preview_empty') }}</p>
    @endif
</div>
