<?php

declare(strict_types=1);

return [
    'bootstrap' => null,
    'public' => [
        'iletisim' => [
            'target' => \App\Modules\Contact\Http\ContactPage::class,
            'kind' => 'Sayfa',
            'dispatch' => 'handler',
        ],
    ],
    'admin' => [],
    'api' => [],
];
