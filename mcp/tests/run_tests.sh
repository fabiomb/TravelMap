#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# TravelMap MCP — Smoke Tests
#
# Uso: bash mcp/tests/run_tests.sh [--keep-data]
#   --keep-data   No elimina los registros creados en la DB durante los tests.
#
# Requiere: php
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

json_get() {
    local response="$1"
    local path="$2"
    php -r '
        $data = json_decode($argv[1], true);
        if (!is_array($data)) {
            exit;
        }
        preg_match_all("/\\.([A-Za-z0-9_]+)|\\[(\\d+)\\]/", $argv[2], $matches, PREG_SET_ORDER);
        $value = $data;
        foreach ($matches as $match) {
            $key = $match[1] !== "" ? $match[1] : (int)$match[2];
            if (!is_array($value) || !array_key_exists($key, $value)) {
                exit;
            }
            $value = $value[$key];
        }
        if (is_bool($value)) {
            echo $value ? "true" : "false";
        } elseif (is_array($value)) {
            echo json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif ($value !== null) {
            echo $value;
        }
    ' "${response}" "${path}" 2>/dev/null || true
}

json_has_error() {
    local response="$1"
    php -r '
        $data = json_decode($argv[1], true);
        exit(is_array($data) && array_key_exists("error", $data) ? 0 : 1);
    ' "${response}" 2>/dev/null
}

json_error_compact() {
    local response="$1"
    php -r '
        $data = json_decode($argv[1], true);
        echo json_encode($data["error"] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ' "${response}" 2>/dev/null || true
}

tool_count() {
    local response="$1"
    local tool="$2"
    php -r '
        $data = json_decode($argv[1], true);
        $count = 0;
        foreach (($data["result"]["tools"] ?? []) as $entry) {
            if (($entry["name"] ?? null) === $argv[2]) {
                $count++;
            }
        }
        echo $count;
    ' "${response}" "${tool}" 2>/dev/null || echo "0"
}

# Verifica que el campo JSON exista y no sea null en la respuesta
assert_field() {
    local label="$1"
    local response="$2"
    local json_path="$3"
    local value
    value=$(json_get "${response}" "${json_path}")
    if [[ -z "${value}" || "${value}" == "null" ]]; then
        red "  FAIL: ${label} — campo '${json_path}' vacío o null"
        red "  Response: ${response}"
        FAIL=$((FAIL+1))
    else
        green "  PASS: ${label} — ${json_path} = ${value}"
        PASS=$((PASS+1))
    fi
}

assert_no_error() {
    local label="$1"
    local response="$2"
    if json_has_error "${response}"; then
        red "  FAIL: ${label} — respuesta de error: $(json_error_compact "${response}")"
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
for TOOL in list_trips search_trips get_trip create_trip update_trip create_route update_route plan_route commit_route search_pois create_poi update_poi inspect_uploaded_photo cleanup_uploaded_photo search_location; do
    COUNT=$(tool_count "${RESP}" "${TOOL}")
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
CONTENT=$(json_get "${RESP}" '.result.content[0].text')
CREATED_TRIP_ID=$(json_get "${CONTENT}" '.id')
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
    CONTENT=$(json_get "${RESP}" '.result.content[0].text')
    TITLE=$(json_get "${CONTENT}" '.trip.title')
    if [[ "${TITLE}" == "TEST_MCP_TRIP" ]]; then
        green "  PASS: get_trip — título correcto"
        PASS=$((PASS+1))
    else
        red "  FAIL: get_trip — título esperado 'TEST_MCP_TRIP', obtenido '${TITLE}'"
        FAIL=$((FAIL+1))
    fi
fi

# ─────────────────────────────────────────────────────────────────────────────
info "\n=== 6. create_route (coordenadas estructuradas) ==="
if [[ -n "${CREATED_TRIP_ID}" ]]; then
    RESP=$(rpc "{\"jsonrpc\":\"2.0\",\"id\":7,\"method\":\"tools/call\",\"params\":{\"name\":\"create_route\",\"arguments\":{\"trip_id\":${CREATED_TRIP_ID},\"transport_type\":\"bike\",\"coordinates\":[{\"lat\":41.3851,\"lon\":2.1734},{\"lat\":41.3902,\"lon\":2.1540}]}}}")
    assert_no_error "create_route no error" "${RESP}"
    CONTENT=$(json_get "${RESP}" '.result.content[0].text')
    CREATED_ROUTE_ID=$(json_get "${CONTENT}" '.id')
    DIST=$(json_get "${CONTENT}" '.distance_km')
    WP=$(json_get "${CONTENT}" '.waypoints_count')
    if php -r 'exit(((float)$argv[1]) > 0 ? 0 : 1);' "${DIST}" 2>/dev/null; then
        green "  PASS: create_route — dist=${DIST}km waypoints=${WP}"
        PASS=$((PASS+1))
    else
        red "  FAIL: create_route — distance_km=${DIST}"
        FAIL=$((FAIL+1))
    fi
fi

# ─────────────────────────────────────────────────────────────────────────────
info "\n=== 7. create_poi (sin foto) ==="
if [[ -n "${CREATED_TRIP_ID}" ]]; then
    RESP=$(rpc "{\"jsonrpc\":\"2.0\",\"id\":8,\"method\":\"tools/call\",\"params\":{\"name\":\"create_poi\",\"arguments\":{\"trip_id\":${CREATED_TRIP_ID},\"title\":\"TEST POI\",\"type\":\"visit\",\"latitude\":41.3851,\"longitude\":2.1734}}}")
    assert_no_error "create_poi no error" "${RESP}"
    CONTENT=$(json_get "${RESP}" '.result.content[0].text')
    CREATED_POI_ID=$(json_get "${CONTENT}" '.id')
    LAT=$(json_get "${CONTENT}" '.latitude')
    LON=$(json_get "${CONTENT}" '.longitude')
    if [[ -n "${CREATED_POI_ID}" && "${CREATED_POI_ID}" != "null" && "${LAT}" == "41.3851" && "${LON}" == "2.1734" ]]; then
        green "  PASS: create_poi — id=${CREATED_POI_ID} lat=${LAT} lon=${LON}"
        PASS=$((PASS+1))
    else
        red "  FAIL: create_poi — respuesta inesperada: ${CONTENT}"
        FAIL=$((FAIL+1))
    fi
fi

# ─────────────────────────────────────────────────────────────────────────────
info "\n=== 8. Tests de seguridad ==="

# Token de foto inexistente
RESP=$(rpc '{"jsonrpc":"2.0","id":91,"method":"tools/call","params":{"name":"create_poi","arguments":{"trip_id":1,"title":"x","type":"visit","latitude":0,"longitude":0,"photo_token":"photo_00000000000000000000000000000000"}}}')
if echo "${RESP}" | grep -qi "PHOTO_NOT_FOUND\|foto\|photo\|error" 2>/dev/null; then
    green "  PASS: photo_token inexistente — rechazado correctamente"
    PASS=$((PASS+1))
else
    red "  FAIL: photo_token inexistente — respuesta inesperada: ${RESP}"
    FAIL=$((FAIL+1))
fi

# trip_id inexistente en create_route
RESP=$(rpc '{"jsonrpc":"2.0","id":93,"method":"tools/call","params":{"name":"create_route","arguments":{"trip_id":999999,"transport_type":"bike","coordinates":[{"lat":0,"lon":0},{"lat":1,"lon":1}]}}}')
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

if [[ "${1:-}" != "--keep-data" && -n "${CREATED_TRIP_ID}" && "${CREATED_TRIP_ID}" != "null" ]]; then
    CLEANUP_OUTPUT=$(php -r '
        define("ROOT_PATH", $argv[1]);
        require ROOT_PATH . "/mcp/bootstrap.php";
        $tripId = (int)$argv[2];
        $trip = (new Trip())->getById($tripId);
        if ($trip && ($trip["title"] ?? "") === "TEST_MCP_TRIP" && (new Trip())->delete($tripId)) {
            echo "deleted";
        } else {
            echo "skipped";
        }
    ' "${ROOT_DIR}" "${CREATED_TRIP_ID}" 2>/dev/null || echo "failed")
    info "\n  Limpieza: trip id=${CREATED_TRIP_ID} (${CLEANUP_OUTPUT})"
elif [[ "${1:-}" == "--keep-data" && -n "${CREATED_TRIP_ID}" && "${CREATED_TRIP_ID}" != "null" ]]; then
    info "\n  Nota: Se conservó el trip id=${CREATED_TRIP_ID} (title='TEST_MCP_TRIP') por --keep-data."
fi

[[ "${FAIL}" == "0" ]] && exit 0 || exit 1
