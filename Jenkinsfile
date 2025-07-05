pipeline {
    agent any
    
    environment {
        KALI_HOST = '192.168.81.131'  // IP de tu Kali
        KALI_USER = 'kali'         // Usuario SSH
        TARGET_URL = 'http://192.168.81.129'
        SSH_KEY = credentials('kali-ssh-key')  // ID de tu credential en Jenkins
    }
    
    stages {
        stage('DAST Scan') {
            steps {
                script {
                    // Ejecutar script ZAP remotamente
                    sh """
                        ssh -i ${SSH_KEY} -o StrictHostKeyChecking=no ${KALI_USER}@${KALI_HOST} '
                            # Verificar que ZAP esté corriendo
                            if ! systemctl is-active --quiet zaproxy; then
                                echo "ERROR: ZAP no está corriendo"
                                exit 1
                            fi
                            
                            # Verificar conexión a ZAP
                            curl -f http://127.0.0.1:8080/ || {
                                echo "ERROR: ZAP API no responde"
                                exit 1
                            }
                            
                            # Ejecutar scan
                            TARGET_URL="${TARGET_URL}"
                            
                            echo "=== Ejecutando spider ==="
                            SPIDER_ID=\$(curl -s "http://127.0.0.1:8080/JSON/spider/action/scan/?url=\${TARGET_URL}" | grep -o "\"scan\":\"[^\"]*" | cut -d"\"" -f4)
                            echo "Spider ID: \$SPIDER_ID"
                            sleep 60
                            
                            echo "=== Ejecutando active scan ==="
                            ASCAN_ID=\$(curl -s "http://127.0.0.1:8080/JSON/ascan/action/scan/?url=\${TARGET_URL}" | grep -o "\"scan\":\"[^\"]*" | cut -d"\"" -f4)
                            echo "Active scan ID: \$ASCAN_ID"
                            sleep 120
                            
                            echo "=== Generando reporte ==="
                            curl "http://127.0.0.1:8080/OTHER/core/other/htmlreport/" > /tmp/zap-report-\$(date +%Y%m%d-%H%M%S).html
                            
                            echo "Scan completado"
                        '
                    """
                    
                    // Copiar reporte desde Kali a Jenkins
                    sh """
                        scp -i ${SSH_KEY} -o StrictHostKeyChecking=no ${KALI_USER}@${KALI_HOST}:/tmp/zap-report-*.html ./zap-report.html
                    """
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
            sh """
                ssh -i ${SSH_KEY} -o StrictHostKeyChecking=no ${KALI_USER}@${KALI_HOST} 'rm -f /tmp/zap-report-*.html'
            """
        }
    }
}
