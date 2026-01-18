<?php

use Knuckles\Scribe\Extracting\Strategies;
use Knuckles\Scribe\Config\Defaults;
use Knuckles\Scribe\Config\AuthIn;
use function Knuckles\Scribe\Config\{removeStrategies, configureStrategy};

return [
    // The HTML <title> for the generated documentation.
    'title' => 'Bora Dia Família - API Documentation',

    // A short description of your API.
    'description' => 'API REST para o aplicativo Bora Dia Família - plataforma de descoberta de experiências para famílias.',

    // Text to place in the "Introduction" section
    'intro_text' => <<<INTRO
        # Bem-vindo à API Bora Dia Família! 🎯
        
        Esta documentação descreve todos os endpoints disponíveis para integração com o aplicativo.
        
        ## Autenticação
        A API usa **JWT (JSON Web Token)** para autenticação. Após fazer login via OTP, você receberá um `access_token` que deve ser incluído no header de todas as requisições protegidas:
        
        ```
        Authorization: Bearer {seu_access_token}
        ```
        
        ## Formato de Resposta
        Todas as respostas seguem o formato padrão:
        
        ```json
        {
          "data": { ... },
          "meta": { "success": true },
          "errors": null
        }
        ```
        
        ## Códigos de Erro
        | HTTP | Código | Descrição |
        |------|--------|-----------|
        | 400 | BAD_REQUEST | Requisição inválida |
        | 401 | UNAUTHORIZED | Token inválido ou expirado |
        | 403 | FORBIDDEN | Sem permissão |
        | 404 | NOT_FOUND | Recurso não encontrado |
        | 422 | VALIDATION_ERROR | Erro de validação |
        | 429 | RATE_LIMIT | Muitas requisições |
        
        ## Paginação
        Endpoints de listagem usam cursor pagination. O campo `next_cursor` em `meta` contém o cursor para a próxima página.
    INTRO,

    // The base URL displayed in the docs.
    'base_url' => 'https://api.valorsc.com.br',

    // Routes to include in the docs
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

    // Authentication configuration
    'auth' => [
        'enabled' => true,
        'default' => true,
        'in' => AuthIn::BEARER->value,
        'name' => 'Authorization',
        'use_value' => env('SCRIBE_AUTH_KEY'),
        'placeholder' => '{ACCESS_TOKEN}',
        'extra_info' => 'Obtenha seu token através do endpoint `/auth/otp/verify` após verificar o código OTP.',
    ],

    // Example languages
    'example_languages' => [
        'bash',
        'javascript',
        'php',
    ],

    // Postman collection
    'postman' => [
        'enabled' => true,
        'overrides' => [
            'info.version' => '1.0.0',
        ],
    ],

    // OpenAPI spec
    'openapi' => [
        'enabled' => true,
        'version' => '3.0.3',
        'overrides' => [
            'info.version' => '1.0.0',
        ],
        'generators' => [],
    ],

    // Groups configuration
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
        'metadata' => [
            ...Defaults::METADATA_STRATEGIES,
        ],
        'headers' => [
            ...Defaults::HEADERS_STRATEGIES,
            Strategies\StaticData::withSettings(data: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
        ],
        'urlParameters' => [
            ...Defaults::URL_PARAMETERS_STRATEGIES,
        ],
        'queryParameters' => [
            ...Defaults::QUERY_PARAMETERS_STRATEGIES,
        ],
        'bodyParameters' => [
            ...Defaults::BODY_PARAMETERS_STRATEGIES,
        ],
        'responses' => removeStrategies(
            Defaults::RESPONSES_STRATEGIES,
            [Strategies\Responses\ResponseCalls::class]
        ),
        'responseFields' => [
            ...Defaults::RESPONSE_FIELDS_STRATEGIES,
        ]
    ],

    'database_connections_to_transact' => [config('database.default')],

    'fractal' => [
        'serializer' => null,
    ],
];
