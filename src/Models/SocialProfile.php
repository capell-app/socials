<?php

declare(strict_types=1);

namespace Capell\Socials\Models;

use Capell\Core\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $site_id
 * @property string|null $network_key
 * @property string $profile_value
 * @property string|null $custom_label
 * @property int $sort_order
 * @property bool $is_enabled
 */
final class SocialProfile extends Model
{
    protected $table = 'social_profiles';

    /** @var list<string> */
    protected $fillable = [
        'site_id',
        'network_key',
        'profile_value',
        'custom_label',
        'sort_order',
        'is_enabled',
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
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
