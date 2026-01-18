# 📱 Bora Dia Família - API Completa para Frontend

> **Base URL**: `http://localhost:8000/api/v1`  
> **Auth**: Bearer Token no header `Authorization: Bearer {token}`  
> **Versão**: 2.0 (Atualizado 2026-01-18)

---

## 🚀 Antes de Começar - Executar Migrations

```bash
# No diretório do backend
cd backend

# Executar todas as migrations
php artisan migrate

# Rodar os seeders (dados de teste)
php artisan db:seed

# Iniciar o servidor
php artisan serve
```

**Após migrations, o backend estará disponível em:** `http://localhost:8000`

---

## 📋 Índice de Endpoints (60+ endpoints)

| # | Área | Endpoints | Autenticação |
|---|------|-----------|--------------|
| 1 | [Auth](#1-autenticação) | 5 | Parcial |
| 2 | [Onboarding](#2-onboarding) | 2 | 🔒 |
| 3 | [Usuário](#3-usuário) | 5 | 🔒 |
| 4 | [Home](#4-home) | 1 | 🔒 |
| 5 | [Busca](#5-busca) | 1 | 🔒 |
| 6 | [Mapa](#6-mapa) | 1 | 🔒 |
| 7 | [Experiências](#7-experiências) | 1 | 🔒 |
| 8 | [Categorias](#8-categorias) | 1 | Público |
| 9 | [Cidades](#9-cidades) | 2 | Público |
| 10 | [Favoritos](#10-favoritos) | 6 | 🔒 |
| 11 | [Família](#11-família) | 11 | 🔒 |
| 12 | [Planos](#12-planos) | 11 | 🔒 |
| 13 | [Reviews](#13-reviews) | 6 | 🔒 |
| 14 | [Memórias](#14-memórias) | 7 | 🔒 |
| 15 | [Notificações](#15-notificações) | 8 | 🔒 |
| 16 | [Uploads](#16-uploads) | 2 | 🔒 |
| 17 | [Utilidades](#17-utilidades) | 3 | Misto |

---

## 1. Autenticação

### 1.1 `POST /auth/otp/send`
> Enviar código OTP para telefone

**Rate Limit:** 1/min por telefone

```json
// Request
{ "phone": "11999999999" }

// Response 200
{
  "data": {
    "message": "Código enviado com sucesso",
    "expires_at": "2026-01-18T01:50:00Z",
    "code": "123456"  // ⚠️ Apenas em dev
  },
  "meta": { "success": true },
  "errors": null
}
```

---

### 1.2 `POST /auth/otp/verify`
> Verificar OTP e autenticar

**Lógica:**
1. Valida código (5 tentativas máx)
2. Se usuário não existe → cria user + family
3. Gera JWT (1h) + refresh token (14d)

```json
// Request
{
  "phone": "11999999999",
  "code": "123456",
  "name": "João Silva",       // Obrigatório para novos
  "referral_code": "ABC123"   // Opcional
}

// Response 200/201
{
  "data": {
    "user": {
      "id": "uuid",
      "phone": "+5511999999999",
      "name": "João Silva",
      "avatar": null,
      "is_verified": true,
      "onboarding_completed": false,   // 🆕
      "primary_family_id": "uuid",     // 🆕
      "primary_city_id": null          // 🆕
    },
    "tokens": {
      "access_token": "eyJ...",
      "refresh_token": "abc...",
      "token_type": "bearer",
      "expires_in": 3600
    },
    "is_new_user": true
  }
}
```

**💡 Sugestão Frontend:**
```typescript
// Decidir navegação após login
if (!user.onboarding_completed) {
  navigate('/onboarding');
} else {
  navigate('/home');
}
```

---

### 1.3 `POST /auth/refresh`
> Renovar tokens (sem auth header)

```json
// Request
{ "refresh_token": "abc..." }

// Response 200
{
  "data": {
    "tokens": {
      "access_token": "eyJ...",
      "refresh_token": "xyz...",
      "expires_in": 3600
    }
  }
}
```

---

### 1.4 `POST /auth/logout` 🔒
> Revogar tokens

```json
// Request
{
  "refresh_token": "abc...",
  "all_devices": false
}
```

---

### 1.5 `GET /auth/me` 🔒
> Dados do usuário logado

```json
// Response
{
  "data": {
    "id": "uuid",
    "phone": "+5511999999999",
    "name": "João Silva",
    "avatar": "https://...",
    "email": null,
    "is_verified": true,
    "stats": {
      "xp": 500,
      "level": 2,
      "streak_days": 5
    },
    "primary_family": {
      "id": "uuid",
      "name": "Família Silva"
    }
  }
}
```

---

## 2. Onboarding

### 2.1 `GET /onboarding/status` 🔒
> Verificar progresso do onboarding

**Lógica:** Verifica campos preenchidos do usuário/família

```json
// Response
{
  "data": {
    "completed": false,
    "steps_completed": ["name", "family"],
    "missing_steps": ["preferences", "categories"]
  }
}
```

---

### 2.2 `POST /onboarding/complete` 🔒
> Finalizar onboarding completo

**Lógica:**
1. Atualiza nome do usuário
2. Cria/atualiza família
3. Salva preferências (distância, preço)
4. Associa categorias favoritas
5. Cria dependentes
6. Marca `onboarding_completed = true`

```json
// Request
{
  "name": "João Silva",
  "family_name": "Família Silva",
  "favorite_categories": ["uuid1", "uuid2", "uuid3"],
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

// Response 201
{
  "data": {
    "message": "Onboarding completed successfully!",
    "user": {
      "id": "uuid",
      "name": "João Silva",
      "onboarding_completed": true
    }
  }
}
```

**💡 Sugestão - Fluxo de Telas:**
```
WelcomeScreen → name
FamilyScreen → family_name + dependents
CategoriesScreen → favorite_categories (mín 3)
PreferencesScreen → max_distance_km + default_price
```

---

## 3. Usuário

### 3.1 `PUT /users/me` 🔒
> Atualizar perfil

```json
// Request
{
  "name": "João Silva Atualizado",
  "email": "joao@email.com"
}
```

---

### 3.2 `PATCH /users/me/avatar` 🔒
> Atualizar avatar

```json
// Request
{ "avatar_url": "https://cdn.../avatar.jpg" }
```

---

### 3.3 `POST /users/me/location` 🔒
> Atualizar localização

```json
// Request
{
  "lat": -23.5874,
  "lng": -46.6576,
  "city_id": "uuid"
}
```

---

### 3.4 `GET /users/me/stats` 🔒
> Estatísticas de gamificação

**Regras de XP:**
| Ação | XP | Limite/dia |
|------|-----|------------|
| Onboarding | 100 | 1× |
| Salvar exp | 5 | 20 |
| Review | 25 | 5 |
| Memória | 10 | 10 |
| Plano completo | 50 | - |
| Referral | 100 | - |

```json
// Response
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
      { "slug": "explorer", "name": "Explorador", "earned_at": "..." }
    ]
  }
}
```

---

### 3.5 `DELETE /users/me` 🔒
> Desativar conta

```json
// Request
{ "confirmation": "DELETE" }
```

---

## 4. Home

### `GET /home?city_id={uuid}&lat={float}&lng={float}` 🔒
> Endpoint unificado da home

**Lógica:**
1. Atualiza localização do usuário
2. Busca trending do cache
3. Calcula distâncias
4. Conta facetas (chips)

```json
// Response
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
        "category": { "name": "Parques", "emoji": "🌳" }
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
      { "id": "uuid", "title": "Domingo", "date": "2026-01-20" }
    ]
  }
}
```

---

## 5. Busca

### `GET /experiences/search` 🔒
> Busca com filtros e cursor pagination

**Query Params:**
| Param | Tipo | Descrição |
|-------|------|-----------|
| `city_id` | uuid | **Obrigatório** |
| `q` | string | Busca textual |
| `categories[]` | uuid[] | Filtro categorias |
| `price[]` | string[] | `free`, `moderate`, `top` |
| `duration` | string | `quick`, `half`, `full` |
| `age_tags[]` | string[] | `baby`, `toddler`, `kid`, `teen` |
| `weather` | string | `sun`, `rain`, `any` |
| `has_reviews` | bool | Apenas com reviews |
| `min_rating` | float | Rating mínimo |
| `sort` | string | `trending`, `rating`, `distance`, `saves` |
| `cursor` | string | Próxima página |
| `limit` | int | 1-50 (default: 20) |

```json
// Response
{
  "data": {
    "results": [ /* ExperienceCard[] */ ],
    "facets": {
      "categories": [{ "id": "uuid", "name": "Parques", "count": 15 }],
      "price_level": [{ "value": "free", "count": 23 }],
      "age_tags": [{ "value": "kid", "count": 45 }]
    },
    "applied_filters": { "categories": ["uuid1"] },
    "total_estimate": 156
  },
  "meta": {
    "next_cursor": "eyJ...",
    "has_more": true
  }
}
```

---

## 6. Mapa

### `GET /map/experiences?bbox={w,s,e,n}&zoom={int}` 🔒
> Experiências no viewport

**Lógica:**
- `zoom >= 14`: pontos individuais
- `zoom < 14`: clusters

```json
// Response
{
  "data": {
    "points": [
      { "id": "uuid", "lat": -23.58, "lng": -46.65, "title": "..." }
    ],
    "clusters": [
      { "lat": -23.55, "lng": -46.63, "count": 12 }
    ]
  }
}
```

---

## 7. Experiências

### `GET /experiences/{id}` 🔒
> Detalhes completos

**Headers:** `ETag` para cache condicional

```json
// Response (resumido)
{
  "data": {
    "id": "uuid",
    "title": "Piquenique no Ibirapuera",
    "mission_title": "Descubra a magia",
    "summary": "...",
    "category": { "name": "Parques", "emoji": "🌳", "color": "#22c55e" },
    "badges": ["staff_pick", "trending"],
    "age_tags": ["toddler", "kid", "teen"],
    "duration": { "label": "1-2h", "minutes_min": 60, "minutes_max": 120 },
    "price": { "level": "free", "label": "Grátis" },
    "practical": { "parking": true, "bathroom": true, "stroller": true },
    "tips": ["Chegue cedo", "Leve protetor solar"],
    "location": { "address": "Av. Pedro Álvares Cabral", "neighborhood": "Vila Mariana" },
    "coords": { "lat": -23.5874, "lng": -46.6576 },
    "images": { "cover": "url", "gallery": ["url1", "url2"] },
    "stats": { "saves_count": 234, "reviews_count": 56, "average_rating": 4.7 },
    "recent_reviews": [ /* Review[] */ ],
    "related": [ /* ExperiencePreview[] */ ],
    "is_saved": false,
    "user_review": null,
    "distance_km": 3.2
  }
}
```

---

## 8. Categorias

### `GET /categories` (Público)
> Listar todas

**Cache:** 24h

```json
// Response
{
  "data": [
    {
      "id": "uuid",
      "name": "Parques",
      "slug": "parques",
      "emoji": "🌳",
      "color": "#22c55e",
      "experiences_count": 45
    }
  ],
  "meta": { "cache_until": "2026-01-19T..." }
}
```

---

## 9. Cidades

### 9.1 `GET /cities?q={query}&limit={int}` (Público)
> Buscar cidades

```json
// Response
{
  "data": [
    { "id": "uuid", "name": "São Paulo", "state": "SP", "display_name": "São Paulo, SP" }
  ]
}
```

### 9.2 `GET /cities/{id}` (Público)
> Detalhes da cidade

---

## 10. Favoritos

| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `/favorites` | GET | Listar salvos |
| `/favorites` | POST | Salvar experiência |
| `/favorites/{exp_id}` | DELETE | Remover |
| `/favorite-lists` | POST | Criar lista |
| `/favorite-lists/{id}` | PUT | Atualizar lista |
| `/favorite-lists/{id}` | DELETE | Excluir lista |

```json
// POST /favorites
{ "experience_id": "uuid", "list_id": "uuid" }
```

---

## 11. Família

| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `/family` | GET | Dados da família |
| `/family` | POST | Criar família |
| `/family` | PUT | Atualizar |
| `/family/invite` | POST | Gerar convite |
| `/family/join` | POST | Entrar com código |
| `/family/leave` | POST | Sair da família |
| `/family/{id}/members/{userId}` | DELETE | Remover membro |
| `/family/dependents` | GET | Listar dependentes |
| `/family/dependents` | POST | Criar dependente |
| `/family/dependents/{id}` | PUT | Atualizar |
| `/family/dependents/{id}` | DELETE | Remover |

```json
// POST /family/invite
{ "max_uses": 5, "expires_in_days": 7 }

// Response
{ "data": { "code": "BORA-XK9MP2", "expires_at": "..." } }
```

---

## 12. Planos

| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `/plans` | GET | Listar planos |
| `/plans` | POST | Criar |
| `/plans/{id}` | GET | Detalhes |
| `/plans/{id}` | PUT | Atualizar |
| `/plans/{id}` | DELETE | Excluir |
| `/plans/{id}/complete` | POST | Marcar concluído |
| `/plans/{id}/duplicate` | POST | Duplicar |
| `/plans/{id}/experiences` | POST | Adicionar exp |
| `/plans/{id}/experiences/{expId}` | PUT | Atualizar ordem |
| `/plans/{id}/experiences/{expId}` | DELETE | Remover exp |
| `/plans/{id}/collaborators` | POST | Convidar |
| `/plans/{id}/collaborators/{userId}` | DELETE | Remover |

```json
// POST /plans
{
  "title": "Domingo no Parque",
  "date": "2026-01-20",
  "experience_ids": ["uuid1", "uuid2"]
}

// POST /plans/{id}/experiences
{
  "experience_id": "uuid",
  "time_slot": "morning",
  "notes": "Levar lanche"
}
```

---

## 13. Reviews

| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `/experiences/{id}/reviews` | GET | Listar |
| `/experiences/{id}/reviews` | POST | Criar |
| `/reviews/{id}` | PUT | Atualizar |
| `/reviews/{id}` | DELETE | Excluir |
| `/reviews/{id}/helpful` | POST | Marcar útil |

```json
// POST /experiences/{id}/reviews
{
  "rating": 5,
  "comment": "Adoramos!",
  "tags": ["divertido", "limpo"],
  "visited_at": "2026-01-15",
  "photo_urls": ["https://..."]
}
```

---

## 14. Memórias

| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `/memories` | GET | Listar |
| `/memories` | POST | Criar |
| `/memories/{id}` | GET | Detalhes |
| `/memories/{id}` | PUT | Atualizar |
| `/memories/{id}` | DELETE | Excluir |
| `/memories/{id}/reactions` | POST | Reagir |
| `/memories/{id}/comments` | POST | Comentar |

```json
// POST /memories
{
  "image_url": "https://...",
  "caption": "Dia incrível!",
  "experience_id": "uuid",
  "visibility": "family"
}

// POST /memories/{id}/reactions
{ "emoji": "❤️" }
```

---

## 15. Notificações

| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `/notifications` | GET | Listar |
| `/notifications/unread-count` | GET | Contador |
| `/notifications/{id}/read` | PATCH | Marcar lida |
| `/notifications/read-all` | POST | Marcar todas |
| `/notifications/{id}` | DELETE | Excluir |
| `/notifications/settings` | GET | Config |
| `/notifications/settings` | PUT | Atualizar config |

```json
// GET /notifications
{
  "data": {
    "notifications": [
      {
        "id": "uuid",
        "type": "family_invite",
        "title": "Convite para família",
        "body": "Maria te convidou",
        "is_read": false,
        "data": { "family_id": "uuid" }
      }
    ]
  },
  "meta": { "unread_count": 5 }
}

// PUT /notifications/settings
{
  "push_enabled": true,
  "types": {
    "family_invite": true,
    "plan_reminder": true,
    "trending": false
  },
  "quiet_hours": { "enabled": true, "start": "22:00", "end": "08:00" }
}
```

---

## 16. Uploads

### 16.1 `POST /uploads/presign` 🔒
> Obter URL para upload direto ao S3

```json
// Request
{
  "type": "memory",
  "content_type": "image/jpeg",
  "filename": "photo.jpg"
}

// Response
{
  "data": {
    "upload_url": "https://s3.../presigned",
    "file_url": "https://cdn.../uuid.jpg",
    "expires_at": "2026-01-18T02:00:00Z"
  }
}
```

**💡 Fluxo de Upload:**
```typescript
// 1. Obter presigned URL
const { upload_url, file_url } = await api.post('/uploads/presign', {...});

// 2. Upload direto ao S3
await fetch(upload_url, { method: 'PUT', body: file });

// 3. Usar file_url no POST
await api.post('/memories', { image_url: file_url, ... });
```

---

## 17. Utilidades

### 17.1 `GET /resolve/{code}` (Público)
> Resolver deep link

```json
// Response
{
  "data": {
    "type": "experience",
    "target_id": "uuid",
    "redirect_url": "/experiences/uuid"
  }
}
```

### 17.2 `POST /reports` 🔒
> Denunciar conteúdo

```json
// Request
{
  "type": "experience",
  "target_id": "uuid",
  "reason": "wrong_info",
  "details": "Esse lugar fechou"
}
```

### 17.3 `POST /share-links` 🔒
> Gerar link de compartilhamento

```json
// Request
{ "type": "experience", "target_id": "uuid" }

// Response
{ "data": { "code": "Xk9mP2", "short_url": "https://bdf.app/s/Xk9mP2" } }
```

---

## 📦 TypeScript Types

```typescript
// Copie para seu projeto
interface ApiResponse<T> {
  data: T;
  meta: { success: boolean; next_cursor?: string; has_more?: boolean };
  errors: Array<{ code: string; message: string }> | null;
}

interface User {
  id: string;
  phone: string;
  name: string;
  avatar: string | null;
  onboarding_completed: boolean;
  primary_family_id: string | null;
  primary_city_id: string | null;
}

interface Experience {
  id: string;
  title: string;
  mission_title: string;
  cover_image: string;
  distance_km: number;
  price_level: 'free' | 'moderate' | 'top';
  average_rating: number;
  is_saved: boolean;
  category: Category;
}

interface Category {
  id: string;
  name: string;
  emoji: string;
  color?: string;
}

type AgeTag = 'baby' | 'toddler' | 'kid' | 'teen' | 'all';
type PlanStatus = 'draft' | 'planned' | 'in_progress' | 'completed';
type Visibility = 'private' | 'family' | 'collaborators' | 'public';
```

---

## ✅ Checklist de Integração

### Passo 1: Backend Setup
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret

# Criar banco MariaDB e configurar .env
php artisan migrate
php artisan db:seed
php artisan serve
```

### Passo 2: Testar Endpoints

```bash
# 1. Testar OTP
curl -X POST http://localhost:8000/api/v1/auth/otp/send \
  -H "Content-Type: application/json" \
  -d '{"phone":"11999999999"}'

# 2. Verificar OTP (usar código retornado)
curl -X POST http://localhost:8000/api/v1/auth/otp/verify \
  -H "Content-Type: application/json" \
  -d '{"phone":"11999999999","code":"123456","name":"Teste"}'

# 3. Listar categorias
curl http://localhost:8000/api/v1/categories

# 4. Endpoint autenticado
curl http://localhost:8000/api/v1/home?city_id=UUID \
  -H "Authorization: Bearer SEU_TOKEN"
```

### Passo 3: Configurar Frontend

```typescript
// api.ts
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8000/api/v1',
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('access_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

export default api;
```

---

## ❓ Perguntas Pendentes

1. **Push Notifications**: Vão usar FCM? Precisamos endpoint de registro de device token.

2. **Filtros adicionais**: Precisam de filtros por:
   - Bairro/região?
   - Dia da semana (aberto domingo)?
   - Acessibilidade específica?

3. **Polling interval**: A cada quantos segundos o polling de `/notifications/unread-count`?

4. **Deep links**: O formato `bdf://experience/{id}` funciona para app scheme?

5. **Batch operations**: Precisam de operações em lote (ex: marcar múltiplas notificações como lidas)?

---

## 📞 Suporte

Dúvidas sobre a API? Abra uma issue ou contate o backend team.

> **Última atualização:** 2026-01-18 01:45 (UTC-3)
