<?php

return [
    'public_enabled' => (bool) env('COUNTERPARTY_LEDGER_PUBLIC_ENABLED', true),
    'shadow_shared_enabled' => (bool) env('COUNTERPARTY_LEDGER_SHADOW_SHARED_ENABLED', false),
    'shadow_manual_enabled' => (bool) env('COUNTERPARTY_LEDGER_SHADOW_MANUAL_ENABLED', false),
];
