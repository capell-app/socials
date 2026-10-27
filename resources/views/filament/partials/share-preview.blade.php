<div data-capell-socials-admin-preview>
    @if ($renderData?->shouldRender())
        @include('capell-socials::blocks.socials', compact('renderData'))
    @elseif ($renderData !== null)
        <p data-capell-socials-share-empty>{{ __('capell-socials::socials.admin.share_preview_empty') }}</p>
    @else
        <p data-capell-socials-share-setup>{{ __('capell-socials::socials.admin.share_preview_unavailable') }}</p>
    @endif
</div>
