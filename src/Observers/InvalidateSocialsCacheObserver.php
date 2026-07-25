<?php

declare(strict_types=1);

namespace Capell\Socials\Observers;

use Capell\Core\Events\FrontendSurrogateKeysInvalidated;
use Capell\Socials\Models\SocialProfile;
use Capell\Socials\Models\SocialSitePreferences;
use Capell\Socials\Support\SocialsCacheEpoch;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Keeps the manifest's model-event invalidation contract honest: any create,
 * update, or delete on the Socials models rotates the site cache epoch, even
 * when the write bypasses the package Actions.
 */
final readonly class InvalidateSocialsCacheObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private SocialsCacheEpoch $cacheEpoch) {}

    public function created(SocialProfile|SocialSitePreferences $model): void
    {
        $this->invalidate($model);
    }

    public function updated(SocialProfile|SocialSitePreferences $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(SocialProfile|SocialSitePreferences $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(SocialProfile|SocialSitePreferences $model): void
    {
        $siteId = $model->site_id;

        $this->cacheEpoch->increment($siteId);

        event(new FrontendSurrogateKeysInvalidated(['site-' . $siteId]));
    }
}
