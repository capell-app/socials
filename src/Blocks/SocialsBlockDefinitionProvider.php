<?php

declare(strict_types=1);

namespace Capell\Socials\Blocks;

use Capell\BlockLibrary\Contracts\BlockDefinitionProvider;
use Capell\BlockLibrary\Data\BlockDefinitionData;
use Capell\BlockLibrary\Data\BlockSettingDefinitionData;

final class SocialsBlockDefinitionProvider implements BlockDefinitionProvider
{
    public function definitions(): iterable
    {
        yield new BlockDefinitionData(
            key: 'socials',
            label: 'capell-socials::socials.widget.label',
            description: 'capell-socials::socials.widget.description',
            category: 'content',
            view: 'capell-socials::blocks.socials',
            defaults: [
                'mode' => 'follow',
                'label_style' => 'inherit',
                'link_target' => 'inherit',
                'alignment' => 'start',
                'profile_ids' => null,
                'custom_links' => [],
                'share_network_keys' => null,
            ],
            renderer: SocialsBlockRenderer::class,
            safeForPublicOutput: true,
            sourcePackage: 'socials',
            settings: [
                new BlockSettingDefinitionData('mode', 'capell-socials::socials.widget.mode', 'select', 'follow', options: ['follow' => 'capell-socials::socials.widget.follow', 'share' => 'capell-socials::socials.widget.share']),
                new BlockSettingDefinitionData('heading', 'capell-socials::socials.widget.heading', 'text'),
                new BlockSettingDefinitionData('label_style', 'capell-socials::socials.widget.label_style', 'select', 'inherit'),
                new BlockSettingDefinitionData('link_target', 'capell-socials::socials.widget.link_target', 'select', 'inherit'),
                new BlockSettingDefinitionData('alignment', 'capell-socials::socials.widget.alignment', 'select', 'start'),
                new BlockSettingDefinitionData('profile_ids', 'capell-socials::socials.widget.profile_ids', 'list'),
                new BlockSettingDefinitionData('custom_links', 'capell-socials::socials.widget.custom_links', 'list', []),
                new BlockSettingDefinitionData('share_network_keys', 'capell-socials::socials.widget.share_network_keys', 'list'),
            ],
        );
    }
}
