#!/bin/bash

# === CONFIGURACIÓN BÁSICA ===
TARGET="$1"
SCAN_TYPE="$2"
GENERATE_REPORT="$3"
ZAP_PORT="$4"

RESULTADOS_DIR="resultados_zap"
mkdir -p "$RESULTADOS_DIR"

# === FUNCIONES DE LOG ===
log_info() { echo "[INFO] $(date '+%F %T') - $1"; }
log_error() { echo "[ERROR] $(date '+%F %T') - $1"; }

# === FUNCIONES DE ESCANEO ===

iniciar_zap() {
    log_info "Iniciando ZAP en puerto $ZAP_PORT..."
    zaproxy -daemon \
        -config api.disablekey=true \
        -port "$ZAP_PORT" \
        -config spider.maxDuration=10 \
        -config spider.maxDepth=5 \
        -config ascan.maxDuration=15 \
        > /dev/null 2>&1 &

    ZAP_PID=$!
    log_info "Esperando que ZAP esté listo..."
    for i in {1..30}; do
        if curl -s "http://localhost:$ZAP_PORT/JSON/core/view/version/" > /dev/null; then
            log_info "ZAP listo."
            return
        fi
        sleep 2
    done

    log_error "ZAP no se inició correctamente."
    exit 1
}

run_spider() {
    log_info "Ejecutando Spider sobre $TARGET"
    local scan_id
    scan_id=$(curl -s "http://localhost:$ZAP_PORT/JSON/spider/action/scan/?url=$TARGET" | jq -r '.scan')

    if [ -z "$scan_id" ]; then
        log_error "Fallo al iniciar Spider."
        return 1
    fi

    while true; do
        status=$(curl -s "http://localhost:$ZAP_PORT/JSON/spider/view/status/?scanId=$scan_id" | jq -r '.status')
        log_info "Spider progreso: $status%"
        [ "$status" -eq 100 ] && break
        sleep 5
    done

    curl -s "http://localhost:$ZAP_PORT/JSON/spider/view/results/?scanId=$scan_id" > "$RESULTADOS_DIR/urls.json"
}

run_active_scan() {
    local policy="$1"
    log_info "Ejecutando Active Scan ($policy)..."
    local scan_id
    scan_id=$(curl -s "http://localhost:$ZAP_PORT/JSON/ascan/action/scan/?url=$TARGET&scanPolicyName=$policy" | jq -r '.scan')

    if [ -z "$scan_id" ]; then
        log_error "Fallo al iniciar Active Scan."
        return 1
    fi

    while true; do
        status=$(curl -s "http://localhost:$ZAP_PORT/JSON/ascan/view/status/?scanId=$scan_id" | jq -r '.status')
        log_info "Active Scan progreso: $status%"
        [ "$status" -eq 100 ] && break
        sleep 5
    done
}

generar_reportes() {
    log_info "Generando reportes..."

    curl -s "http://localhost:$ZAP_PORT/JSON/core/view/alerts/" > "$RESULTADOS_DIR/alertas.json"
    curl -s "http://localhost:$ZAP_PORT/JSON/core/view/alertsSummary/" > "$RESULTADOS_DIR/resumen.json"

    if [ "$GENERATE_REPORT" == "true" ]; then
        curl -s "http://localhost:$ZAP_PORT/OTHER/core/other/htmlreport/" > "$RESULTADOS_DIR/reporte.html"
    fi

    generar_junit
}

generar_junit() {
    local input="$RESULTADOS_DIR/alertas.json"
    local output="$RESULTADOS_DIR/junit-report.xml"

    python3 - <<EOF
import json
from xml.etree.ElementTree import Element, SubElement, tostring
from xml.dom.minidom import parseString

with open("$input") as f:
    data = json.load(f)

alerts = data.get("alerts", [])
high = sum(1 for a in alerts if a.get("risk") == "High")
medium = sum(1 for a in alerts if a.get("risk") == "Medium")
low = sum(1 for a in alerts if a.get("risk") == "Low")

testsuite = Element('testsuite', name="ZAP Scan", tests=str(len(alerts)), failures=str(high + medium), time="0")

tc1 = SubElement(testsuite, 'testcase', name="High Risk", classname="ZAP")
if high > 0:
    SubElement(tc1, 'failure', message="High").text = f"{high} high risk found"

tc2 = SubElement(testsuite, 'testcase', name="Medium Risk", classname="ZAP")
if medium > 0:
    SubElement(tc2, 'failure', message="Medium").text = f"{medium} medium risk found"

tc3 = SubElement(testsuite, 'testcase', name="Low Risk", classname="ZAP")

xml_str = tostring(testsuite)
pretty = parseString(xml_str).toprettyxml()
with open("$output", "w") as f:
    f.write(pretty)
EOF
}

evaluar_estado() {
    local resumen="$RESULTADOS_DIR/resumen.json"
    local high=$(jq -r '.High // 0' "$resumen")
    local medium=$(jq -r '.Medium // 0' "$resumen")

    if [ "$high" -gt 0 ]; then
        echo "BUILD_STATUS=FAILURE" > build_status.txt
    elif [ "$medium" -gt 5 ]; then
        echo "BUILD_STATUS=UNSTABLE" > build_status.txt
    else
        echo "BUILD_STATUS=SUCCESS" > build_status.txt
    fi
}

cleanup() {
    if [ -n "$ZAP_PID" ]; then
        kill -9 "$ZAP_PID" 2>/dev/null || true
        log_info "ZAP detenido."
    fi
}
trap cleanup EXIT

# === EJECUCIÓN ===
log_info "Inicio ZAP Scan para: $TARGET"
iniciar_zap

case "$SCAN_TYPE" in
    spider-only)
        run_spider
        ;;
    quick)
        run_spider
        run_active_scan "Light"
        ;;
    full)
        run_spider
        run_active_scan ""
        ;;
    *)
        log_error "Tipo de escaneo no válido: $SCAN_TYPE"
        exit 1
        ;;
esac

generar_reportes
evaluar_estado

log_info "Análisis finalizado. Resultados guardados en $RESULTADOS_DIR"

