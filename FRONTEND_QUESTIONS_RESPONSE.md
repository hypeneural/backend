# 📋 Respostas para o Time de Frontend

> **Data:** 2026-01-18  
> **Versão API:** v1  
> **Base URL:** `https://api.valorsc.com.br/api/v1/`

---

## 🔐 1. Autenticação

### 1.1 Formato do telefone no `/auth/otp/send`
**R:** Aceita **apenas números** com DDD (ex: `11999999999`). O backend adiciona o +55 automaticamente.

```json
// ✅ Correto
{ "phone": "11999999999" }

// ❌ Incorreto
{ "phone": "+5511999999999" }
```

### 1.2 Código OTP em DEV
**R:** Sim! Em ambiente de desenvolvimento, o código é retornado no campo `code`:

```json
{
  "data": {
    "message": "Code sent successfully.",
    "expires_at": "2026-01-18T05:32:36Z",
    "code": "816538"  // ← Só aparece em DEV
  }
}
```

### 1.3 Tempo de expiração do OTP
**R:** **5 minutos** (configurável em `.env`)

### 1.4 Expiração do Refresh Token
**R:** **14 dias**. O Access Token expira em **60 minutos**.

| Token | Expiração |
|-------|-----------|
| Access Token | 60 minutos |
| Refresh Token | 14 dias |

---

## 🏙️ 2. Cidades

### 2.1 Endpoint `/cities` ativo
**R:** ✅ Sim! Está ativo e funcional. Testado com sucesso:

```bash
curl https://api.valorsc.com.br/api/v1/cities
# Retorna: São Paulo e Rio de Janeiro
```

### 2.2 CORS Error
**R:** CORS está configurado. Se ainda tiver problemas, verifique se está usando:
- `Accept: application/json` no header
- Origem permitida (localhost ou domínio de produção)

> ⚠️ Já adicionamos configuração CORS no Laravel. Se persistir, verificar proxy/CDN.

### 2.3 Formato mínimo de busca
**R:** Mínimo **1 caractere**. Busca por nome OU estado.

```
GET /cities?q=São        → São Paulo
GET /cities?q=RJ         → Rio de Janeiro
GET /cities?q=Paulo      → São Paulo
GET /cities              → Retorna todas (por população desc)
```

### 2.4 Quantidade de cidades cadastradas
**R:** Atualmente **2 cidades** (São Paulo, Rio de Janeiro). Vamos adicionar mais conforme demanda.

---

## 🏠 3. Home Feed

### 3.1 `city_id` é obrigatório?
**R:** ✅ **Sim, obrigatório**. A home é personalizada por cidade.

### 3.2 Usuário sem cidade selecionada
**R:** Retorna erro 422:

```json
{
  "errors": [{"field": "city_id", "message": "O campo city_id é obrigatório"}]
}
```

**Sugestão Frontend:** Na primeira vez, mostrar modal de seleção de cidade.

### 3.3 Campo `chips`
**R:** São contadores rápidos para facilitar navegação. Exemplo:

```json
{
  "chips": {
    "adventure": 12,  // Experiências com tag aventura
    "rain": 8,        // Experiências para dia de chuva
    "baby": 15,       // Experiências baby-friendly
    "food": 23        // Experiências com comida
  }
}
```

### 3.4 `highlight` vs `trending`
**R:**
- **highlight** = Top 3 experiências editoriais (curadoria manual + trending)
- **trending** = Top 10 por trending_score (algoritmo automático)

---

## 🔍 4. Experiências / Busca

### 4.1 Múltiplas categorias
**R:** ✅ Sim! Use `categories[]`:

```
GET /experiences/search?city_id=UUID&categories[]=uuid1&categories[]=uuid2
```

### 4.2 Valores de `age_tags`
**R:** Aceita os seguintes valores:

| Valor | Idade | Descrição |
|-------|-------|-----------|
| `baby` | 0-1 | Bebês |
| `toddler` | 2-4 | Crianças pequenas |
| `kid` | 5-12 | Crianças |
| `teen` | 13-17 | Adolescentes |
| `all` | Todas | Universal |

Exemplo:
```
GET /experiences/search?city_id=UUID&age_tags[]=baby&age_tags[]=toddler
```

### 4.3 Valores de `duration_bucket`
**R:** Aceita strings, não números:

| Valor | Duração |
|-------|---------|
| `quick` | < 1 hora |
| `half` | 1-3 horas |
| `full` | 3+ horas |

### 4.4 Paginação por cursor
**R:** ✅ Sim! Retorna `next_cursor` em `meta`:

```json
{
  "data": { "results": [...] },
  "meta": {
    "success": true,
    "next_cursor": "eyJzY29yZSI6ODUuMn0=",
    "has_more": true
  }
}
```

Próxima página:
```
GET /experiences/search?city_id=UUID&cursor=eyJzY29yZSI6ODUuMn0=
```

---

## ❤️ 5. Favoritos

### 5.1 O que `/favorites` retorna?
**R:** Retorna **apenas experiências salvas**. Para listas, use `/favorite-lists`.

### 5.2 Como salvar
**R:** O `list_id` é **opcional**:

```json
// Salvar na lista geral (sem lista)
{ "experience_id": "uuid-experience" }

// Salvar em lista específica
{ "experience_id": "uuid-experience", "list_id": "uuid-lista" }
```

### 5.3 Lista "Geral" default
**R:** ❌ Não existe lista default. Experiências salvas sem `list_id` ficam "soltas". O frontend pode filtrar por `list_id = null` para mostrar "Salvos Gerais".

---

## 📋 6. Planos (Day Plans)

### 6.1 O que `/plans` retorna?
**R:** Retorna **todos os planos** do usuário. Use query params para filtrar:

```
GET /plans?status=planned        → Só planejados
GET /plans?status=draft          → Só rascunhos
GET /plans?sort=date             → Ordenar por data
```

### 6.2 Status possíveis
**R:**

| Status | Descrição |
|--------|-----------|
| `draft` | Rascunho (sem data) |
| `planned` | Planejado (com data futura) |
| `in_progress` | Em andamento (hoje) |
| `completed` | Concluído |

### 6.3 Mesma experiência em múltiplos planos
**R:** ✅ Sim! Pode adicionar a mesma experiência em quantos planos quiser.

---

## 📸 7. Memórias (Album)

### 7.1 O que `/memories` retorna?
**R:** Retorna memórias do **usuário E da família**:
- `visibility: 'family'` → Visível para toda família
- `visibility: 'private'` → Só o criador vê

### 7.2 Upload de imagem
**R:** ✅ Sim! Fluxo completo:

```typescript
// 1. Obter presigned URL
const { data } = await api.post('/uploads/presign', {
  type: 'memory',
  content_type: 'image/jpeg',
  filename: 'foto.jpg'
});

// 2. Upload direto ao S3
await fetch(data.upload_url, {
  method: 'PUT',
  body: imageFile,
  headers: { 'Content-Type': 'image/jpeg' }
});

// 3. Criar memória com URL
await api.post('/memories', {
  image_url: data.file_url,
  caption: 'Dia incrível!',
  experience_id: 'uuid',
  visibility: 'family'
});
```

---

## 🔔 8. Notificações

### 8.1 Paginação
**R:** ✅ Sim, paginado por cursor:

```
GET /notifications?limit=20
GET /notifications?cursor=xxx&limit=20
```

### 8.2 Push Notification
**R:** Endpoint para registrar device token existe mas FCM ainda não está 100% configurado:

```json
POST /notifications/register-device
{ "fcm_token": "xxx", "device_type": "android" }
```

### 8.3 Tipos de notificação

| Type | Descrição |
|------|-----------|
| `family_invite` | Convite para família |
| `memory_reaction` | Reação em memória |
| `plan_reminder` | Lembrete de plano |
| `trending` | Experiência em alta |
| `badge_earned` | Conquista desbloqueada |
| `plan_update` | Atualização em plano |
| `new_review` | Nova review em experiência salva |

---

## 💡 Respostas às Sugestões

### ✅ Endpoint `/health`
**Implementado!**

```
GET /health
{ "status": "ok", "timestamp": "2026-01-18T..." }
```

### ⏳ Endpoint `/config` (a fazer)
Vamos criar para retornar:
- Energy levels
- Vibe options
- Quick filters

### ✅ Filtros combinados
Já funcionam! Exemplo:

```
GET /experiences/search?city_id=UUID&weather=rain&age_tags[]=baby
```

### ⏳ Campo `is_open_now`
Não temos horários de funcionamento ainda. Precisamos de dados dos lugares.

### ✅ CORS
Configurado para:
- `localhost:*`
- `*.valorsc.com.br`
- `*.borafamilia.com.br`

---

## 📊 Mapeamento de Dados Estáticos

### Categories vs QuickFilters
**R:** São **coisas diferentes**:

| `categories` | `quickFilters` |
|--------------|----------------|
| Categorias principais de experiências | Atalhos rápidos de busca |
| Vem da API `/categories` | Podem vir da API `/config` |
| Ex: Parques, Museus | Ex: Dia de chuva, Baby-friendly |

**Sugestão:** Os chips na home (`adventure`, `rain`, `baby`) são quickFilters. Vamos criar endpoint `/config` para servir esses dados.

### EnergyLevels / Vibes
**R:** Atualmente é **configuração estática** do cliente. Vamos adicionar ao `/config`:

```json
GET /config
{
  "energy_levels": [
    { "value": 1, "emoji": "😴", "label": "Dia de algo levinho" },
    { "value": 2, "emoji": "🙂", "label": "Passeio tranquilo" },
    // ...
  ],
  "vibe_options": [...],
  "quick_filters": [...]
}
```

### Collections vs Favorite-Lists
**R:**
- **Collections** = Listas curadas pelo editorial (ex: "Pra chuva", "Baratinhos")
- **Favorite-Lists** = Listas criadas pelo usuário para organizar seus salvos

Vamos criar endpoint `/collections` separado.

---

## ✅ Dados de Teste Criados

### Cidades (2)
| Nome | Estado | ID |
|------|--------|-----|
| São Paulo | SP | `edbca93c-2f01-4e17-af0a-53b1ccb4bf90` |
| Rio de Janeiro | RJ | `1dd3042f-077f-4721-b1c0-661c0976bfd2` |

### Categorias (5)
| Nome | Emoji | Experiências |
|------|-------|--------------|
| Parques | 🌳 | 8 |
| Museus | 🏛️ | 8 |
| Aventura | 🎢 | 8 |
| Gastronomia | 🍕 | 8 |
| Natureza | 🏞️ | 8 |

### Experiências
- **40 por cidade** (80 total)
- **8 por categoria**
- Variados `price_level`, `duration`, `weather`, `age_tags`

### Usuário de Teste
```json
{
  "phone": "+5511999999999",
  "name": "Frontend Team",
  "user_id": "019bcf92-ecda-70a6-98ec-204362b9c61a",
  "family_id": "019bcf92-ecde-718f-a588-27021c63eb59"
}
```

**Para gerar novo token:**
```bash
php generate_token.php
```

---

## 📋 Próximos Passos Backend

| # | Tarefa | Prioridade |
|---|--------|------------|
| 1 | ✅ CORS configurado | Alta |
| 2 | ✅ Dados fake criados | Alta |
| 3 | ⏳ Criar `/config` endpoint | Média |
| 4 | ⏳ Criar `/collections` endpoint | Média |
| 5 | ⏳ Adicionar mais cidades | Baixa |
| 6 | ⏳ Configurar FCM push | Baixa |

---

## 🔗 Links Úteis

- **Documentação Scribe:** https://api.valorsc.com.br/docs
- **Postman Collection:** https://api.valorsc.com.br/docs/collection.json
- **OpenAPI Spec:** https://api.valorsc.com.br/docs/openapi.yaml

---

## 📞 Dúvidas?

Qualquer dúvida adicional, abra uma issue ou contate o time de backend.

> **Atualizado:** 2026-01-18 03:15 (UTC-3)
