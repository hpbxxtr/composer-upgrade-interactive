<?php

declare(strict_types=1);

use Brnshkr\Config\PhpCsFixer;

return PhpCsFixer::getConfig()
    ->setRules([
        'static_lambda' => false
    ]);
