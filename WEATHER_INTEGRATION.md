# 🌤️ Weather Integration Guide - Frontend

> **API Base:** `https://api.valorsc.com.br/api/v1/weather`  
> **Cache:** Respostas são cacheadas (search: 24h, current: 5min, forecast: 30min)

---

## 📡 Endpoints Disponíveis

### 1. Buscar Localizações (Autocomplete)

```http
GET /weather/search?q=São Paulo
```

**Response:**
```typescript
interface SearchResponse {
  data: {
    locations: {
      id: number;
      name: string;
      region: string;
      country: string;
      lat: number;
      lon: number;
      display_name: string; // "São Paulo, São Paulo, Brazil"
    }[];
  };
  meta: { success: boolean };
}
```

---

### 2. Clima Atual

```http
GET /weather/current?q=São Paulo
GET /weather/current?q=-23.53,-46.62  // lat,lon
```

**Response:**
```typescript
interface CurrentResponse {
  data: {
    location: WeatherLocation;
    current: {
      temp_c: number;
      temp_f: number;
      feelslike_c: number;
      feelslike_f: number;
      humidity: number;
      wind_kph: number;
      wind_dir: string;
      pressure_mb: number;
      uv: number;
      is_day: boolean;
      condition: WeatherCondition;
      last_updated: string;
    };
  };
  meta: { success: boolean };
}
```

---

### 3. Previsão do Tempo

```http
GET /weather/forecast?q=São Paulo&days=3
```

**Response:**
```typescript
interface ForecastResponse {
  data: {
    location: WeatherLocation;
    current: CurrentWeather;
    forecast: {
      date: string;         // "2026-01-18"
      min_c: number;
      max_c: number;
      avg_temp_c: number;
      avg_humidity: number;
      chance_of_rain: number;
      uv: number;
      condition: WeatherCondition;
      sunrise: string;
      sunset: string;
      hours: HourlyForecast[];
    }[];
  };
  meta: { success: boolean };
}
```

---

## 🔧 Tipos TypeScript

```typescript
// src/types/weather.types.ts

export interface WeatherLocation {
  name: string;
  region: string;
  country: string;
  lat: number;
  lon: number;
  tz_id: string;
  localtime: string;
}

export interface WeatherCondition {
  text: string;   // "Parcialmente nublado"
  icon: string;   // "https://cdn.weatherapi.com/weather/64x64/day/116.png"
  code: number;
}

export interface CurrentWeather {
  temp_c: number;
  feelslike_c: number;
  humidity: number;
  wind_kph: number;
  is_day: boolean;
  condition: WeatherCondition;
}

export interface DayForecast {
  date: string;
  min_c: number;
  max_c: number;
  avg_temp_c: number;
  avg_humidity: number;
  chance_of_rain: number;
  uv: number;
  condition: WeatherCondition;
  sunrise: string;
  sunset: string;
  hours: HourlyForecast[];
}

export interface HourlyForecast {
  time: string;
  temp_c: number;
  condition: WeatherCondition;
  chance_of_rain: number;
  wind_kph: number;
}

export interface LocationSearchResult {
  id: number;
  name: string;
  region: string;
  country: string;
  lat: number;
  lon: number;
  display_name: string;
}
```

---

## 📦 Service Layer

```typescript
// src/data/services/weather.service.ts

import { api } from '@/lib/api';
import type { 
  LocationSearchResult, 
  CurrentWeather, 
  WeatherLocation, 
  DayForecast 
} from '@/types/weather.types';

interface SearchResponse {
  data: { locations: LocationSearchResult[] };
  meta: { success: boolean };
}

interface CurrentResponse {
  data: { location: WeatherLocation; current: CurrentWeather };
  meta: { success: boolean };
}

interface ForecastResponse {
  data: { 
    location: WeatherLocation; 
    current: CurrentWeather; 
    forecast: DayForecast[] 
  };
  meta: { success: boolean };
}

export const weatherService = {
  async searchLocations(query: string): Promise<LocationSearchResult[]> {
    if (query.length < 2) return [];
    const { data } = await api.get<SearchResponse>('/weather/search', { 
      params: { q: query } 
    });
    return data.data.locations;
  },

  async getCurrent(query: string) {
    const { data } = await api.get<CurrentResponse>('/weather/current', { 
      params: { q: query } 
    });
    return data.data;
  },

  async getForecast(query: string, days = 3) {
    const { data } = await api.get<ForecastResponse>('/weather/forecast', { 
      params: { q: query, days } 
    });
    return data.data;
  },
};
```

---

## 🎣 React Query Hooks

```typescript
// src/data/hooks/useWeather.ts

import { useQuery } from '@tanstack/react-query';
import { weatherService } from '../services/weather.service';
import { useDebouncedValue } from '@/hooks/useDebouncedValue';

// 🔍 Autocomplete com debounce
export function useWeatherSearch(query: string) {
  const debouncedQuery = useDebouncedValue(query, 400);

  return useQuery({
    queryKey: ['weather', 'search', debouncedQuery],
    queryFn: () => weatherService.searchLocations(debouncedQuery),
    enabled: debouncedQuery.length >= 2,
    staleTime: 24 * 60 * 60 * 1000, // 24h (já cacheado no backend)
  });
}

// 🌡️ Clima atual
export function useCurrentWeather(location: string | null) {
  return useQuery({
    queryKey: ['weather', 'current', location],
    queryFn: () => weatherService.getCurrent(location!),
    enabled: !!location,
    staleTime: 5 * 60 * 1000, // 5 min
    retry: 1,
  });
}

// 📅 Previsão
export function useForecast(location: string | null, days = 3) {
  return useQuery({
    queryKey: ['weather', 'forecast', location, days],
    queryFn: () => weatherService.getForecast(location!, days),
    enabled: !!location,
    staleTime: 15 * 60 * 1000, // 15 min
    retry: 1,
  });
}
```

---

## 🧩 Hook de Debounce

```typescript
// src/hooks/useDebouncedValue.ts

import { useState, useEffect } from 'react';

export function useDebouncedValue<T>(value: T, delay: number): T {
  const [debouncedValue, setDebouncedValue] = useState(value);

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedValue(value), delay);
    return () => clearTimeout(timer);
  }, [value, delay]);

  return debouncedValue;
}
```

---

## 🎨 Componente de Exemplo

```tsx
// src/components/WeatherWidget.tsx

import { useState } from 'react';
import { useWeatherSearch, useForecast } from '@/data/hooks/useWeather';

export function WeatherWidget() {
  const [query, setQuery] = useState('');
  const [selectedLocation, setSelectedLocation] = useState<string | null>(null);

  const { data: locations, isLoading: searching } = useWeatherSearch(query);
  const { data: forecast, isLoading: loadingForecast, error } = useForecast(selectedLocation, 3);

  return (
    <div className="weather-widget">
      {/* Search Input */}
      <div className="search-container">
        <input
          type="text"
          placeholder="Buscar cidade..."
          value={query}
          onChange={(e) => setQuery(e.target.value)}
        />
        
        {/* Autocomplete Dropdown */}
        {searching && <div className="loading">Buscando...</div>}
        {locations && locations.length > 0 && (
          <ul className="autocomplete-list">
            {locations.map((loc) => (
              <li
                key={loc.id}
                onClick={() => {
                  setSelectedLocation(loc.display_name);
                  setQuery(loc.display_name);
                }}
              >
                {loc.display_name}
              </li>
            ))}
          </ul>
        )}
      </div>

      {/* Weather Display */}
      {loadingForecast && <WeatherSkeleton />}
      
      {error && (
        <div className="error">
          Não foi possível carregar o clima. Tente novamente.
        </div>
      )}

      {forecast && (
        <>
          {/* Current Weather Card */}
          <div className="current-weather">
            <h2>{forecast.location.name}</h2>
            <div className="temp">{Math.round(forecast.current.temp_c)}°C</div>
            <div className="feels-like">
              Sensação: {Math.round(forecast.current.feelslike_c)}°C
            </div>
            <img src={forecast.current.condition.icon} alt="" />
            <p>{forecast.current.condition.text}</p>
            <div className="details">
              <span>💧 {forecast.current.humidity}%</span>
              <span>💨 {forecast.current.wind_kph} km/h</span>
            </div>
          </div>

          {/* Forecast Days */}
          <div className="forecast-days">
            {forecast.forecast.map((day) => (
              <div key={day.date} className="forecast-day">
                <p className="date">
                  {new Date(day.date).toLocaleDateString('pt-BR', { weekday: 'short' })}
                </p>
                <img src={day.condition.icon} alt="" />
                <p className="temps">
                  {Math.round(day.max_c)}° / {Math.round(day.min_c)}°
                </p>
                {day.chance_of_rain > 30 && (
                  <p className="rain">🌧️ {day.chance_of_rain}%</p>
                )}
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  );
}

function WeatherSkeleton() {
  return (
    <div className="weather-skeleton">
      <div className="skeleton-line" style={{ width: '60%' }} />
      <div className="skeleton-line" style={{ width: '40%' }} />
      <div className="skeleton-line" style={{ width: '80%' }} />
    </div>
  );
}
```

---

## 🔑 Configuração Backend

Adicionar no `.env`:
```env
WEATHERAPI_KEY=sua_chave_aqui
```

Obter chave em: https://www.weatherapi.com/signup.aspx (Free: 1M calls/month)

---

## ⚡ Dicas de Performance

1. **Debounce no search**: 400ms evita spam de requisições
2. **staleTime alto**: Dados de clima não mudam a cada segundo
3. **enabled condicional**: Só faz request quando tem query válida
4. **retry: 1**: Não insistir muito em caso de erro
    
---

## 📱 Integração com Experiências

Para mostrar clima na Home ou em experiências:

```typescript
// Usar coordenadas da cidade selecionada
const { data: user } = useUser();
const cityCoords = `${user.primaryCity.lat},${user.primaryCity.lng}`;

const { data: weather } = useCurrentWeather(cityCoords);

// Filtrar experiências por clima
const isRaining = weather?.current.condition.code >= 1180; // rain codes
const filteredExperiences = isRaining 
  ? experiences.filter(e => e.weather_tags.includes('rain'))
  : experiences;
```

---

> **Atualizado:** 2026-01-18 11:40 (UTC-3)
