#!/bin/bash
# ============================================================
# API Endpoint Health Check Script
# Run: bash scripts/test-endpoints.sh
# ============================================================

BASE_URL="${API_BASE_URL:-https://api.valorsc.com.br/api/v1}"
CITY_ID="${CITY_ID:-edbca93c-2f01-4e17-af0a-53b1ccb4bf90}"
AUTH_TOKEN="${AUTH_TOKEN:-}"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "============================================"
echo "🧪 API Endpoint Health Check"
echo "Base URL: $BASE_URL"
echo "City ID: $CITY_ID"
echo "============================================"
echo ""

# Track stats
PASS=0
FAIL=0
SKIP=0

# Test function for public endpoints
test_public() {
    local name="$1"
    local path="$2"
    local expected_status="${3:-200}"
    
    response=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}${path}" -H "Accept: application/json" --max-time 10)
    
    if [ "$response" = "$expected_status" ]; then
        echo -e "  ${GREEN}✓${NC} $name ($response)"
        ((PASS++))
    else
        echo -e "  ${RED}✗${NC} $name (got $response, expected $expected_status)"
        ((FAIL++))
    fi
}

# Test function for authenticated endpoints
test_auth() {
    local name="$1"
    local path="$2"
    local expected_status="${3:-200}"
    
    if [ -z "$AUTH_TOKEN" ]; then
        echo -e "  ${YELLOW}⊘${NC} $name (skipped - no token)"
        ((SKIP++))
        return
    fi
    
    response=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}${path}" \
        -H "Accept: application/json" \
        -H "Authorization: Bearer $AUTH_TOKEN" \
        --max-time 10)
    
    if [ "$response" = "$expected_status" ]; then
        echo -e "  ${GREEN}✓${NC} $name ($response)"
        ((PASS++))
    else
        echo -e "  ${RED}✗${NC} $name (got $response, expected $expected_status)"
        ((FAIL++))
    fi
}

# Test that should return 401 if no auth
test_requires_auth() {
    local name="$1"
    local path="$2"
    
    response=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}${path}" \
        -H "Accept: application/json" \
        --max-time 10)
    
    if [ "$response" = "401" ]; then
        echo -e "  ${GREEN}✓${NC} $name (correctly returns 401 without auth)"
        ((PASS++))
    elif [ "$response" = "500" ]; then
        echo -e "  ${RED}✗${NC} $name (500 instead of 401 - ERROR HANDLING ISSUE)"
        ((FAIL++))
    else
        echo -e "  ${YELLOW}?${NC} $name (unexpected: $response)"
        ((FAIL++))
    fi
}

echo "📡 Public Endpoints (No Auth Required)"
echo "----------------------------------------"
test_public "Health Check" "/health"
test_public "Config" "/config"
test_public "Categories" "/categories"
test_public "Cities Search" "/cities?q=sao"
test_public "City Detail" "/cities/${CITY_ID}"
test_public "Collections List" "/collections"
test_public "Weather Search" "/weather/search?q=Sao%20Paulo"
test_public "Weather Current" "/weather/current?q=Sao%20Paulo"

echo ""
echo "🔐 Auth Required Endpoints (Testing 401 Response)"
echo "----------------------------------------"
test_requires_auth "Home Feed" "/home?city_id=${CITY_ID}"
test_requires_auth "Experience Search" "/experiences/search?city_id=${CITY_ID}"
test_requires_auth "Favorites" "/favorites"
test_requires_auth "Family" "/family"
test_requires_auth "Family Dependents" "/family/dependents"
test_requires_auth "Plans" "/plans"
test_requires_auth "Notifications" "/notifications"
test_requires_auth "Auth Me" "/auth/me"

echo ""
echo "🔑 Authenticated Endpoints (With Token)"
echo "----------------------------------------"
if [ -z "$AUTH_TOKEN" ]; then
    echo -e "  ${YELLOW}⊘${NC} Skipping - Set AUTH_TOKEN env var to test"
else
    test_auth "Home Feed" "/home?city_id=${CITY_ID}"
    test_auth "Experience Search" "/experiences/search?city_id=${CITY_ID}"
    test_auth "Favorites" "/favorites"
    test_auth "Family" "/family"
    test_auth "Family Dependents" "/family/dependents"
    test_auth "Plans" "/plans"
    test_auth "Notifications" "/notifications"
    test_auth "Auth Me" "/auth/me"
fi

echo ""
echo "============================================"
echo "📊 Results Summary"
echo "============================================"
echo -e "  ${GREEN}Pass: $PASS${NC}"
echo -e "  ${RED}Fail: $FAIL${NC}"
echo -e "  ${YELLOW}Skip: $SKIP${NC}"
echo ""

if [ $FAIL -gt 0 ]; then
    echo "❌ Some tests failed!"
    exit 1
else
    echo "✅ All tests passed!"
    exit 0
fi
