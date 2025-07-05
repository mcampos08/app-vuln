pipeline {
    agent any
    
    environment {
        KALI_HOST = '192.168.81.131'
        KALI_USER = 'kali'
        TARGET_URL = 'http://192.168.81.129'
    }
    
    stages {
        stage('DAST Scan') {
            steps {
                script {
                    // Crear script en Kali y ejecutar
                    withCredentials([sshUserPrivateKey(credentialsId: 'kali-ssh-key', keyFileVariable: 'SSH_KEY', usernameVariable: 'SSH_USER')]) {
                        sh '''
                            # Crear script ZAP remotamente
                            ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no $KALI_USER@$KALI_HOST << 'EOF_SCRIPT'
#!/bin/bash
TARGET_URL="http://192.168.81.129"

echo "=== Verificando ZAP ==="
if ! systemctl is-active --quiet zaproxy; then
    echo "ERROR: ZAP no está corriendo"
    exit 1
fi

echo "=== Verificando conexión a ZAP API ==="
if ! curl -f http://127.0.0.1:8080/ >/dev/null 2>&1; then
    echo "ERROR: ZAP API no responde"
    exit 1
fi

echo "=== Ejecutando spider ==="
SPIDER_RESPONSE=$(curl -s "http://127.0.0.1:8080/JSON/spider/action/scan/?url=${TARGET_URL}")
echo "Spider response: $SPIDER_RESPONSE"

SPIDER_ID=$(echo "$SPIDER_RESPONSE" | grep -o '"scan":"[^"]*' | cut -d'"' -f4)
echo "Spider ID: $SPIDER_ID"

if [ -z "$SPIDER_ID" ]; then
    echo "ERROR: No se pudo obtener Spider ID"
    exit 1
fi

echo "=== Esperando spider (60 segundos) ==="
sleep 60

echo "=== Ejecutando active scan ==="
ASCAN_RESPONSE=$(curl -s "http://127.0.0.1:8080/JSON/ascan/action/scan/?url=${TARGET_URL}")
echo "Active scan response: $ASCAN_RESPONSE"

ASCAN_ID=$(echo "$ASCAN_RESPONSE" | grep -o '"scan":"[^"]*' | cut -d'"' -f4)
echo "Active scan ID: $ASCAN_ID"

if [ -z "$ASCAN_ID" ]; then
    echo "ERROR: No se pudo obtener Active Scan ID"
    exit 1
fi

echo "=== Esperando active scan (120 segundos) ==="
sleep 120

echo "=== Generando reporte ==="
REPORT_FILE="/tmp/zap-report-$(date +%Y%m%d-%H%M%S).html"
curl "http://127.0.0.1:8080/OTHER/core/other/htmlreport/" > "$REPORT_FILE"

echo "Scan completado. Reporte: $REPORT_FILE"
echo "REPORT_PATH=$REPORT_FILE"
EOF_SCRIPT
                        '''
                        
                        // Obtener el path del reporte generado
                        sh '''
                            REPORT_PATH=$(ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no $KALI_USER@$KALI_HOST 'ls -t /tmp/zap-report-*.html 2>/dev/null | head -1')
                            echo "Copiando reporte: $REPORT_PATH"
                            scp -i "$SSH_KEY" -o StrictHostKeyChecking=no $KALI_USER@$KALI_HOST:"$REPORT_PATH" ./zap-report.html
                        '''
                    }
                }
                
                // Publicar reporte
                publishHTML([
                    allowMissing: false,
                    alwaysLinkToLastBuild: true,
                    keepAll: true,
                    reportDir: '.',
                    reportFiles: 'zap-report.html',
                    reportName: 'ZAP Security Report'
                ])
            }
        }
    }
    
    post {
        always {
            // Limpiar archivos remotos
            script {
                try {
                    withCredentials([sshUserPrivateKey(credentialsId: 'kali-ssh-key', keyFileVariable: 'SSH_KEY', usernameVariable: 'SSH_USER')]) {
                        sh '''
                            ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no $KALI_USER@$KALI_HOST 'rm -f /tmp/zap-report-*.html' || true
                        '''
                    }
                } catch (Exception e) {
                    echo "Error limpiando archivos remotos: ${e.getMessage()}"
                }
            }
        }
    }
}
