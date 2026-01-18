# 📱 Bora Dia Família - Documentação Completa da API

> **URL de Produção:** `https://api.valorsc.com.br/api/v1/`  
> **Autenticação:** Bearer Token JWT no header `Authorization: Bearer {token}`  
> **Content-Type:** `application/json`  
> **Última Atualização:** 2026-01-18

---

## 📖 Estrutura Padrão de Resposta

Todos os endpoints seguem o mesmo formato:

```typescript
interface ApiResponse<T> {
  data: T | null;
  meta: {
    success: boolean;
    next_cursor?: string;    // Para paginação cursor
    has_more?: boolean;      // Indica se há mais itens
    cache_until?: string;    // Quando o cache expira
    unread_count?: number;   // Para notificações
  };
  errors: Array<{
    code: string;            // Ex: "OTP_EXPIRED"
    message: string;         // Mensagem em português
    field?: string;          // Campo com erro (validação)
  }> | null;
}
```

---

## 🔐 1. AUTENTICAÇÃO

### 1.1 Enviar OTP
```http
POST /auth/otp/send
```

**O que faz:** Envia um código de 6 dígitos por SMS para o telefone informado.

**Rate Limit:** 1 request por minuto por telefone.

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `phone` | string | ✅ | Telefone com DDD (ex: "11999999999") |

**Request:**
```json
{ "phone": "11999999999" }
```

**Response 200:**
```json
{
  "data": {
    "message": "Code sent successfully.",
    "expires_at": "2026-01-18T05:32:36Z",
    "code": "816538"
  },
  "meta": { "success": true },
  "errors": null
}
```

> ⚠️ O campo `code` só aparece em ambiente dev/staging para facilitar testes.

**Erros Possíveis:**
| HTTP | Código | Quando |
|------|--------|--------|
| 422 | `VALIDATION_ERROR` | Telefone inválido |
| 429 | `RATE_LIMIT` | Muitas requisições (aguarde 1 min) |

---

### 1.2 Verificar OTP
```http
POST /auth/otp/verify
```

**O que faz:** Valida o código OTP e retorna tokens de acesso. Se o usuário não existir, cria automaticamente.

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `phone` | string | ✅ | Mesmo telefone usado no send |
| `code` | string | ✅ | Código de 6 dígitos |
| `name` | string | ✅ (novos) | Nome do usuário (obrigatório para novos) |
| `referral_code` | string | ❌ | Código de indicação |

**Request:**
```json
{
  "phone": "11999999999",
  "code": "816538",
  "name": "João Silva",
  "referral_code": "BORA123"
}
```

**Response 200 (usuário existente) / 201 (novo):**
```json
{
  "data": {
    "user": {
      "id": "uuid-do-usuario",
      "phone": "+5511999999999",
      "name": "João Silva",
      "avatar": null,
      "is_verified": true,
      "onboarding_completed": false,
      "primary_family_id": "uuid-da-familia",
      "primary_city_id": null
    },
    "tokens": {
      "access_token": "eyJhbGciOiJIUzI1NiIs...",
      "refresh_token": "dGhpcyBpcyBhIHJlZnJl...",
      "token_type": "bearer",
      "expires_in": 3600
    },
    "is_new_user": true
  },
  "meta": { "success": true },
  "errors": null
}
```

**Lógica de Navegação:**
```typescript
if (response.data.user.onboarding_completed === false) {
  navigate('/onboarding');
} else {
  navigate('/home');
}
```

**Erros Possíveis:**
| HTTP | Código | Quando |
|------|--------|--------|
| 400 | `OTP_EXPIRED` | Código expirou (>5 min) |
| 401 | `OTP_INVALID` | Código incorreto |
| 429 | `OTP_MAX_ATTEMPTS` | 5 tentativas esgotadas |

---

### 1.3 Refresh Token
```http
POST /auth/refresh
```

**O que faz:** Gera novos tokens usando o refresh_token. O refresh_token antigo é invalidado (one-time use).

**Request:**
```json
{ "refresh_token": "seu-refresh-token" }
```

**Response 200:**
```json
{
  "data": {
    "tokens": {
      "access_token": "novo-access-token",
      "refresh_token": "novo-refresh-token",
      "expires_in": 3600
    }
  }
}
```

---

### 1.4 Logout
```http
POST /auth/logout 🔒
```

**O que faz:** Invalida os tokens do usuário.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `refresh_token` | string | Token específico para revogar |
| `all_devices` | boolean | Se `true`, revoga todos os tokens |

---

### 1.5 Dados do Usuário Logado
```http
GET /auth/me 🔒
```

**O que faz:** Retorna dados completos do usuário autenticado.

**Response:**
```json
{
  "data": {
    "id": "uuid",
    "phone": "+5511999999999",
    "name": "João Silva",
    "avatar": "https://cdn.../avatar.jpg",
    "email": "joao@email.com",
    "is_verified": true,
    "stats": {
      "xp": 500,
      "level": 2,
      "streak_days": 5
    },
    "primary_family": {
      "id": "uuid",
      "name": "Família Silva"
    },
    "created_at": "2026-01-15T10:00:00Z"
  }
}
```

---

## 🎯 2. ONBOARDING

### 2.1 Status do Onboarding
```http
GET /onboarding/status 🔒
```

**O que faz:** Verifica quais etapas do onboarding o usuário completou.

**Response:**
```json
{
  "data": {
    "completed": false,
    "steps_completed": ["name", "family"],
    "missing_steps": ["preferences", "categories"]
  }
}
```

**Etapas Possíveis:**
- `name` - Nome do usuário
- `family` - Nome da família
- `preferences` - Distância e preço
- `categories` - Categorias favoritas

---

### 2.2 Completar Onboarding
```http
POST /onboarding/complete 🔒
```

**O que faz:** Salva todos os dados do onboarding de uma vez.

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `name` | string | ✅ | Nome do usuário |
| `family_name` | string | ❌ | Nome da família |
| `favorite_categories` | uuid[] | ✅ | Mínimo 1, máximo 10 IDs |
| `max_distance_km` | number | ❌ | 1-100 (padrão: 30) |
| `default_price` | string | ❌ | `free`, `moderate`, `top` |
| `dependents` | object[] | ❌ | Lista de dependentes |

**Schema Dependent:**
```typescript
interface Dependent {
  name: string;           // Obrigatório
  birth_date?: string;    // YYYY-MM-DD
  age_group: 'baby' | 'toddler' | 'kid' | 'teen';
  avatar?: string;        // Emoji
}
```

**Request Exemplo:**
```json
{
  "name": "João Silva",
  "family_name": "Família Silva",
  "favorite_categories": [
    "c038d7b3-74b9-4c28-8488-b64a5dc1d791",
    "99da4ce7-cf82-4445-9942-51873a2c7741"
  ],
  "max_distance_km": 30,
  "default_price": "moderate",
  "dependents": [
    {
      "name": "Lucas",
      "birth_date": "2018-05-15",
      "age_group": "kid",
      "avatar": "👦"
    }
  ]
}
```

---

## 👤 3. USUÁRIO

### 3.1 Atualizar Perfil
```http
PUT /users/me 🔒
```

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `name` | string | Nome do usuário |
| `email` | string | Email (validação) |

---

### 3.2 Atualizar Avatar
```http
PATCH /users/me/avatar 🔒
```

```json
{ "avatar_url": "https://cdn.../nova-foto.jpg" }
```

---

### 3.3 Atualizar Localização
```http
POST /users/me/location 🔒
```

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `lat` | number | Latitude |
| `lng` | number | Longitude |
| `city_id` | uuid | ID da cidade (opcional) |

---

### 3.4 Estatísticas de Gamificação
```http
GET /users/me/stats 🔒
```

**Response:**
```json
{
  "data": {
    "xp": 1500,
    "level": 5,
    "level_progress": 0.45,
    "next_level_xp": 2000,
    "streak_days": 12,
    "longest_streak": 15,
    "total_saves": 45,
    "total_reviews": 12,
    "total_plans": 8,
    "total_memories": 67,
    "badges": [
      {
        "slug": "explorer",
        "name": "Explorador",
        "icon": "🧭",
        "earned_at": "2026-01-10T..."
      }
    ]
  }
}
```

**Regras de XP:**
| Ação | XP | Limite Diário |
|------|-----|---------------|
| Onboarding | 100 | 1× (única) |
| Salvar experiência | 5 | 20 |
| Criar review | 25 | 5 |
| Criar memória | 10 | 10 |
| Completar plano | 50 | - |
| Indicação | 100 | - |

---

### 3.5 Desativar Conta
```http
DELETE /users/me 🔒
```

```json
{ "confirmation": "DELETE" }
```

---

## 🏠 4. HOME

### 4.1 Feed Unificado
```http
GET /home 🔒
```

**O que faz:** Retorna dados personalizados para a tela inicial.

**Query Params:**
| Param | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `city_id` | uuid | ✅ | ID da cidade |
| `lat` | number | ❌ | Latitude do usuário |
| `lng` | number | ❌ | Longitude do usuário |

**Response:**
```json
{
  "data": {
    "user": {
      "name": "João",
      "avatar": "https://...",
      "family_name": "Família Silva",
      "streak": 5,
      "level": 2
    },
    "highlight": [
      {
        "id": "uuid",
        "title": "Parque Ibirapuera",
        "mission_title": "Descubra a magia",
        "cover_image": "https://...",
        "distance_km": 3.2,
        "price_level": "free",
        "average_rating": 4.8,
        "reviews_count": 156,
        "is_saved": false,
        "badges": ["staff_pick"],
        "category": { "id": "uuid", "name": "Parques", "emoji": "🌳" }
      }
    ],
    "trending": [ /* mesmo formato */ ],
    "chips": {
      "adventure": 12,
      "rain": 8,
      "baby": 15,
      "food": 23
    },
    "upcoming_plans": [
      { "id": "uuid", "title": "Domingo", "date": "2026-01-20", "experiences_count": 3 }
    ]
  }
}
```

---

## 🔍 5. BUSCA

### 5.1 Buscar Experiências
```http
GET /experiences/search 🔒
```

**O que faz:** Busca experiências com filtros avançados e paginação por cursor.

**Query Params - Filtros:**

| Param | Tipo | Descrição |
|-------|------|-----------|
| `city_id` | uuid | **Obrigatório** - ID da cidade |
| `q` | string | Texto livre (fulltext search) |
| `categories[]` | uuid[] | IDs das categorias |
| `price[]` | string[] | `free`, `moderate`, `top` |
| `duration` | string | `quick` (<1h), `half` (1-3h), `full` (3h+) |
| `age_tags[]` | string[] | `baby`, `toddler`, `kid`, `teen`, `all` |
| `weather` | string | `sun`, `rain`, `any` |
| `has_reviews` | boolean | Apenas com reviews |
| `min_rating` | number | Rating mínimo (ex: 4.0) |
| `sort` | string | `trending`, `rating`, `distance`, `saves` |
| `cursor` | string | Cursor da próxima página |
| `limit` | number | 1-50 (padrão: 20) |

**Exemplo de Request:**
```
GET /experiences/search?city_id=UUID&categories[]=UUID1&price[]=free&age_tags[]=kid&sort=trending&limit=20
```

**Response:**
```json
{
  "data": {
    "results": [
      {
        "id": "uuid",
        "title": "Piquenique no Parque",
        "mission_title": "Crie memórias",
        "cover_image": "https://...",
        "distance_km": 2.5,
        "price_level": "free",
        "duration_bucket": "half",
        "average_rating": 4.7,
        "reviews_count": 45,
        "saves_count": 230,
        "is_saved": true,
        "badges": ["trending"],
        "category": { "id": "uuid", "name": "Parques", "emoji": "🌳" }
      }
    ],
    "facets": {
      "categories": [
        { "id": "uuid", "name": "Parques", "emoji": "🌳", "count": 15 }
      ],
      "price_level": [
        { "value": "free", "label": "Grátis", "count": 23 }
      ],
      "age_tags": [
        { "value": "kid", "label": "Crianças", "count": 45 }
      ],
      "duration": [
        { "value": "half", "label": "1-3 horas", "count": 25 }
      ]
    },
    "applied_filters": { "categories": ["uuid1"] },
    "total_estimate": 156
  },
  "meta": {
    "success": true,
    "next_cursor": "eyJzY29yZSI6ODUuMn0=",
    "has_more": true
  }
}
```

**Infinite Scroll:**
```typescript
const { data, fetchNextPage, hasNextPage } = useInfiniteQuery({
  queryKey: ['search', cityId, filters],
  queryFn: ({ pageParam }) => 
    api.get('/experiences/search', { 
      params: { ...filters, cursor: pageParam } 
    }),
  getNextPageParam: (lastPage) => 
    lastPage.meta.has_more ? lastPage.meta.next_cursor : undefined,
});
```

---

## 🗺️ 6. MAPA

### 6.1 Experiências no Mapa
```http
GET /map/experiences 🔒
```

**Query Params:**
| Param | Tipo | Descrição |
|-------|------|-----------|
| `bbox` | string | `west,south,east,north` (obrigatório) |
| `zoom` | number | 1-22 (obrigatório) |
| `categories[]` | uuid[] | Filtrar por categorias |
| `limit` | number | 1-200 (padrão: 100) |

**Lógica de Clustering:**
- `zoom >= 14`: Retorna pontos individuais
- `zoom < 14`: Retorna clusters

**Response:**
```json
{
  "data": {
    "points": [
      {
        "id": "uuid",
        "lat": -23.5874,
        "lng": -46.6576,
        "title": "Parque Ibirapuera",
        "cover_image": "https://...",
        "category_emoji": "🌳"
      }
    ],
    "clusters": [
      {
        "lat": -23.55,
        "lng": -46.63,
        "count": 12,
        "bounds": { "west": -46.65, "south": -23.58, "east": -46.61, "north": -23.52 }
      }
    ]
  }
}
```

---

## 📍 7. EXPERIÊNCIAS

### 7.1 Detalhes da Experiência
```http
GET /experiences/{id} 🔒
```

**Headers de Cache:**
- `ETag` - Para conditional requests
- `Cache-Control: private, max-age=600`

**Response Completa:**
```json
{
  "data": {
    "id": "uuid",
    "title": "Piquenique no Ibirapuera",
    "mission_title": "Descubra a magia do maior parque de SP",
    "summary": "Uma experiência incrível...",
    
    "category": {
      "id": "uuid",
      "name": "Parques",
      "emoji": "🌳",
      "color": "#22c55e"
    },
    
    "badges": ["staff_pick", "trending"],
    "age_tags": ["toddler", "kid", "teen"],
    "vibe": ["relaxante", "divertido"],
    
    "duration": {
      "label": "1-2h",
      "minutes_min": 60,
      "minutes_max": 120
    },
    
    "price": {
      "level": "free",
      "label": "Entrada gratuita"
    },
    
    "weather": ["sun", "any"],
    
    "practical": {
      "parking": true,
      "bathroom": true,
      "food": true,
      "stroller": true,
      "accessibility": false,
      "changing_table": true
    },
    
    "tips": [
      "Chegue cedo para garantir lugar na sombra",
      "Leve protetor solar e água"
    ],
    
    "location": {
      "place_name": "Parque Ibirapuera",
      "address": "Av. Pedro Álvares Cabral, s/n",
      "neighborhood": "Vila Mariana",
      "city": "São Paulo",
      "state": "SP"
    },
    
    "coords": { "lat": -23.5874, "lng": -46.6576 },
    
    "images": {
      "cover": "https://...",
      "gallery": ["https://...", "https://..."]
    },
    
    "stats": {
      "saves_count": 234,
      "reviews_count": 56,
      "average_rating": 4.7,
      "trending_score": 85.3
    },
    
    "review_distribution": { "5": 30, "4": 18, "3": 5, "2": 1, "1": 2 },
    
    "recent_reviews": [
      {
        "id": "uuid",
        "user_name": "Maria",
        "user_avatar": "https://...",
        "rating": 5,
        "comment": "Adoramos!",
        "created_at": "2026-01-15T..."
      }
    ],
    
    "related": [
      { "id": "uuid", "title": "Parque Villa-Lobos", "cover_image": "https://...", "distance_km": 5.2 }
    ],
    
    "user_review": null,
    "is_saved": false,
    "distance_km": 3.2
  }
}
```

---

## 📂 8. CATEGORIAS

### 8.1 Listar Categorias
```http
GET /categories (Público)
```

**Cache:** 24 horas

**Response:**
```json
{
  "data": [
    {
      "id": "c038d7b3-74b9-4c28-8488-b64a5dc1d791",
      "name": "Parques",
      "slug": "parques",
      "emoji": "🌳",
      "icon": "trees",
      "color": "#22c55e",
      "description": "Parques, praças e áreas verdes",
      "experiences_count": 8
    }
  ],
  "meta": { 
    "success": true, 
    "cache_until": "2026-01-19T05:27:20Z" 
  }
}
```

**Categorias Disponíveis:**
| Slug | Nome | Emoji | Cor |
|------|------|-------|-----|
| `parques` | Parques | 🌳 | #22c55e |
| `museus` | Museus | 🏛️ | #8b5cf6 |
| `aventura` | Aventura | 🎢 | #ef4444 |
| `gastronomia` | Gastronomia | 🍕 | #f59e0b |
| `natureza` | Natureza | 🏞️ | #06b6d4 |

---

## 🏙️ 9. CIDADES

### 9.1 Buscar Cidades
```http
GET /cities (Público)
```

| Param | Tipo | Descrição |
|-------|------|-----------|
| `q` | string | Texto de busca |
| `limit` | number | 1-20 (padrão: 10) |

**Response:**
```json
{
  "data": [
    {
      "id": "edbca93c-2f01-4e17-af0a-53b1ccb4bf90",
      "name": "São Paulo",
      "slug": "sao-paulo",
      "state": "SP",
      "country": "BR",
      "lat": -23.5505,
      "lng": -46.6333,
      "timezone": "America/Sao_Paulo",
      "display_name": "São Paulo, SP"
    }
  ]
}
```

---

### 9.2 Detalhes da Cidade
```http
GET /cities/{id} (Público)
```

**Response:**
```json
{
  "data": {
    "id": "uuid",
    "name": "São Paulo",
    "state": "SP",
    "population": 12400000,
    "places_count": 10,
    "experiences_count": 20
  }
}
```

---

## ❤️ 10. FAVORITOS

### Endpoints de Favoritos

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/favorites` | Listar salvos |
| POST | `/favorites` | Salvar experiência |
| DELETE | `/favorites/{experience_id}` | Remover |
| POST | `/favorite-lists` | Criar lista |
| PUT | `/favorite-lists/{id}` | Atualizar lista |
| DELETE | `/favorite-lists/{id}` | Excluir lista |

**GET /favorites Query Params:**
- `sort`: `saved_at` (padrão), `name`, `distance`
- `list_id`: UUID da lista específica

**POST /favorites:**
```json
{
  "experience_id": "uuid",
  "list_id": "uuid"
}
```

---

## 👨‍👩‍👧‍👦 11. FAMÍLIA

### Endpoints de Família

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/family` | Dados da família |
| POST | `/family` | Criar família |
| PUT | `/family` | Atualizar |
| POST | `/family/invite` | Gerar código |
| POST | `/family/join` | Entrar com código |
| POST | `/family/leave` | Sair |
| DELETE | `/family/{id}/members/{userId}` | Remover membro |

### Dependentes

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/family/dependents` | Listar |
| POST | `/family/dependents` | Criar |
| PUT | `/family/dependents/{id}` | Atualizar |
| DELETE | `/family/dependents/{id}` | Excluir |

**Age Groups:**
| Valor | Idade | Emoji Sugerido |
|-------|-------|----------------|
| `baby` | 0-1 | 👶 |
| `toddler` | 2-4 | 🧒 |
| `kid` | 5-12 | 👦👧 |
| `teen` | 13-17 | 🧑 |

---

## 📋 12. PLANOS

### Endpoints de Planos

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/plans` | Listar |
| POST | `/plans` | Criar |
| GET | `/plans/{id}` | Detalhes |
| PUT | `/plans/{id}` | Atualizar |
| DELETE | `/plans/{id}` | Excluir |
| POST | `/plans/{id}/complete` | Marcar concluído |
| POST | `/plans/{id}/duplicate` | Duplicar |

### Experiências no Plano

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/plans/{id}/experiences` | Adicionar |
| PUT | `/plans/{id}/experiences/{expId}` | Atualizar ordem |
| DELETE | `/plans/{id}/experiences/{expId}` | Remover |

### Colaboradores

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/plans/{id}/collaborators` | Convidar |
| DELETE | `/plans/{id}/collaborators/{userId}` | Remover |

**Status de Plano:**
```typescript
type PlanStatus = 'draft' | 'planned' | 'in_progress' | 'completed';
```

**Time Slots:**
```typescript
type TimeSlot = 'morning' | 'afternoon' | 'evening';
```

---

## ⭐ 13. REVIEWS

### Endpoints de Reviews

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/experiences/{id}/reviews` | Listar |
| POST | `/experiences/{id}/reviews` | Criar |
| PUT | `/reviews/{id}` | Atualizar |
| DELETE | `/reviews/{id}` | Excluir |
| POST | `/reviews/{id}/helpful` | Marcar útil |

**GET /experiences/{id}/reviews Query Params:**
- `sort`: `recent`, `helpful`, `rating_high`, `rating_low`
- `cursor`: Paginação
- `limit`: 1-50

**POST /experiences/{id}/reviews:**
```json
{
  "rating": 5,
  "comment": "Experiência incrível!",
  "tags": ["divertido", "limpo", "seguro"],
  "visited_at": "2026-01-15",
  "visibility": "public",
  "photo_urls": ["https://..."]
}
```

---

## 📸 14. MEMÓRIAS

### Endpoints de Memórias

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/memories` | Listar |
| POST | `/memories` | Criar |
| GET | `/memories/{id}` | Detalhes |
| PUT | `/memories/{id}` | Atualizar |
| DELETE | `/memories/{id}` | Excluir |
| POST | `/memories/{id}/reactions` | Reagir |
| POST | `/memories/{id}/comments` | Comentar |

**Visibility:**
```typescript
type Visibility = 'private' | 'family' | 'collaborators' | 'public';
```

---

## 🔔 15. NOTIFICAÇÕES

### Endpoints de Notificações

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/notifications` | Listar |
| GET | `/notifications/unread-count` | Contador |
| PATCH | `/notifications/{id}/read` | Marcar lida |
| POST | `/notifications/read-all` | Marcar todas |
| DELETE | `/notifications/{id}` | Excluir |
| GET | `/notifications/settings` | Configurações |
| PUT | `/notifications/settings` | Atualizar config |

**Tipos de Notificação:**
| Type | Descrição |
|------|-----------|
| `family_invite` | Convite para família |
| `memory_reaction` | Reação em memória |
| `plan_reminder` | Lembrete de plano |
| `trending` | Nova experiência trending |
| `badge_earned` | Conquista desbloqueada |

---

## 📤 16. UPLOADS

### 16.1 Presigned URL
```http
POST /uploads/presign 🔒
```

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `type` | string | `memory`, `review`, `avatar`, `family_avatar` |
| `content_type` | string | `image/jpeg`, `image/png`, `image/webp` |
| `filename` | string | Nome do arquivo |

**Response:**
```json
{
  "data": {
    "upload_url": "https://s3.../presigned",
    "file_url": "https://cdn.../uuid.jpg",
    "key": "memories/user_id/uuid.jpg",
    "expires_at": "2026-01-18T02:00:00Z"
  }
}
```

**Fluxo de Upload:**
```typescript
// 1. Obter presigned URL
const { upload_url, file_url } = await api.post('/uploads/presign', {...});

// 2. Upload direto ao S3
await fetch(upload_url, { method: 'PUT', body: file });

// 3. Usar file_url na criação
await api.post('/memories', { image_url: file_url });
```

---

## 🔗 17. UTILIDADES

### 17.1 Resolver Deep Link
```http
GET /resolve/{code} (Público)
```

**Response:**
```json
{
  "data": {
    "type": "experience",
    "target_id": "uuid",
    "redirect_url": "/experiences/uuid"
  }
}
```

### 17.2 Denunciar Conteúdo
```http
POST /reports 🔒
```

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `type` | string | `experience`, `review`, `memory`, `user` |
| `target_id` | uuid | ID do conteúdo |
| `reason` | string | Ver tabela abaixo |
| `details` | string | Descrição adicional |

**Reasons:**
- `inappropriate` - Conteúdo impróprio
- `spam` - Spam
- `wrong_info` - Informação incorreta
- `closed` - Local fechado
- `harassment` - Assédio
- `other` - Outro

### 17.3 Gerar Link de Compartilhamento
```http
POST /share-links 🔒
```

```json
{ "type": "experience", "target_id": "uuid" }
```

---

## 📦 TypeScript Types

```typescript
// Copie para src/types/api.ts

interface ApiResponse<T> {
  data: T;
  meta: ApiMeta;
  errors: ApiError[] | null;
}

interface ApiMeta {
  success: boolean;
  next_cursor?: string;
  has_more?: boolean;
  cache_until?: string;
  unread_count?: number;
}

interface ApiError {
  code: string;
  message: string;
  field?: string;
}

interface User {
  id: string;
  phone: string;
  name: string;
  avatar: string | null;
  email: string | null;
  is_verified: boolean;
  onboarding_completed: boolean;
  primary_family_id: string | null;
  primary_city_id: string | null;
}

interface Category {
  id: string;
  name: string;
  slug: string;
  emoji: string;
  icon?: string;
  color?: string;
  experiences_count: number;
}

interface City {
  id: string;
  name: string;
  slug: string;
  state: string;
  country: string;
  lat: number;
  lng: number;
  display_name: string;
}

interface Experience {
  id: string;
  title: string;
  mission_title: string;
  cover_image: string;
  distance_km: number;
  price_level: PriceLevel;
  average_rating: number;
  reviews_count: number;
  is_saved: boolean;
  badges: string[];
  category: Category;
}

interface Dependent {
  id: string;
  name: string;
  birth_date?: string;
  age_group: AgeGroup;
  avatar?: string;
}

type PriceLevel = 'free' | 'moderate' | 'top';
type DurationBucket = 'quick' | 'half' | 'full';
type AgeGroup = 'baby' | 'toddler' | 'kid' | 'teen';
type PlanStatus = 'draft' | 'planned' | 'in_progress' | 'completed';
type TimeSlot = 'morning' | 'afternoon' | 'evening';
type Visibility = 'private' | 'family' | 'collaborators' | 'public';
```

---

## 🔧 Configuração do Axios

```typescript
// src/lib/api.ts
import axios from 'axios';

const api = axios.create({
  baseURL: 'https://api.valorsc.com.br/api/v1',
  headers: { 'Content-Type': 'application/json' },
});

// Interceptor: adicionar token
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('access_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

// Interceptor: refresh automático
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401 && !error.config._retry) {
      error.config._retry = true;
      try {
        const refresh = localStorage.getItem('refresh_token');
        const { data } = await api.post('/auth/refresh', { refresh_token: refresh });
        localStorage.setItem('access_token', data.data.tokens.access_token);
        localStorage.setItem('refresh_token', data.data.tokens.refresh_token);
        error.config.headers.Authorization = `Bearer ${data.data.tokens.access_token}`;
        return api(error.config);
      } catch {
        localStorage.clear();
        window.location.href = '/login';
      }
    }
    return Promise.reject(error);
  }
);

export default api;
```

---

## 📞 Contato

Dúvidas sobre a API? Entre em contato com o time de backend.

> **Atualizado em:** 2026-01-18 02:32 (UTC-3)
