<?php

declare(strict_types=1);

namespace Capell\Socials\Models;

use Capell\Core\Models\Site;
use Capell\Socials\Enums\SocialLabelStyle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $site_id
 * @property SocialLabelStyle $follow_label_style
 * @property bool $follow_open_in_new_tab
 * @property list<string> $share_network_keys
 * @property SocialLabelStyle $share_label_style
 * @property bool $share_open_in_new_tab
 */
final class SocialSitePreferences extends Model
{
    protected $table = 'social_site_preferences';

    /** @var array<string, mixed> */
    protected $attributes = [
        'follow_label_style' => SocialLabelStyle::Icons->value,
        'follow_open_in_new_tab' => false,
        'share_network_keys' => '[]',
        'share_label_style' => SocialLabelStyle::Icons->value,
        'share_open_in_new_tab' => false,
    ];

    /** @var list<string> */
    protected $fillable = [
        'site_id',
        'follow_label_style',
        'follow_open_in_new_tab',
        'share_network_keys',
        'share_label_style',
        'share_open_in_new_tab',
    ];

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'follow_label_style' => SocialLabelStyle::class,
            'follow_open_in_new_tab' => 'boolean',
            'share_network_keys' => 'array',
            'share_label_style' => SocialLabelStyle::class,
            'share_open_in_new_tab' => 'boolean',
        ];
    }
}
