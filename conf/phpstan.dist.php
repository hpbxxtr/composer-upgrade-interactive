<?php

declare(strict_types=1);

use Brnshkr\Config\PhpStan;

return PhpStan::getConfig(null, true)
    ->setLevel(10)
    ->setPaths([
        'src',
    ])
;
