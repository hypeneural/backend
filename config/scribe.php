<?php

/**
 * Scribe API Documentation Configuration
 * 
 * This config only works when knuckleswtf/scribe package is installed.
 * If not installed, this file will return an empty array to avoid errors.
 */

// Check if Scribe is installed
if (!class_exists(\Knuckles\Scribe\Config\Defaults::class)) {
    return [];
}

use Knuckles\Scribe\Extracting\Strategies;
use Knuckles\Scribe\Config\Defaults;
use Knuckles\Scribe\Config\AuthIn;
use function Knuckles\Scribe\Config\{removeStrategies, configureStrategy};

return [
    'title' => 'Bora Dia Família - API Documentation',
    'description' => 'API REST para o aplicativo Bora Dia Família.',

    'intro_text' => <<<INTRO
        # Bem-vindo à API Bora Dia Família! 🎯
        
        Esta documentação descreve todos os endpoints disponíveis.
        
        ## Autenticação
        A API usa **JWT (JSON Web Token)**. Após login via OTP, inclua o token no header:
        
        ```
        Authorization: Bearer {seu_access_token}
        ```
    INTRO,

    'base_url' => 'https://api.valorsc.com.br',

    'routes' => [
        [
            'match' => [
                'prefixes' => ['api/v1/*'],
                'domains' => ['*'],
            ],
            'include' => [],
            'exclude' => [],
        ],
    ],

    'type' => 'static',
    'theme' => 'default',

    'static' => [
        'output_path' => 'public/docs',
    ],

    'laravel' => [
        'add_routes' => true,
        'docs_url' => '/docs',
        'assets_directory' => null,
        'middleware' => [],
    ],

    'external' => [
        'html_attributes' => []
    ],

    'try_it_out' => [
        'enabled' => true,
        'base_url' => 'https://api.valorsc.com.br',
        'use_csrf' => false,
        'csrf_url' => '/sanctum/csrf-cookie',
    ],

    'auth' => [
        'enabled' => true,
        'default' => true,
        'in' => AuthIn::BEARER->value,
        'name' => 'Authorization',
        'use_value' => env('SCRIBE_AUTH_KEY'),
        'placeholder' => '{ACCESS_TOKEN}',
        'extra_info' => 'Obtenha seu token via `/auth/otp/verify`.',
    ],

    'example_languages' => ['bash', 'javascript', 'php'],

    'postman' => [
        'enabled' => true,
        'overrides' => ['info.version' => '1.0.0'],
    ],

    'openapi' => [
        'enabled' => true,
        'version' => '3.0.3',
        'overrides' => ['info.version' => '1.0.0'],
        'generators' => [],
    ],

    'groups' => [
        'default' => 'Outros',
        'order' => [
            '1. Autenticação',
            '2. Onboarding',
            '3. Usuário',
            '4. Home',
            '5. Busca',
            '6. Mapa',
            '7. Experiências',
            '8. Categorias',
            '9. Cidades',
            '10. Favoritos',
            '11. Família',
            '12. Dependentes',
            '13. Planos',
            '14. Reviews',
            '15. Memórias',
            '16. Notificações',
            '17. Uploads',
            '18. Utilidades',
        ],
    ],

    'logo' => false,
    'last_updated' => 'Última atualização: {date:d/m/Y H:i}',

    'examples' => [
        'faker_seed' => 1234,
        'models_source' => ['factoryCreate', 'factoryMake', 'databaseFirst'],
    ],

    'strategies' => [
        'metadata' => [...Defaults::METADATA_STRATEGIES],
        'headers' => [
            ...Defaults::HEADERS_STRATEGIES,
            Strategies\StaticData::withSettings(data: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
        ],
        'urlParameters' => [...Defaults::URL_PARAMETERS_STRATEGIES],
        'queryParameters' => [...Defaults::QUERY_PARAMETERS_STRATEGIES],
        'bodyParameters' => [...Defaults::BODY_PARAMETERS_STRATEGIES],
        'responses' => removeStrategies(
            Defaults::RESPONSES_STRATEGIES,
            [Strategies\Responses\ResponseCalls::class]
        ),
        'responseFields' => [...Defaults::RESPONSE_FIELDS_STRATEGIES],
    ],

    'database_connections_to_transact' => [config('database.default')],

    'fractal' => ['serializer' => null],
];
