<?php

namespace App\Enums;

enum SourceType: string
{
    case Webpage = 'webpage';
    case Youtube = 'youtube';
    case XPost = 'x_post';
    case LinkedIn = 'linkedin';
}
