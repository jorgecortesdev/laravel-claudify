<?php

declare(strict_types=1);

namespace JorgeCortesDev\Claudify\Enums;

enum WriteResult
{
    case SUCCESS;
    case UPDATED;
    case FAILED;
}
