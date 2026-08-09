<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sensitive Read Routes
    |--------------------------------------------------------------------------
    |
    | Every state-changing admin request is audited automatically. Plain page
    | views are not (they would bury the trail in noise) — except these, where
    | *looking* is itself the sensitive act: reading private conversations,
    | opening a member's full account, and any data export.
    |
    | Values are route-name patterns; `*` wildcards are supported.
    |
    */

    'sensitive_read_routes' => [
        'admin.messaging.*',
        'admin.users.show',
        'admin.users.sessions',
        'admin.users.password_resets',
        'admin.earnings.reconciliation',
        'admin.*.export',
        'admin.*.*.export',
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Payload Capture
    |--------------------------------------------------------------------------
    |
    | Submitted fields are stored with the audit row so "what did they change"
    | is answerable. Never-logged keys are dropped outright; every retained
    | value is truncated to keep rows small.
    |
    */

    'never_log_keys' => [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        '_token',
        '_method',
        'remember',
    ],

    'max_value_length' => 300,

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | How long stylebite:prune-activity-logs keeps rows. The audit trail is
    | meant to be long-lived, so this is deliberately generous — raise it if
    | your retention policy needs more, and remember the table grows with
    | every admin action.
    |
    */

    'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 365),

];
