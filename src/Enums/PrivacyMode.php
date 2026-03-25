<?php

namespace Arseno25\FilamentPrivacyBlur\Enums;

enum PrivacyMode: string
{
    case Disabled = 'disabled';
    case Blur = 'blur';
    case Mask = 'mask';
    case BlurHover = 'blur_hover';
    case BlurClick = 'blur_click';
    case BlurAuth = 'blur_auth';
    case Hybrid = 'hybrid';
}
