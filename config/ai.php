<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Which AI provider and model the app uses
    |--------------------------------------------------------------------------
    |
    | LLM_PROVIDER is one of the providers below. LLM_MODEL_NAME is the Azure
    | deployment name, or the Ollama model name, depending on the provider.
    |
    */

    'default' => env('LLM_PROVIDER', 'azure'),

    'providers' => [
        'azure' => [
            'driver' => 'azure',
            'key' => env('AZURE_OPENAI_API_KEY'),
            // The SDK adds "/openai/v1" itself, so take it off if the address already ends with it.
            'url' => Str::before((string) env('AZURE_OPENAI_ENDPOINT'), '/openai/v1'),
            'deployment' => env('LLM_MODEL_NAME'),
            // Ask Azure not to keep what we send or what comes back.
            'store' => false,
        ],

        'ollama' => [
            'driver' => 'ollama',
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
            'models' => [
                'text' => ['default' => env('LLM_MODEL_NAME')],
            ],
        ],
    ],

];
