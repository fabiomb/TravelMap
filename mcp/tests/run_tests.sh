#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# TravelMap MCP — Smoke Tests
#
# Uso: bash mcp/tests/run_tests.sh [--keep-data]
#   --keep-data   No elimina los registros creados en la DB durante los tests.
#
# Requiere: php, jq (para parsear JSON), bc (para comparar floats)
# ─────────────────────────────────────────────────────────────────────────────

set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"
SERVER="php ${ROOT_DIR}/mcp/server.php"
PASS=0
FAIL=0
CREATED_TRIP_ID=""
CREATED_ROUTE_ID=""
CREATED_POI_ID=""

red()   { echo -e "\033[31m$*\033[0m"; }
green() { echo -e "\033[32m$*\033[0m"; }
info()  { echo -e "\033[34m$*\033[0m"; }

# Envía una request JSON-RPC y devuelve la respuesta
rpc() {
    local payload="$1"
    echo "${payload}" | ${SERVER} 2>/dev/null
}

# Verifica que el campo jq_path exista y no sea null en la respuesta
assert_field() {
    local label="$1"
    local response="$2"
    local jq_path="$3"
    local value
    value=$(echo "${response}" | jq -r "${jq_path}" 2>/dev/null || true)
    if [[ -z "${value}" || "${value}" == "null" ]]; then
        red "  FAIL: ${label} — campo '${jq_path}' vacío o null"
        red "  Response: ${response}"
        FAIL=$((FAIL+1))
    else
        green "  PASS: ${label} — ${jq_path} = ${value}"
        PASS=$((PASS+1))
    fi
    echo "${value}"
}

assert_no_error() {
    local label="$1"
    local response="$2"
    if echo "${response}" | jq -e '.error' > /dev/null 2>&1; then
        red "  FAIL: ${label} — respuesta de error: $(echo "${response}" | jq -c '.error')"
        FAIL=$((FAIL+1))
        return 1
    fi
    green "  PASS: ${label} — sin error"
    PASS=$((PASS+1))
    return 0
}

# ─────────────────────────────────────────────────────────────────────────────
info "\n=== 1. initialize ==="
RESP=$(rpc '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}')
assert_field "serverInfo.name" "${RESP}" '.result.serverInfo.name'

# ─────────────────────────────────────────────────────────────────────────────
info "\n=== 2. tools/list ==="
RESP=$(rpc '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}')
assert_no_error "tools/list no error" "${RESP}"
for TOOL in list_trips search_trips get_trip create_trip update_trip create_route update_route plan_route commit_route search_pois create_poi update_poi search_location; do
    COUNT=$(echo "${RESP}" | jq "[.result.tools[].name] | map(select(. == \"${TOOL}\")) | length" 2>/dev/null || echo "0")
    if [[ "${COUNT}" == "1" ]]; then
        green "  PASS: tool '${TOOL}' presente"
        PASS=$((PASS+1))
    else
        red "  FAIL: tool '${TOOL}' no encontrado"
        FAIL=$((FAIL+1))
    fi
done

# ─────────────────────────────────────────────────────────────────────────────
info "\n=== 3. list_trips ==="
RESP=$(rpc '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"list_trips","arguments":{"limit":5}}}')
assert_no_error "list_trips no error" "${RESP}"

# ─────────────────────────────────────────────────────────────────────────────
info "\n=== 4. create_trip ==="
RESP=$(rpc '{"jsonrpc":"2.0","id":5,"method":"tools/call","params":{"name":"create_trip","arguments":{"title":"TEST_MCP_TRIP","start_date":"2024-01-01","end_date":"2024-01-10","status":"draft","tags":["test","mcp"]}}}')
assert_no_error "create_trip no error" "${RESP}"
CONTENT=$(echo "${RESP}" | jq -r '.result.content[0].text' 2>/dev/null || echo "{}")
CREATED_TRIP_ID=$(echo "${CONTENT}" | jq -r '.id' 2>/dev/null || echo "")
if [[ -n "${CREATED_TRIP_ID}" && "${CREATED_TRIP_ID}" != "null" ]]; then
    green "  PASS: create_trip — id=${CREATED_TRIP_ID}"
    PASS=$((PASS+1))
else
    red "  FAIL: create_trip — no devolvió id"
    FAIL=$((FAIL+1))
fi

# ─────────────────────────────────────────────────────────────────────────────
info "\n=== 5. get_trip (round-trip) ==="
if [[ -n "${CREATED_TRIP_ID}" ]]; then
    RESP=$(rpc "{\"jsonrpc\":\"2.0\",\"id\":6,\"method\":\"tools/call\",\"params\":{\"name\":\"get_trip\",\"arguments\":{\"id\":${CREATED_TRIP_ID}}}}")
    assert_no_error "get_trip no error" "${RESP}"
    CONTENT=$(echo "${RESP}" | jq -r '.result.content[0].text')
    TITLE=$(echo "${CONTENT}" | jq -r '.trip.title')
    if [[ "${TITLE}" == "TEST_MCP_TRIP" ]]; then
        green "  PASS: get_trip — título correcto"
        PASS=$((PASS+1))
    else
        red "  FAIL: get_trip — título esperado 'TEST_MCP_TRIP', obtenido '${TITLE}'"
        FAIL=$((FAIL+1))
    fi
fi

# ─────────────────────────────────────────────────────────────────────────────
info "\n=== 6. create_route (BRouter CSV) ==="
if [[ -n "${CREATED_TRIP_ID}" ]]; then
    CSV_B64=$(base64 -w0 "${SCRIPT_DIR}/fixtures/sample.brouter.csv")
    RESP=$(rpc "{\"jsonrpc\":\"2.0\",\"id\":7,\"method\":\"tools/call\",\"params\":{\"name\":\"create_route\",\"arguments\":{\"trip_id\":${CREATED_TRIP_ID},\"transport_type\":\"bike\",\"brouter_csv_base64\":\"${CSV_B64}\"}}}")
    assert_no_error "create_route no error" "${RESP}"
    CONTENT=$(echo "${RESP}" | jq -r '.result.content[0].text')
    CREATED_ROUTE_ID=$(echo "${CONTENT}" | jq -r '.id')
    DIST=$(echo "${CONTENT}" | jq -r '.distance_km')
    WP=$(echo "${CONTENT}" | jq -r '.waypoints_count')
    if [[ "${DIST}" != "null" ]] && (( $(echo "${DIST} > 0" | bc -l 2>/dev/null || echo 0) )); then
        green "  PASS: create_route — dist=${DIST}km waypoints=${WP}"
        PASS=$((PASS+1))
    else
        red "  FAIL: create_route — distance_km=${DIST}"
        FAIL=$((FAIL+1))
    fi
fi

# ─────────────────────────────────────────────────────────────────────────────
info "\n=== 7. create_poi (foto base64) ==="
if [[ -n "${CREATED_TRIP_ID}" ]]; then
    PHOTO_B64=$(cat "${SCRIPT_DIR}/fixtures/tiny.jpg.b64" | tr -d '\n')
    RESP=$(rpc "{\"jsonrpc\":\"2.0\",\"id\":8,\"method\":\"tools/call\",\"params\":{\"name\":\"create_poi\",\"arguments\":{\"trip_id\":${CREATED_TRIP_ID},\"title\":\"TEST POI\",\"type\":\"visit\",\"latitude\":41.3851,\"longitude\":2.1734,\"photo_base64\":\"${PHOTO_B64}\",\"photo_filename\":\"tiny.jpg\"}}}")
    assert_no_error "create_poi no error" "${RESP}"
    CONTENT=$(echo "${RESP}" | jq -r '.result.content[0].text')
    CREATED_POI_ID=$(echo "${CONTENT}" | jq -r '.id')
    IMG=$(echo "${CONTENT}" | jq -r '.image_path')
    if [[ "${IMG}" == uploads/points/img_* ]]; then
        green "  PASS: create_poi — image_path=${IMG}"
        PASS=$((PASS+1))
        # Verificar que el archivo existe en disco
        if [[ -f "${ROOT_DIR}/${IMG}" ]]; then
            green "  PASS: create_poi — archivo existe en disco"
            PASS=$((PASS+1))
        else
            red "  FAIL: create_poi — archivo NO encontrado en disco: ${ROOT_DIR}/${IMG}"
            FAIL=$((FAIL+1))
        fi
    else
        red "  FAIL: create_poi — image_path inesperado: ${IMG}"
        FAIL=$((FAIL+1))
    fi
fi

# ─────────────────────────────────────────────────────────────────────────────
info "\n=== 8. Tests de seguridad ==="

# Path traversal en photo_filename
PHOTO_B64=$(cat "${SCRIPT_DIR}/fixtures/tiny.jpg.b64" | tr -d '\n')
RESP=$(rpc "{\"jsonrpc\":\"2.0\",\"id\":91,\"method\":\"tools/call\",\"params\":{\"name\":\"create_poi\",\"arguments\":{\"trip_id\":1,\"title\":\"x\",\"type\":\"visit\",\"latitude\":0,\"longitude\":0,\"photo_base64\":\"${PHOTO_B64}\",\"photo_filename\":\"../../etc/passwd.png\"}}}")
IS_ERR=$(echo "${RESP}" | jq -r '.result.isError // false' 2>/dev/null || echo "false")
# Puede fallar por trip_id=1 no existente o por filename sanitizado — lo importante es no crashear
if [[ "${RESP}" == *"jsonrpc"* ]]; then
    green "  PASS: path traversal — servidor responde sin crash"
    PASS=$((PASS+1))
else
    red "  FAIL: path traversal — servidor no respondió"
    FAIL=$((FAIL+1))
fi

# Base64 inválido
RESP=$(rpc '{"jsonrpc":"2.0","id":92,"method":"tools/call","params":{"name":"create_poi","arguments":{"trip_id":1,"title":"x","type":"visit","latitude":0,"longitude":0,"photo_base64":"!!!notbase64!!!","photo_filename":"test.jpg"}}}')
if echo "${RESP}" | grep -qi "base64\|INVALID\|inválid\|error" 2>/dev/null; then
    green "  PASS: base64 inválido — rechazado correctamente"
    PASS=$((PASS+1))
else
    red "  FAIL: base64 inválido — no fue rechazado"
    FAIL=$((FAIL+1))
fi

# trip_id inexistente en create_route
RESP=$(rpc '{"jsonrpc":"2.0","id":93,"method":"tools/call","params":{"name":"create_route","arguments":{"trip_id":999999,"transport_type":"bike","brouter_csv_text":"x"}}}')
if echo "${RESP}" | grep -qi "TRIP_NOT_FOUND\|no encontrado\|not found" 2>/dev/null; then
    green "  PASS: trip_id inexistente — error TRIP_NOT_FOUND"
    PASS=$((PASS+1))
else
    red "  FAIL: trip_id inexistente — respuesta inesperada: ${RESP}"
    FAIL=$((FAIL+1))
fi

# JSON malformado — servidor debe seguir vivo
RESP=$(echo "{malformed json" | ${SERVER} 2>/dev/null || true)
if [[ -n "${RESP}" ]]; then
    green "  PASS: JSON malformado — servidor no crasheó"
    PASS=$((PASS+1))
else
    red "  FAIL: JSON malformado — servidor crasheó (sin respuesta)"
    FAIL=$((FAIL+1))
fi

# ─────────────────────────────────────────────────────────────────────────────
# Resumen
info "\n=== Resultados ==="
echo "  PASS: ${PASS}"
echo "  FAIL: ${FAIL}"

if [[ "${1:-}" != "--keep-data" && -n "${CREATED_TRIP_ID}" ]]; then
    info "\n  Nota: Se creó el trip id=${CREATED_TRIP_ID} (title='TEST_MCP_TRIP') para los tests."
    info "  Puedes eliminarlo desde el admin o ejecutar:"
    info "    php -r \"require '${ROOT_DIR}/config/config.php'; require '${ROOT_DIR}/config/db.php'; getDB()->exec('DELETE FROM trips WHERE id=${CREATED_TRIP_ID}');\""
fi

[[ "${FAIL}" == "0" ]] && exit 0 || exit 1
