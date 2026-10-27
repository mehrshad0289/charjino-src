<?php

return [
    'language' => 'fa',
    'direction' => 'rtl',

    'jwt' => [
        'access_secret' => env('JWT_ACCESS_SECRET', 'charjino-dev-access-change-me'),
        'refresh_secret' => env('JWT_REFRESH_SECRET', 'charjino-dev-refresh-change-me'),
        'access_ttl' => (int) env('JWT_ACCESS_TTL', 1800),
        'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 432000),
    ],

    'otp' => [
        'dev_code' => env('DEV_OTP_CODE', '000000'),
        'ttl_seconds' => 120,
        'max_attempts' => 5,
        'rate_max' => 5,
        'rate_window_seconds' => 600,
    ],

    'sms' => [
        'api_key' => env('SMS_API_KEY', ''),
        'base_url' => env('SMS_BASE_URL', 'https://api.sms-webservice.com/api/V3/'),
        'otp_template' => env('SMS_TEMPLATE_OTP', 'charjinonet'),
        'order_completed_template' => env('SMS_TEMPLATE_ORDER_COMPLETED', ''),
        'order_failed_template' => env('SMS_TEMPLATE_ORDER_FAILED', ''),
        'order_refunded_template' => env('SMS_TEMPLATE_ORDER_REFUNDED', ''),
        'allow_in_tests' => false,
    ],

    'admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@charjino.local'),
        'password' => env('ADMIN_PASSWORD', 'charjino-admin'),
        'name' => env('ADMIN_NAME', 'شارژینو'),
        'front_url' => env('ADMIN_FRONT_URL', 'http://127.0.0.1:3002'),
    ],

    'shop_url' => env('SHOP_URL', env('FRONTEND_URL', 'http://127.0.0.1:3001')),

    'catalog' => [
        'url_unavailable_days' => (int) env('CATALOG_URL_UNAVAILABLE_DAYS', 14),
    ],

    'supplier' => [
        's2t_base' => env('S2T_BASE', 'https://shop2topup.com/api/endpoints/v1'),
        's2t_key' => env('S2T_KEY', env('SHOP2TOPUP_API_KEY', '')),
        'webhook_secret' => env('S2T_WEBHOOK_SECRET', ''),
        'allow_live_order' => env('ALLOW_LIVE_ORDER', 'no') === 'yes',
        'fazercards_base' => env('FAZERCARDS_API_BASE', 'https://api.fzr.cards/api/v2'),
        'fazercards_key' => env('FAZERCARDS_API_KEY', ''),
        'fazercards_allow_live_order' => filter_var(env('FAZERCARDS_ALLOW_LIVE_ORDER', false), FILTER_VALIDATE_BOOLEAN),
        'fazercards_webhook_secret' => env('FAZERCARDS_WEBHOOK_SECRET', ''),
        'fazercards_sync_budget_ms' => (int) env('FAZERCARDS_SYNC_BUDGET_MS', 20000),
        'fazercards_sync_max_per_tick' => (int) env('FAZERCARDS_SYNC_MAX_PER_TICK', 5),
        'fazercards_steam_gifts_shop_max' => (int) env('FAZERCARDS_STEAM_GIFTS_SHOP_MAX', 50000),
        'fazercards_listed_shop_max' => (int) env('FAZERCARDS_LISTED_SHOP_MAX', 800),
        'fazercards_hide_missing_max_percent' => (float) env('FAZERCARDS_HIDE_MISSING_MAX_PERCENT', 5),
        'fazercards_gamekeys_per_platform' => (int) env('FAZERCARDS_GAMEKEYS_PER_PLATFORM', 300),
        'fazercards_home_per_group' => (int) env('FAZERCARDS_HOME_PER_GROUP', 12),
        'fazercards_steam_min_usd' => env('FAZERCARDS_STEAM_MIN_USD', '0.15'),
        'fazercards_steam_max_usd' => env('FAZERCARDS_STEAM_MAX_USD', '1000'),
        'fazercards_list_page_limit' => (int) env('FAZERCARDS_LIST_PAGE_LIMIT', 0),
        'fazercards_heavy_lock_ttl' => (int) env('FAZERCARDS_HEAVY_LOCK_TTL', 600),
        'fazercards_heavy_lock_heartbeat' => (int) env('FAZERCARDS_HEAVY_LOCK_HEARTBEAT', 120),
        'fazercards_rate_limiter_enforce' => filter_var(env('FAZERCARDS_RATE_LIMITER_ENFORCE', false), FILTER_VALIDATE_BOOLEAN),
        'fazercards_weekly_stop_hour' => (int) env('FAZERCARDS_WEEKLY_STOP_HOUR', 8),
    ],

    'orders' => [
        'payment_ttl_minutes' => (int) env('ORDER_PAYMENT_TTL_MINUTES', 30),
        'poll_after_minutes' => (int) env('ORDER_POLL_AFTER_MINUTES', 5),
        'poll_max_per_window' => 3,
        'poll_window_seconds' => 60,
        'payment_driver' => env('PAYMENT_DRIVER', env('APP_ENV') === 'testing' ? 'mock' : 'zibal'),
        'mock_payments' => env('PAYMENT_DRIVER', env('APP_ENV') === 'testing' ? 'mock' : 'zibal') === 'mock',
        'stock_fresh_seconds' => (int) env('FAZERCARDS_STOCK_FRESH_SECONDS', 300),
        'price_fresh_seconds' => (int) env('FAZERCARDS_PRICE_FRESH_SECONDS', 86400),
        'price_tolerance_pct' => (float) env('FAZERCARDS_PRICE_TOLERANCE_PCT', 2),
        'block_pay_on_low_balance' => filter_var(env('FAZERCARDS_BLOCK_PAY_ON_LOW_BALANCE', false), FILTER_VALIDATE_BOOLEAN),
        'low_balance_usd' => env('FAZERCARDS_LOW_BALANCE_USD', '20'),
        'balance_hold_retry_minutes' => (int) env('FAZERCARDS_BALANCE_HOLD_RETRY_MINUTES', 5),
        'balance_hold_max_hours' => (int) env('FAZERCARDS_BALANCE_HOLD_MAX_HOURS', 12),
        'balance_cache_seconds' => 45,
        'poll_schedule' => env('FAZERCARDS_POLL_SCHEDULE', '30,60,120,300,900,1800,3600'),
        'poll_max_hours' => (int) env('FAZERCARDS_POLL_MAX_HOURS', 24),
        'poll_budget_per_min' => (int) env('FAZERCARDS_POLL_BUDGET_PER_MIN', 80),
        'unknown_timeout_minutes' => (int) env('FAZERCARDS_UNKNOWN_TIMEOUT_MINUTES', 30),
        'reconcile_max_pages' => (int) env('FAZERCARDS_RECONCILE_MAX_PAGES', 5),
        'telegram_auto_resubmit' => filter_var(env('FAZERCARDS_TELEGRAM_AUTO_RESUBMIT', false), FILTER_VALIDATE_BOOLEAN),
        'same_key_retries' => (int) env('FAZERCARDS_SAME_KEY_RETRIES', 3),
        'refund_mode' => env('CHARJINO_REFUND_MODE', 'admin'),
    ],

    'alerts' => [
        'phone' => env('ADMIN_ALERT_PHONE', ''),
        'email' => env('ADMIN_ALERT_EMAIL', ''),
        'telegram_bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
        'telegram_chat_id' => env('TELEGRAM_ADMIN_CHAT_ID', ''),
    ],

    'zibal' => [
        'merchant' => env('ZIBAL_MERCHANT', ''),
        'callback_url' => env('ZIBAL_CALLBACK_URL', ''),
        'base_url' => env('ZIBAL_BASE_URL', 'https://gateway.zibal.ir/v1/'),
        'start_pay_url' => env('ZIBAL_START_PAY_URL', 'https://gateway.zibal.ir/start/'),
        'test_mode' => filter_var(env('ZIBAL_TEST_MODE', false), FILTER_VALIDATE_BOOLEAN),
    ],

    'cors_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'CORS_ORIGINS',
            'http://127.0.0.1:3001,http://localhost:3001,http://127.0.0.1:3002,http://localhost:3002'
        ))
    ))),

    'smart_search' => [
        'driver' => env('SMART_SEARCH_DRIVER', 'database'),
        'index_prefix' => env('SMART_SEARCH_INDEX_PREFIX', 'charjino_local_'),
        'timeout_ms' => (int) env('SMART_SEARCH_TIMEOUT_MS', 1200),
        'queue' => env('SMART_SEARCH_QUEUE', 'search'),
        'scout_sync' => filter_var(env('SMART_SEARCH_SCOUT_SYNC', false), FILTER_VALIDATE_BOOLEAN),
        'index_writes' => filter_var(env('SMART_SEARCH_INDEX_WRITES', false), FILTER_VALIDATE_BOOLEAN),
        'meilisearch' => [
            'host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'),
            'key' => env('MEILISEARCH_KEY'),
        ],
    ],

    'policy' => [
        'language' => 'fa',
        'direction' => 'rtl',
        'neverAskGamePassword' => true,
        'noNetworkBypassTools' => true,
        'noCrypto' => true,
        'noUserToUserTransfer' => true,
        'noCashOut' => true,
        'noOutcomeToPurchasableValue' => true,
        'noChanceBoxWithPurchasableReward' => true,
        'gameOutsideShopMvp' => true,
        'coinFromGameplayDisabled' => true,
    ],

    'codm_region_check' => [
        'enabled' => (bool) env('CODM_REGION_CHECK_ENABLED', false),
        'device_id' => env('CODM_REGION_CHECK_DEVICE_ID', '7375628e-3842-4dad-be2a-50561c3eb657'),
        'timeout' => (int) env('CODM_REGION_CHECK_TIMEOUT', 4),
        'cache_ttl' => (int) env('CODM_REGION_CHECK_CACHE_TTL', 86400),
        'store_origin' => env('CODM_REGION_CHECK_STORE_ORIGIN', 'https://store.callofdutymobile.com'),
        'user_agent' => env('CODM_REGION_CHECK_UA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36'),
        'hosts' => [
            'US' => 'us',
            'CA' => 'sg',
            'IN' => 'sa',
            'KZ' => 'eu',
            'SA' => 'sg',
        ],
    ],
];
