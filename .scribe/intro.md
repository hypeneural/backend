# Introduction

API REST para o aplicativo Bora Dia Família - plataforma de descoberta de experiências para famílias.

<aside>
    <strong>Base URL</strong>: <code>https://api.valorsc.com.br</code>
</aside>

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

