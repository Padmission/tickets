<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    */

    'provider' => env('COPILOT_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Default AI Model
    |--------------------------------------------------------------------------
    */

    'model' => env('COPILOT_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | Agent Behavior
    |--------------------------------------------------------------------------
    */

    'agent' => [
        'timeout' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Chat History
    |--------------------------------------------------------------------------
    */

    'chat' => [
        'title_auto_generate' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */

    'rate_limits' => [
        'enabled' => false,
        'max_messages_per_hour' => 60,
        'max_messages_per_day' => 500,
        'max_tokens_per_hour' => 100000,
        'max_tokens_per_day' => 1000000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Budget
    |--------------------------------------------------------------------------
    */

    'token_budget' => [
        'enabled' => false,
        'warn_at_percentage' => 80,
        'daily_budget' => null,
        'monthly_budget' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    */

    'audit' => [
        'enabled' => true,
        'log_messages' => true,
        'log_tool_calls' => true,
        'log_record_access' => true,
        'log_navigation' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Memory
    |--------------------------------------------------------------------------
    */

    'memory' => [
        'enabled' => true,
        'max_memories_per_user' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Integration
    |--------------------------------------------------------------------------
    */

    'respect_authorization' => true,

    /*
    |--------------------------------------------------------------------------
    | Rate Limit Management UI
    |--------------------------------------------------------------------------
    */

    'management' => [
        'enabled' => false,
        'guard' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Quick Actions / Canned Prompts
    |--------------------------------------------------------------------------
    */

    'quick_actions' => [],

    /*
    |--------------------------------------------------------------------------
    | System Prompt
    |--------------------------------------------------------------------------
    */

    'system_prompt' => null,

    'few_shot_examples' => [],

    'escalation_triggers' => [
        'low_confidence_threshold' => 0.6,
        'repeated_unresolved_threshold' => 3,
    ],

    'usage_log' => [
        'store_payloads' => env('COPILOT_USAGE_LOG_STORE_PAYLOADS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Host App Record/API Context
    |--------------------------------------------------------------------------
    |
    | Host applications may expose read-only record context tools. The package
    | does not assume an API exists; apps can enable and configure these keys.
    |
    */

    'record_context' => [
        'enabled' => false,
        'types' => [],
    ],

    'journey_api' => [
        'enabled' => false,
        'max_per_page' => 25,
        'resources' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Tools
    |--------------------------------------------------------------------------
    | Tool classes available on every page across all resources.
    | Each entry should be a class name that extends BaseTool.
    */

    'global_tools' => [],

    'replace_tools' => false,

];
