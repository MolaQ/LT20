<?php

return [
    // Premium dla wszystkich (12.11.2026): każdy zalogowany gracz ma dodatki premium za darmo, a wpłaty to
    // dobrowolna „kawka” na buycoffee.to. false: bez premium (kawka nadal nie daje premium), a cennik pojawia się dopiero
    // po ustawieniu Przelewy24.
    'free' => (bool) env('PREMIUM_FREE', true),

    // Premium na start (16.11.2026): nowe konto po potwierdzeniu adresu e-mail dostaje tyle dni premium (0 = bez prezentu).
    'welcome_days' => (int) env('PREMIUM_WELCOME_DAYS', 14),
];
