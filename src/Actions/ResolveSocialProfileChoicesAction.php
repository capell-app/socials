<?php

declare(strict_types=1);

namespace Capell\Socials\Actions;

use Capell\Admin\Support\SiteScope;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Capell\Socials\Contracts\SocialNetworkRegistry;
use Capell\Socials\Data\SocialProfileChoicesData;
use Capell\Socials\Data\SocialProfileConfigurationData;
use Capell\Socials\Models\SocialProfile;
use Capell\Socials\Support\SocialSiteId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/** @method static SocialProfileChoicesData run(?Model $record, ?Site $contextSite = null) */
final class ResolveSocialProfileChoicesAction
{
    use AsFake;
    use AsObject;

    public function __construct(private readonly SocialNetworkRegistry $networks) {}

    public function handle(?Model $record, ?Site $contextSite = null): SocialProfileChoicesData
    {
        if ($record instanceof Translation) {
            $record = $record->translatable;
        }

        $siteId = $record instanceof Site
            ? SocialSiteId::fromInput($record->getKey())
            : SocialSiteId::fromInput($record?->getAttribute('site_id') ?? $contextSite?->getKey());

        if ($siteId === null || ! Schema::hasTable('social_profiles')
            || ! SiteScope::applyForCurrentActor(Site::query(), 'id')->whereKey($siteId)->exists()) {
            return new SocialProfileChoicesData([]);
        }

        $options = [];
        foreach (SocialProfile::query()->where('site_id', $siteId)->where('is_enabled', true)->orderBy('sort_order')->orderBy('id')->get() as $profile) {
            $network = $profile->network_key === null ? null : $this->networks->get($profile->network_key);
            $public = ResolveSiteSocialProfilesAction::make()->resolveConfiguration(
                new SocialProfileConfigurationData(
                    networkKey: $profile->network_key,
                    profileValue: $profile->profile_value,
                    customLabel: $profile->custom_label,
                    isEnabled: true,
                ),
            );
            if ($public === null) {
                continue;
            }

            $options[$profile->id] = implode(' · ', array_filter([$network?->resolveLabel(), $public->label, $public->url]));
        }

        return new SocialProfileChoicesData($options);
    }
}
