# 📚 API Backend - Documentação Completa

> **Base URL:** `https://api.boradiafamilia.com.br/api/v1`  
> **Autenticação:** JWT Bearer Token  
> **Formato:** JSON  
> **Atualizado:** 2026-01-18

---

## 📑 Índice

1. [Autenticação](#1-autenticação)
2. [Configurações](#2-configurações)
3. [Cidades](#3-cidades)
4. [Categorias](#4-categorias)
5. [Collections](#5-collections)
6. [Clima](#6-clima)
7. [Experiências](#7-experiências)
8. [Favoritos](#8-favoritos)
9. [Família](#9-família)
10. [Planos](#10-planos)
11. [Memórias](#11-memórias)
12. [Notificações](#12-notificações)
13. [Headers e Rate Limits](#13-headers-e-rate-limits)

---

## 🔐 1. Autenticação

### POST /auth/otp/send
Envia código OTP via SMS.

```json
// Request
{ "phone": "11999999999" }

// Response 200
{
  "data": { "expires_in": 300, "resend_in": 60 },
  "meta": { "success": true }
}
```

### POST /auth/otp/verify
Verifica OTP e retorna tokens.

```json
// Request
{
  "phone": "11999999999",
  "code": "123456",
  "name": "João Silva"
}

// Response 200
{
  "data": {
    "access_token": "eyJhbGci...",
    "refresh_token": "abcdef123...",
    "expires_in": 3600,
    "user": {
      "id": "uuid",
      "name": "João Silva",
      "phone": "11999999999",
      "avatar": null,
      "primary_city_id": null,
      "onboarding_completed": false
    }
  },
  "meta": { "success": true, "is_new_user": true }
}
```

### POST /auth/refresh
Renova tokens.

```json
// Request
{ "refresh_token": "abcdef123..." }

// Response 200
{
  "data": {
    "access_token": "eyJnew...",
    "refresh_token": "newtoken...",
    "expires_in": 3600
  }
}
```

### GET /auth/me 🔒
Retorna dados do usuário logado.

```json
{
  "data": {
    "id": "uuid",
    "name": "João Silva",
    "phone": "11999999999",
    "avatar": "https://...",
    "primary_city_id": "uuid",
    "primary_city": { "id": "uuid", "name": "São Paulo", "state": "SP" },
    "onboarding_completed": true,
    "stats": {
      "xp": 450,
      "level": 2,
      "level_name": "Explorador",
      "streak_days": 5
    },
    "primary_family": {
      "id": "uuid",
      "name": "Família Silva",
      "members_count": 4
    }
  }
}
```

---

## ⚙️ 2. Configurações

### GET /health
```json
{ "status": "ok", "timestamp": "2026-01-18T12:00:00Z" }
```

### GET /config
Configurações estáticas (cache 24h).

```json
{
  "data": {
    "energy_levels": [
      { "value": 1, "emoji": "😴", "label": "Levinho" },
      { "value": 5, "emoji": "🚀", "label": "Energia máxima!" }
    ],
    "quick_filters": [
      { "id": "rain", "label": "Dia de chuva", "emoji": "🌧️" },
      { "id": "baby", "label": "Com bebê", "emoji": "👶" },
      { "id": "free", "label": "Grátis", "emoji": "🆓" }
    ],
    "age_groups": [
      { "value": "baby", "label": "Bebê", "age_range": "0-1 ano" },
      { "value": "kid", "label": "Criança", "age_range": "5-12 anos" }
    ],
    "price_levels": [
      { "value": "free", "label": "Grátis" },
      { "value": "moderate", "label": "R$ 20-80" }
    ]
  }
}
```

---

## 🏙️ 3. Cidades

### GET /cities?q=são

```json
{
  "data": [
    {
      "id": "uuid",
      "name": "São Paulo",
      "state": "SP",
      "lat": -23.5505,
      "lng": -46.6333,
      "display_name": "São Paulo, SP"
    }
  ]
}
```

### GET /cities/{id}

```json
{
  "data": {
    "id": "uuid",
    "name": "São Paulo",
    "state": "SP",
    "experiences_count": 320
  }
}
```

---

## 📂 4. Categorias

### GET /categories

```json
{
  "data": [
    {
      "id": "uuid",
      "name": "Parques",
      "slug": "parques",
      "emoji": "🌳",
      "color": "#4CAF50",
      "experiences_count": 45
    }
  ]
}
```

---

## 🎯 5. Collections

### GET /collections?featured=true

```json
{
  "data": [
    {
      "id": "uuid",
      "name": "Dia de Chuva",
      "slug": "dia-de-chuva",
      "emoji": "🌧️",
      "description": "Experiências para quando chove",
      "experiences_count": 12
    }
  ]
}
```

### GET /collections/{id}

```json
{
  "data": {
    "id": "uuid",
    "name": "Dia de Chuva",
    "experiences": [
      { "id": "uuid", "title": "MASP", "cover_image": "https://..." }
    ]
  }
}
```

---

## 🌤️ 6. Clima

### GET /weather/search?q=São Paulo

```json
{
  "data": {
    "locations": [
      {
        "id": 287907,
        "name": "Sao Paulo",
        "region": "Sao Paulo",
        "country": "Brazil",
        "lat": -23.53,
        "lon": -46.62,
        "display_name": "Sao Paulo, Sao Paulo, Brazil"
      }
    ]
  }
}
```

### GET /weather/current?q=São Paulo

```json
{
  "data": {
    "location": { "name": "Sao Paulo", "localtime": "2026-01-18 12:00" },
    "current": {
      "temp_c": 28.0,
      "feelslike_c": 31.2,
      "humidity": 65,
      "wind_kph": 15.5,
      "is_day": true,
      "condition": {
        "text": "Parcialmente nublado",
        "icon": "https://cdn.weatherapi.com/weather/64x64/day/116.png"
      }
    }
  }
}
```

### GET /weather/forecast?q=São Paulo&days=3

```json
{
  "data": {
    "location": { "name": "Sao Paulo" },
    "current": { "temp_c": 28 },
    "forecast": [
      {
        "date": "2026-01-18",
        "min_c": 20,
        "max_c": 32,
        "chance_of_rain": 40,
        "condition": { "text": "Possibilidade de chuva", "icon": "..." }
      }
    ]
  }
}
```

---

## 🎡 7. Experiências

### GET /experiences/search 🔒

**Query Params:**
| Param | Tipo | Descrição |
|-------|------|-----------|
| `city_id` | uuid | **Obrigatório** |
| `q` | string | Busca textual |
| `categories[]` | uuid[] | Filtro categorias |
| `price[]` | string[] | free, moderate, top |
| `duration` | string | quick, half, full |
| `age_tags[]` | string[] | baby, toddler, kid, teen |
| `sort` | string | trending, rating, distance |
| `cursor` | string | Paginação |

```json
{
  "data": {
    "results": [
      {
        "id": "uuid",
        "title": "Parque Ibirapuera",
        "cover_image": "https://...",
        "price_level": "free",
        "duration_bucket": "half",
        "average_rating": 4.8,
        "distance_km": 2.5,
        "is_saved": false,
        "category": { "name": "Parques", "emoji": "🌳" }
      }
    ],
    "total_estimate": 42
  },
  "meta": { "next_cursor": "abc", "has_more": true }
}
```

### GET /experiences/{id} 🔒

Detalhes completos incluindo opening_hours, tips, practical info, etc.

---

## ❤️ 8. Favoritos 🔒

### GET /favorites
Lista experiências salvas.

### POST /favorites
```json
{ "experience_id": "uuid", "list_id": "uuid" }
```

### DELETE /favorites/{experience_id}

---

## 👨‍👩‍👧‍👦 9. Família 🔒

### GET /family
Dados da família do usuário.

### POST /family/dependents
```json
{ "name": "Luca", "birthdate": "2020-05-15", "age_group": "toddler" }
```

### PUT /family/dependents/{id}

### DELETE /family/dependents/{id}

---

## 📅 10. Planos 🔒

### GET /plans?status=planned

### POST /plans
```json
{
  "title": "Passeio no Parque",
  "date": "2026-01-20",
  "experiences": ["uuid1", "uuid2"]
}
```

### POST /plans/{id}/complete
Marca como concluído e ganha XP.

---

## 📸 11. Memórias 🔒

### POST /uploads/presign
Obter URL para upload.

### POST /memories
```json
{
  "image_url": "https://s3.../memory.jpg",
  "caption": "Dia incrível!",
  "experience_id": "uuid"
}
```

---

## 🔔 12. Notificações 🔒

### GET /notifications

### GET /notifications/unread-count
```json
{ "data": { "count": 5 } }
```

### POST /notifications/read-all

---

## 🔧 13. Headers e Rate Limits

### Headers de Resposta

| Header | Descrição |
|--------|-----------|
| `X-Request-Id` | ID único para debug |
| `X-Idempotency-Replayed` | true se resposta foi do cache |

### Header Recomendado (Request)

```http
Idempotency-Key: uuid-único
```

Use em POST/PUT/DELETE para evitar duplicação.

### Rate Limits

| Endpoint | Limite |
|----------|--------|
| `/auth/otp/send` | 5 / 15min |
| `/auth/otp/verify` | 10 / 15min |
| `/weather/*` | 60 / min |
| `/experiences/search` | 60 / min |
| `/reviews` | 10 / hora |

---

## 💡 Dicas para Frontend

### Cache Sugerido

```typescript
const STALE_TIMES = {
  config: 24 * 60 * 60 * 1000,  // 24h
  categories: 60 * 60 * 1000,   // 1h
  weather: 5 * 60 * 1000,       // 5min
  home: 2 * 60 * 1000,          // 2min
};
```

### Paginação Cursor

```typescript
const { data, fetchNextPage, hasNextPage } = useInfiniteQuery({
  queryKey: ['experiences'],
  queryFn: ({ pageParam }) => api.get(`/experiences/search?cursor=${pageParam}`),
  getNextPageParam: (last) => last.meta.next_cursor,
});
```

### Envelope de Erro

```json
{
  "data": null,
  "meta": { "success": false },
  "errors": [{ "code": "NOT_FOUND", "message": "Experiência não encontrada" }]
}
```

---

> **Documentação Interativa:** https://api.boradiafamilia.com.br/docs
