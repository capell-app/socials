@php
    /** @var \Capell\Socials\Data\SocialFollowRenderData|\Capell\Socials\Data\SocialShareRenderData $renderData */
    $isShare = $renderData instanceof \Capell\Socials\Data\SocialShareRenderData;
    $links = $isShare ? $renderData->links : $renderData->profiles;
@endphp

<section class="capell-socials capell-socials--{{ $renderData->alignment }}">
    @if ($renderData->heading)
        <h2>{{ $renderData->heading }}</h2>
    @endif

    <ul>
        @foreach ($links as $link)
            <li>
                <a
                    href="{{ $link->url }}"
                    aria-label="{{ $link->label }}"
                    rel="{{ $isShare ? 'nofollow noopener noreferrer' : ($link->networkKey === 'custom' ? 'noopener noreferrer' : 'me noopener noreferrer') }}"
                    @if ($renderData->openInNewTab) target="_blank" @endif
                >
                    @if ($renderData->labelStyle !== \Capell\Socials\Enums\SocialLabelStyle::Labels)
                        <span
                            class="capell-socials__icon"
                            aria-hidden="true"
                        >
                            @include ('capell-socials::blocks.icon', ['icon' => $link->icon])
                        </span>
                    @endif
                    @if ($renderData->labelStyle !== \Capell\Socials\Enums\SocialLabelStyle::Icons)
                        <span>{{ $link->label }}</span>
                    @endif
                    @if ($renderData->labelStyle === \Capell\Socials\Enums\SocialLabelStyle::Icons)
                        <span class="sr-only">{{ $link->label }}</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
</section>
