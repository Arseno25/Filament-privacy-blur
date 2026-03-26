<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Privacy Mode
    |--------------------------------------------------------------------------
    |
    | What should happen when `->private()` is called on a column without
    | specifically chaining `->privacyMode('...')`?
    | Supported: 'blur', 'mask', 'blur_hover',
    |            'blur_click', 'blur_auth', 'hybrid'
    |
    */
    'default_mode' => 'blur_click',

    /*
    |--------------------------------------------------------------------------
    | Default Blur Amount
    |--------------------------------------------------------------------------
    |
    | The default blur intensity in pixels. E.g., `4` corresponds to Tailwind's `blur-sm`.
    |
    */
    'default_blur_amount' => 4,

    /*
    |--------------------------------------------------------------------------
    | Default Mask Strategy
    |--------------------------------------------------------------------------
    |
    | If the field is set to mask instead of blur, how should it mask by default
    | if not specified? Options: 'email', 'phone', 'nik', 'full_name', 'api_key',
    | 'address', 'generic'.
    |
    */
    'default_mask_strategy' => 'generic',

    /*
    |--------------------------------------------------------------------------
    | Globally Except Rules
    |--------------------------------------------------------------------------
    |
    | If there are columns, resources, or panels that should NEVER have privacy blur
    | applied, even if they have `->private()` configured, specify them here.
    |
    */
    'except_columns' => ['id', 'created_at', 'updated_at'],
    'except_resources' => [],
    'except_panels' => [],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | If enabled, every time a user reveals a blurred item via a click action,
    | it will be logged to the `privacy_reveal_logs` table.
    |
    */
    'audit_enabled' => false,

    /*
    |--------------------------------------------------------------------------
    | UI / UX Options
    |--------------------------------------------------------------------------
    |
    | Specific options for display
    |
    */
    'icon_trigger_enabled' => true,
];
