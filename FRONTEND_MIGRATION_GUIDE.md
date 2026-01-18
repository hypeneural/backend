# 🚀 Guia de Migração Frontend - Mock para API Real

> **Data:** 2026-01-18  
> **Base URL:** `https://api.valorsc.com.br/api/v1/`  
> **Documentação Interativa:** https://api.valorsc.com.br/docs

---

## 📊 Resumo: Endpoints por Tela

| Tela | Endpoint Principal | Status |
|------|-------------------|--------|
| HomeScreen | `GET /home` | ✅ Pronto |
| SearchScreen | `GET /experiences/search` | ✅ Pronto |
| SavedScreen | `GET /favorites` | ✅ Pronto |
| PlanScreen | `GET /plans` | ✅ Pronto |
| MapScreen | `GET /map/experiences` | ✅ Pronto |
| ExploreScreen | `GET /experiences/search` | ✅ Pronto |
| ExperienceDetailScreen | `GET /experiences/{id}` | ✅ Pronto |
| ExperienceReviewScreen | `GET /experiences/{id}/reviews` | ✅ Pronto |
| NotificationsScreen | `GET /notifications` | ✅ Pronto |
| FamilyAlbumScreen | `GET /memories` | ✅ Pronto |
| CalendarScreen | `GET /plans?sort=date` | ✅ Pronto |
| EditFamilyMemberScreen | `PUT /family/dependents/{id}` | ✅ Pronto |

---

## 🔍 1. SearchScreen / ExploreScreen

### Endpoint
```http
GET /experiences/search
```

### Query Params (Filtros)

| Param | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `city_id` | uuid | ✅ | ID da cidade |
| `q` | string | ❌ | Texto de busca (fulltext) |
| `categories[]` | uuid[] | ❌ | IDs das categorias |
| `price[]` | string[] | ❌ | `free`, `moderate`, `top` |
| `duration` | string | ❌ | `quick` (<1h), `half` (1-3h), `full` (3h+) |
| `age_tags[]` | string[] | ❌ | `baby`, `toddler`, `kid`, `teen` |
| `weather` | string | ❌ | `sun`, `rain`, `any` |
| `energy` | number | ❌ | 1-5 (nível de energia) |
| `min_rating` | number | ❌ | Rating mínimo (ex: 4.0) |
| `sort` | string | ❌ | `trending`, `rating`, `distance`, `saves` |
| `cursor` | string | ❌ | Cursor para próxima página |
| `limit` | number | ❌ | 1-50 (padrão: 20) |

### Exemplo de Request com Filtros
```typescript
// Buscar experiências: grátis, para criança, dia de chuva
GET /experiences/search?
  city_id=edbca93c-2f01-4e17-af0a-53b1ccb4bf90&
  price[]=free&
  age_tags[]=kid&
  weather=rain&
  sort=trending&
  limit=20
```

### Response Schema
```typescript
interface SearchResponse {
  data: {
    results: Experience[];
    facets: {
      categories: { id: string; name: string; emoji: string; count: number }[];
      price_level: { value: string; label: string; count: number }[];
      age_tags: { value: string; label: string; count: number }[];
      duration: { value: string; label: string; count: number }[];
    };
    applied_filters: Record<string, any>;
    total_estimate: number;
  };
  meta: {
    success: boolean;
    next_cursor: string | null;
    has_more: boolean;
  };
}

interface Experience {
  id: string;
  title: string;
  mission_title: string;
  cover_image: string;
  distance_km: number;
  price_level: 'free' | 'moderate' | 'top';
  duration_bucket: 'quick' | 'half' | 'full';
  average_rating: number;
  reviews_count: number;
  saves_count: number;
  is_saved: boolean;
  badges: string[];
  category: {
    id: string;
    name: string;
    emoji: string;
  };
}
```

### React Query Hook Sugerido
```typescript
// src/data/hooks/useExperienceSearch.ts
export function useExperienceSearch(filters: SearchFilters) {
  return useInfiniteQuery({
    queryKey: ['experiences', 'search', filters],
    queryFn: async ({ pageParam }) => {
      const params = new URLSearchParams();
      params.set('city_id', filters.cityId);
      
      if (filters.query) params.set('q', filters.query);
      if (filters.duration) params.set('duration', filters.duration);
      if (filters.weather) params.set('weather', filters.weather);
      if (filters.energy) params.set('energy', String(filters.energy));
      if (filters.minRating) params.set('min_rating', String(filters.minRating));
      if (filters.sort) params.set('sort', filters.sort);
      if (pageParam) params.set('cursor', pageParam);
      
      filters.categories?.forEach(id => params.append('categories[]', id));
      filters.prices?.forEach(p => params.append('price[]', p));
      filters.ageTags?.forEach(t => params.append('age_tags[]', t));
      
      const { data } = await api.get(`/experiences/search?${params}`);
      return data;
    },
    getNextPageParam: (lastPage) => 
      lastPage.meta.has_more ? lastPage.meta.next_cursor : undefined,
    initialPageParam: undefined,
  });
}
```

### Lógica de Aplicação de Filtros
```typescript
// Componente de filtros
const [filters, setFilters] = useState<SearchFilters>({
  cityId: user.primaryCityId,
  sort: 'trending',
});

// Quick filters mapeiam para search params
const quickFilterMapping = {
  adventure: { vibe: 'adventure' },
  rain: { weather: 'rain' },
  baby: { ageTags: ['baby'] },
  free: { prices: ['free'] },
  food: { hasFood: true },
  quick: { duration: 'quick' },
};

const applyQuickFilter = (filterId: string) => {
  setFilters(prev => ({
    ...prev,
    ...quickFilterMapping[filterId],
  }));
};
```

---

## 💾 2. SavedScreen

### Endpoints
```http
GET /favorites                    # Listar experiências salvas
GET /favorite-lists               # Listar listas do usuário
POST /favorites                   # Salvar experiência
DELETE /favorites/{experience_id} # Remover salvos
```

### Query Params para /favorites

| Param | Tipo | Descrição |
|-------|------|-----------|
| `list_id` | uuid | Filtrar por lista específica |
| `sort` | string | `saved_at` (padrão), `name`, `distance` |
| `cursor` | string | Paginação |

### Response Schema
```typescript
interface FavoritesResponse {
  data: {
    experiences: SavedExperience[];
    lists: FavoriteList[];
  };
  meta: { success: boolean; next_cursor?: string; has_more: boolean };
}

interface SavedExperience {
  id: string;
  experience: Experience;
  list_id: string | null;     // null = sem lista (salvos gerais)
  saved_at: string;
}

interface FavoriteList {
  id: string;
  name: string;
  emoji: string;
  experiences_count: number;
}
```

### React Query Hooks
```typescript
// Hook para listar salvos
export function useFavorites(listId?: string) {
  return useQuery({
    queryKey: ['favorites', listId],
    queryFn: async () => {
      const params = listId ? `?list_id=${listId}` : '';
      const { data } = await api.get(`/favorites${params}`);
      return data.data;
    },
  });
}

// Mutation para salvar/remover
export function useToggleFavorite() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async ({ experienceId, isSaved }: { experienceId: string; isSaved: boolean }) => {
      if (isSaved) {
        await api.delete(`/favorites/${experienceId}`);
      } else {
        await api.post('/favorites', { experience_id: experienceId });
      }
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['favorites'] });
      queryClient.invalidateQueries({ queryKey: ['experiences'] });
    },
  });
}
```

---

## 📋 3. PlanScreen / CalendarScreen

### Endpoints
```http
GET /plans                        # Listar planos
POST /plans                       # Criar plano
GET /plans/{id}                   # Detalhes do plano
PUT /plans/{id}                   # Atualizar
DELETE /plans/{id}                # Excluir
POST /plans/{id}/complete         # Marcar concluído
POST /plans/{id}/experiences      # Adicionar experiência
```

### Query Params para /plans

| Param | Tipo | Descrição |
|-------|------|-----------|
| `status` | string | `draft`, `planned`, `in_progress`, `completed` |
| `sort` | string | `date`, `created_at` |
| `from_date` | date | Filtrar a partir de |
| `to_date` | date | Filtrar até |

### Response Schema
```typescript
interface PlansResponse {
  data: Plan[];
  meta: { success: boolean };
}

interface Plan {
  id: string;
  title: string;
  date: string | null;         // null para drafts
  time_slot: 'morning' | 'afternoon' | 'evening' | null;
  status: 'draft' | 'planned' | 'in_progress' | 'completed';
  experiences_count: number;
  estimated_duration_min: number;
  cover_image: string | null;
  collaborators: { id: string; name: string; avatar: string }[];
  experiences: PlanExperience[];
  created_at: string;
}

interface PlanExperience {
  id: string;
  experience: Experience;
  order: number;
  notes: string | null;
  completed: boolean;
}
```

### React Query Hooks
```typescript
// Listar planos
export function usePlans(status?: string) {
  return useQuery({
    queryKey: ['plans', status],
    queryFn: async () => {
      const params = status ? `?status=${status}` : '';
      const { data } = await api.get(`/plans${params}`);
      return data.data;
    },
  });
}

// Planos para o calendário (agrupados por data)
export function useCalendarPlans(month: Date) {
  return useQuery({
    queryKey: ['plans', 'calendar', month.toISOString()],
    queryFn: async () => {
      const from = startOfMonth(month).toISOString().split('T')[0];
      const to = endOfMonth(month).toISOString().split('T')[0];
      const { data } = await api.get(`/plans?from_date=${from}&to_date=${to}&sort=date`);
      
      // Agrupar por data
      return groupBy(data.data, (plan) => plan.date);
    },
  });
}

// Criar plano
export function useCreatePlan() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async (plan: CreatePlanDto) => {
      const { data } = await api.post('/plans', plan);
      return data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['plans'] });
    },
  });
}
```

### Lógica do CalendarScreen
```typescript
// Agrupar planos por data para mostrar no calendário
const plansByDate = useMemo(() => {
  const grouped: Record<string, Plan[]> = {};
  plans.forEach(plan => {
    if (plan.date) {
      const key = plan.date.split('T')[0];
      grouped[key] = grouped[key] || [];
      grouped[key].push(plan);
    }
  });
  return grouped;
}, [plans]);

// Verificar se data tem planos
const hasPlansOnDate = (date: Date) => {
  const key = format(date, 'yyyy-MM-dd');
  return (plansByDate[key]?.length || 0) > 0;
};
```

---

## 🗺️ 4. MapScreen

### Endpoint
```http
GET /map/experiences
```

### Query Params

| Param | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `bbox` | string | ✅ | Bounding box: `west,south,east,north` |
| `zoom` | number | ✅ | Nível de zoom (1-22) |
| `categories[]` | uuid[] | ❌ | Filtrar categorias |
| `limit` | number | ❌ | Máx 200 (padrão: 100) |

### Response Schema
```typescript
interface MapResponse {
  data: {
    points: MapPoint[];      // Pontos individuais (zoom >= 14)
    clusters: MapCluster[];  // Clusters (zoom < 14)
  };
  meta: { success: boolean };
}

interface MapPoint {
  id: string;
  lat: number;
  lng: number;
  title: string;
  cover_image: string;
  category_emoji: string;
  price_level: string;
  average_rating: number;
}

interface MapCluster {
  lat: number;
  lng: number;
  count: number;
  bounds: { west: number; south: number; east: number; north: number };
}
```

### React Query Hook
```typescript
export function useMapExperiences(bounds: MapBounds, zoom: number, categories?: string[]) {
  return useQuery({
    queryKey: ['map', 'experiences', bounds, zoom, categories],
    queryFn: async () => {
      const params = new URLSearchParams();
      params.set('bbox', `${bounds.west},${bounds.south},${bounds.east},${bounds.north}`);
      params.set('zoom', String(zoom));
      categories?.forEach(id => params.append('categories[]', id));
      
      const { data } = await api.get(`/map/experiences?${params}`);
      return data.data;
    },
    staleTime: 30000, // Cache por 30s
    enabled: !!bounds, // Só buscar quando tiver bounds
  });
}
```

### Lógica do MiniMap
```typescript
// Para o componente MiniMap que mostra preview
const useMiniMapExperiences = (cityId: string) => {
  const { data: homeData } = useHomeFeed(cityId);
  
  // Usar experiências do home feed para o mini mapa
  const points = useMemo(() => 
    homeData?.trending?.map(exp => ({
      id: exp.id,
      lat: exp.coords?.lat,
      lng: exp.coords?.lng,
      emoji: exp.category.emoji,
    })).filter(p => p.lat && p.lng) || [],
    [homeData]
  );
  
  return points;
};
```

---

## 📸 5. FamilyAlbumScreen (Memórias)

### Endpoints
```http
GET /memories                     # Listar memórias
POST /memories                    # Criar memória
GET /memories/{id}                # Detalhes
DELETE /memories/{id}             # Excluir
POST /memories/{id}/reactions     # Reagir
POST /memories/{id}/comments      # Comentar
```

### Query Params para /memories

| Param | Tipo | Descrição |
|-------|------|-----------|
| `family_id` | uuid | Filtrar por família |
| `experience_id` | uuid | Filtrar por experiência |
| `visibility` | string | `private`, `family` |
| `sort` | string | `recent`, `reactions` |
| `cursor` | string | Paginação |

### Response Schema
```typescript
interface MemoriesResponse {
  data: Memory[];
  meta: { success: boolean; next_cursor?: string; has_more: boolean };
}

interface Memory {
  id: string;
  image_url: string;
  thumbnail_url: string;
  caption: string | null;
  visibility: 'private' | 'family';
  experience: {
    id: string;
    title: string;
  } | null;
  user: {
    id: string;
    name: string;
    avatar: string;
  };
  reactions: {
    type: string;
    count: number;
    user_reacted: boolean;
  }[];
  comments_count: number;
  created_at: string;
}
```

### Fluxo de Upload de Memória
```typescript
export function useCreateMemory() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async ({ imageFile, caption, experienceId, visibility }) => {
      // 1. Obter presigned URL
      const { data: presign } = await api.post('/uploads/presign', {
        type: 'memory',
        content_type: imageFile.type,
        filename: imageFile.name,
      });
      
      // 2. Upload direto ao S3
      await fetch(presign.data.upload_url, {
        method: 'PUT',
        body: imageFile,
        headers: { 'Content-Type': imageFile.type },
      });
      
      // 3. Criar memória com URL
      const { data: memory } = await api.post('/memories', {
        image_url: presign.data.file_url,
        caption,
        experience_id: experienceId,
        visibility,
      });
      
      return memory.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['memories'] });
    },
  });
}
```

---

## 🔔 6. NotificationsScreen

### Endpoints
```http
GET /notifications                # Listar
GET /notifications/unread-count   # Contador (para badge)
PATCH /notifications/{id}/read    # Marcar como lida
POST /notifications/read-all      # Marcar todas
DELETE /notifications/{id}        # Excluir
```

### Response Schema
```typescript
interface NotificationsResponse {
  data: Notification[];
  meta: { success: boolean; unread_count: number; next_cursor?: string };
}

interface Notification {
  id: string;
  type: NotificationType;
  title: string;
  body: string;
  image_url: string | null;
  data: Record<string, any>;  // Dados extras para navegação
  read_at: string | null;
  created_at: string;
}

type NotificationType = 
  | 'family_invite'     // Convite para família
  | 'memory_reaction'   // Reação em memória
  | 'plan_reminder'     // Lembrete de plano
  | 'trending'          // Experiência em alta
  | 'badge_earned'      // Conquista
  | 'plan_update'       // Atualização de plano
  | 'new_review';       // Nova review
```

### React Query Hooks
```typescript
// Listar notificações
export function useNotifications() {
  return useInfiniteQuery({
    queryKey: ['notifications'],
    queryFn: async ({ pageParam }) => {
      const cursor = pageParam ? `?cursor=${pageParam}` : '';
      const { data } = await api.get(`/notifications${cursor}`);
      return data;
    },
    getNextPageParam: (lastPage) => 
      lastPage.meta.next_cursor,
  });
}

// Badge counter (polling a cada 30s)
export function useUnreadCount() {
  return useQuery({
    queryKey: ['notifications', 'unread'],
    queryFn: async () => {
      const { data } = await api.get('/notifications/unread-count');
      return data.data.count;
    },
    refetchInterval: 30000, // Poll a cada 30s
  });
}

// Navegação baseada no tipo
const handleNotificationPress = (notification: Notification) => {
  switch (notification.type) {
    case 'family_invite':
      navigation.navigate('FamilyInvite', { code: notification.data.code });
      break;
    case 'memory_reaction':
      navigation.navigate('MemoryDetail', { id: notification.data.memory_id });
      break;
    case 'plan_reminder':
      navigation.navigate('PlanDetail', { id: notification.data.plan_id });
      break;
    case 'trending':
      navigation.navigate('ExperienceDetail', { id: notification.data.experience_id });
      break;
    // ...
  }
};
```

---

## 📝 7. ExperienceDetailScreen

### Endpoint
```http
GET /experiences/{id}
```

### Response Schema (Completo)
```typescript
interface ExperienceDetailResponse {
  data: ExperienceDetail;
  meta: { success: boolean };
}

interface ExperienceDetail {
  id: string;
  title: string;
  mission_title: string;
  summary: string;
  
  category: {
    id: string;
    name: string;
    emoji: string;
    color: string;
  };
  
  badges: string[];
  age_tags: ('baby' | 'toddler' | 'kid' | 'teen')[];
  vibe: string[];
  
  duration: {
    label: string;
    minutes_min: number;
    minutes_max: number;
  };
  
  price: {
    level: 'free' | 'moderate' | 'top';
    label: string;
    min_value?: number;
    max_value?: number;
  };
  
  weather: ('sun' | 'rain' | 'any')[];
  
  practical: {
    parking: boolean;
    bathroom: boolean;
    food: boolean;
    stroller: boolean;
    accessibility: boolean;
    changing_table: boolean;
  };
  
  tips: string[];
  
  location: {
    place_name: string;
    address: string;
    neighborhood: string;
    city: string;
    state: string;
  };
  
  coords: { lat: number; lng: number };
  
  images: {
    cover: string;
    gallery: string[];
  };
  
  stats: {
    saves_count: number;
    reviews_count: number;
    average_rating: number;
    trending_score: number;
  };
  
  review_distribution: Record<'1' | '2' | '3' | '4' | '5', number>;
  
  recent_reviews: Review[];
  related: Experience[];
  
  user_review: Review | null;
  is_saved: boolean;
  distance_km: number;
}
```

### React Query Hook
```typescript
export function useExperienceDetail(id: string) {
  return useQuery({
    queryKey: ['experiences', id],
    queryFn: async () => {
      const { data } = await api.get(`/experiences/${id}`);
      return data.data;
    },
    staleTime: 5 * 60 * 1000, // Cache 5 min
  });
}
```

---

## ⚙️ 8. Configurações Estáticas (EnergySlider, VibeSelector)

### Endpoint
```http
GET /config
```

### Response
```typescript
interface ConfigResponse {
  data: {
    energy_levels: { value: number; emoji: string; label: string }[];
    vibe_options: { id: string; emoji: string; label: string }[];
    quick_filters: { id: string; label: string; emoji: string }[];
    age_groups: { value: string; label: string; emoji: string; age_range: string }[];
    price_levels: { value: string; label: string; emoji: string; range?: string }[];
    duration_buckets: { value: string; label: string; emoji: string; range: string }[];
  };
}
```

### React Query Hook
```typescript
export function useAppConfig() {
  return useQuery({
    queryKey: ['config'],
    queryFn: async () => {
      const { data } = await api.get('/config');
      return data.data;
    },
    staleTime: 24 * 60 * 60 * 1000, // Cache 24h
    gcTime: Infinity,
  });
}

// Uso no EnergySlider
const EnergySlider = () => {
  const { data: config } = useAppConfig();
  
  return config?.energy_levels.map(level => (
    <SliderOption key={level.value} {...level} />
  ));
};
```

---

## ✅ Respostas às Perguntas

### Categories vs QuickFilters
- **`/categories`** = Lista de categorias principais (Parques, Museus, etc.)
- **`/config` → quick_filters** = Atalhos de busca rápida (Dia de chuva, Com bebê, etc.)

### Collections vs Favorite-Lists
- **Collections** = Listas curadas pelo editorial (futuro endpoint `/collections`)
- **Favorite-Lists** = Listas criadas pelo usuário (`/favorite-lists`)

### Tokens
- **Access Token**: 60 minutos
- **Refresh Token**: 14 dias

---

## 🔗 Links Úteis

- **Documentação Interativa:** https://api.valorsc.com.br/docs
- **Postman Collection:** https://api.valorsc.com.br/docs/collection.json
- **OpenAPI Spec:** https://api.valorsc.com.br/docs/openapi.yaml

---

## 📞 Suporte

Dúvidas? Abra uma issue ou contate o time de backend.

> **Atualizado:** 2026-01-18 10:04 (UTC-3)
