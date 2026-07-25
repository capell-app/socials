@php
    /**
     * Allow-listed inline icons for the public Socials widget. Icon keys come
     * from the trusted network registry; anything unknown falls back to the
     * generic link glyph so registry extensions can never inject markup.
     *
     * @var string $icon
     */
    $strokePaths = match ($icon) {
        'x-mark' => ['M5 4l14 16', 'M19 4L5 20'],
        'facebook' => ['M15 4h-2a3 3 0 0 0-3 3v13', 'M8 11h6'],
        'instagram' => ['M8 3h8a5 5 0 0 1 5 5v8a5 5 0 0 1-5 5H8a5 5 0 0 1-5-5V8a5 5 0 0 1 5-5z', 'M12 8.5a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7z', 'M17.2 6.8h.01'],
        'linkedin' => ['M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z', 'M8 11v5', 'M8 8v.01', 'M12 16v-5', 'M16 16v-3a2 2 0 0 0-4 0'],
        'youtube' => ['M6 6h12a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V9a3 3 0 0 1 3-3z', 'M10 9.5l5 2.5-5 2.5z'],
        'tiktok' => ['M14 5v9a3.5 3.5 0 1 1-3.5-3.5', 'M14 5a5 5 0 0 0 5 5'],
        'pinterest' => ['M8 20l4-9', 'M10.7 14c.4 1.3 1.4 2 2.6 2 2 0 3.7-1.6 3.7-4a5 5 0 1 0-9.7 1.7'],
        'whatsapp' => ['M3 21l1.7-3.8a9 9 0 1 1 3.4 2.9L3 21', 'M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0-.5.5'],
        'bluesky' => ['M12 11c-1.5-3-4.5-6.5-7-7-.9-.2-1.5.3-1.5 1.5 0 2 1 6 3.5 7-1.8.3-2.5 1.5-1.5 3 1 1.5 3.5 2 6.5-1.5 3 3.5 5.5 3 6.5 1.5 1-1.5.3-2.7-1.5-3 2.5-1 3.5-5 3.5-7 0-1.2-.6-1.7-1.5-1.5-2.5.5-5.5 4-7 7z'],
        'mastodon' => ['M7 16v-6a2.5 2.5 0 0 1 5 0v3', 'M12 10a2.5 2.5 0 0 1 5 0v6', 'M19.5 14.5c1-1 1.5-4.5 1.5-6.5 0-3-2.2-5-5-5H8C5.2 3 3 5 3 8c0 4.5.5 8 4 9 2 .6 5 .6 7 .3'],
        'threads' => ['M12 21c-5 0-8-3-8-9s3-9 8-9 8 3 8 8c0 3-1.5 5-4 5-1.7 0-3-1.3-3-3a3 3 0 1 1 3 3'],
        default => ['M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71', 'M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71'],
    };
    $filledPaths = match ($icon) {
        'youtube' => ['M10 9.5l5 2.5-5 2.5z'],
        default => [],
    };
@endphp
<svg
    class="capell-socials__glyph"
    width="20"
    height="20"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
>
    @foreach ($strokePaths as $strokePath)
        <path
            d="{{ $strokePath }}"
            @if (in_array($strokePath, $filledPaths, true)) fill="currentColor" @endif
        />
    @endforeach
</svg>
