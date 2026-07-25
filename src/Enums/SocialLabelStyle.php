<?php

declare(strict_types=1);

namespace Capell\Socials\Enums;

enum SocialLabelStyle: string
{
    case Icons = 'icons';
    case Labels = 'labels';
    case IconsAndLabels = 'icons_and_labels';
}
