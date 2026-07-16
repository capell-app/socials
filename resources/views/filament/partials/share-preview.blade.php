@if ($renderData?->shouldRender())
    @include ('capell-socials::blocks.socials', compact('renderData'))
@else
    <p>{{ __('capell-socials::socials.admin.share_preview_unavailable') }}</p>
@endif
