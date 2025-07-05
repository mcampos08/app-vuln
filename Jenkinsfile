pipeline {
    agent any

    environment {
        // Configuración de la aplicación objetivo
        ZAP_TARGET_URL = 'http://192.168.81.129'
        
        // Configuración VM-Kali
        ZAP_VM_IP = '192.168.81.131'
        ZAP_USER = 'kali'
        
        // Configuración de reportes
        ZAP_REPORT_JSON = 'zap-report.json'
        ZAP_REPORT_HTML = 'zap-report.html'
        ZAP_REPORT_XML = 'zap-report.xml'
        
        // Configuración ZAP
        ZAP_PORT = '8090'
        ZAP_TIMEOUT = '300'  // 5 minutos timeout
    }

    stages {
        stage('🔍 Verificar conectividad') {
            steps {
                echo '=== VERIFICANDO CONECTIVIDAD ==='
                script {
                    // Verificar conexión SSH a VM-Kali
                    sshagent(['kali-ssh-key']) {
                        sh """
                            echo "Probando conexión SSH a VM-Kali..."
                            ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 ${ZAP_USER}@${ZAP_VM_IP} \\
                            'echo "✅ SSH OK - \$(hostname) - \$(date)"'
                        """
                    }
                    
                    // Verificar que la aplicación objetivo esté disponible
                    echo "Verificando aplicación objetivo..."
                    def response = sh(
                        script: "curl -s -o /dev/null -w '%{http_code}' --connect-timeout 10 ${ZAP_TARGET_URL}",
                        returnStdout: true
                    ).trim()
                    
                    if (response == '200') {
                        echo "✅ Aplicación disponible en ${ZAP_TARGET_URL} (HTTP ${response})"
                    } else {
                        echo "⚠️  Aplicación responde con HTTP ${response}"
                    }
                }
            }
        }

        stage('🛠️ Preparar entorno ZAP') {
            steps {
                echo '=== PREPARANDO ENTORNO ZAP ==='
                script {
                    sshagent(['kali-ssh-key']) {
                        sh """
                            ssh -o StrictHostKeyChecking=no ${ZAP_USER}@${ZAP_VM_IP} '
                                echo "Preparando entorno ZAP..."
                                
                                # Crear directorio para reportes
                                mkdir -p ~/zap-test-reports
                                
                                # Verificar instalación de ZAP
                                echo "Verificando ZAP..."
                                if command -v zaproxy >/dev/null 2>&1; then
                                    echo "✅ ZAP instalado correctamente"
                                    zaproxy -version
                                else
                                    echo "❌ ZAP no encontrado"
                                    exit 1
                                fi
                                
                                # Verificar dependencias
                                echo "Verificando dependencias..."
                                which curl jq || (echo "❌ curl o jq no instalados" && exit 1)
                                
                                # Limpiar procesos ZAP anteriores
                                echo "Limpiando procesos ZAP anteriores..."
                                pkill -f "zaproxy" || true
                                sleep 5
                                
                                echo "✅ Entorno preparado"
                            '
                        """
                    }
                }
            }
        }

        stage('🚀 Ejecutar ZAP Scan') {
            steps {
                echo '=== EJECUTANDO ZAP SCAN ==='
                script {
                    sshagent(['kali-ssh-key']) {
                        sh """
                            ssh -o StrictHostKeyChecking=no ${ZAP_USER}@${ZAP_VM_IP} '
                                cd ~/zap-test-reports
                                
                                echo "Iniciando ZAP daemon..."
                                zaproxy -daemon -port ${ZAP_PORT} -host 0.0.0.0 &
                                ZAP_PID=\$!
                                echo "ZAP PID: \$ZAP_PID"
                                
                                # Función para verificar ZAP
                                check_zap_ready() {
                                    local max_attempts=30
                                    local attempt=1
                                    
                                    while [ \$attempt -le \$max_attempts ]; do
                                        if curl -s "http://localhost:${ZAP_PORT}/JSON/core/view/version/" >/dev/null 2>&1; then
                                            echo "✅ ZAP está listo"
                                            return 0
                                        fi
                                        echo "⏳ Esperando ZAP... (intento \$attempt/\$max_attempts)"
                                        sleep 5
                                        attempt=\$((attempt + 1))
                                    done
                                    
                                    echo "❌ ZAP no está respondiendo"
                                    return 1
                                }
                                
                                # Esperar que ZAP esté listo
                                if ! check_zap_ready; then
                                    echo "❌ Error: ZAP no inició correctamente"
                                    kill \$ZAP_PID 2>/dev/null || true
                                    exit 1
                                fi
                                
                                # Obtener versión de ZAP
                                echo "Versión ZAP:"
                                curl -s "http://localhost:${ZAP_PORT}/JSON/core/view/version/" | jq -r .version
                                
                                # Configurar contexto
                                echo "Configurando contexto..."
                                curl -s "http://localhost:${ZAP_PORT}/JSON/context/action/newContext/?contextName=TestContext"
                                curl -s "http://localhost:${ZAP_PORT}/JSON/context/action/includeInContext/?contextName=TestContext&regex=${ZAP_TARGET_URL}.*"
                                
                                # Ejecutar Spider
                                echo "🕷️  Ejecutando Spider scan..."
                                SPIDER_RESPONSE=\$(curl -s "http://localhost:${ZAP_PORT}/JSON/spider/action/scan/?url=${ZAP_TARGET_URL}&recurse=true&inScopeOnly=false&contextName=TestContext&subtreeOnly=false")
                                SPIDER_ID=\$(echo \$SPIDER_RESPONSE | jq -r .scan)
                                echo "Spider ID: \$SPIDER_ID"
                                
                                # Monitorear Spider
                                while true; do
                                    STATUS=\$(curl -s "http://localhost:${ZAP_PORT}/JSON/spider/view/status/?scanId=\$SPIDER_ID" | jq -r .status)
                                    if [ "\$STATUS" = "100" ]; then
                                        echo "✅ Spider completado"
                                        break
                                    fi
                                    echo "🕷️  Spider progreso: \$STATUS%"
                                    sleep 10
                                done
                                
                                # Mostrar URLs encontradas
                                echo "URLs encontradas por Spider:"
                                curl -s "http://localhost:${ZAP_PORT}/JSON/spider/view/results/?scanId=\$SPIDER_ID" | jq -r .results[]
                                
                                # Ejecutar Active Scan
                                echo "⚡ Ejecutando Active scan..."
                                ASCAN_RESPONSE=\$(curl -s "http://localhost:${ZAP_PORT}/JSON/ascan/action/scan/?url=${ZAP_TARGET_URL}&recurse=true&inScopeOnly=false&scanPolicyName=&method=&postData=&contextId=")
                                ASCAN_ID=\$(echo \$ASCAN_RESPONSE | jq -r .scan)
                                echo "Active Scan ID: \$ASCAN_ID"
                                
                                # Monitorear Active Scan
                                while true; do
                                    STATUS=\$(curl -s "http://localhost:${ZAP_PORT}/JSON/ascan/view/status/?scanId=\$ASCAN_ID" | jq -r .status)
                                    if [ "\$STATUS" = "100" ]; then
                                        echo "✅ Active scan completado"
                                        break
                                    fi
                                    echo "⚡ Active scan progreso: \$STATUS%"
                                    sleep 15
                                done
                                
                                # Obtener resumen de alertas
                                echo "📊 Resumen de alertas:"
                                curl -s "http://localhost:${ZAP_PORT}/JSON/core/view/alerts/" | jq -r ".alerts | group_by(.risk) | map({risk: .[0].risk, count: length}) | .[]"
                                
                                # Generar reportes
                                echo "📄 Generando reportes..."
                                curl -s "http://localhost:${ZAP_PORT}/JSON/core/view/jsonreport/" > ~/zap-test-reports/zap-report.json
				curl -s "http://localhost:${ZAP_PORT}/OTHER/core/other/htmlreport/" > ~/zap-test-reports/zap-report.html
				curl -s "http://localhost:${ZAP_PORT}/OTHER/core/other/xmlreport/" > ~/zap-test-reports/zap-report.xml

                                
                                # Verificar que los reportes se generaron
                                for report in zap-report.json zap-report.html zap-report.xml; do
                                    if [ -f \$report ]; then
                                        echo "✅ \$report generado (\$(stat -c%s \$report) bytes)"
                                    else
                                        echo "❌ Error generando \$report"
                                    fi
                                done
                                
                                # Detener ZAP
                                echo "🛑 Deteniendo ZAP..."
                                curl -s "http://localhost:${ZAP_PORT}/JSON/core/action/shutdown/"
                                sleep 5
                                kill \$ZAP_PID 2>/dev/null || true
                                
                                echo "✅ ZAP scan completado exitosamente"
                            '
                        """
                    }
                }
            }
        }

        stage('📥 Descargar reportes') {
            steps {
                echo '=== DESCARGANDO REPORTES ==='
                script {
                    // Crear directorio local para reportes
                    sh 'mkdir -p zap-test-reports'
                    
                    sshagent(['kali-ssh-key']) {
                        sh """
                            echo "Descargando reportes desde VM-Kali..."
                            
                            # Descargar cada reporte
                            scp ${ZAP_USER}@${ZAP_VM_IP}:~/zap-test-reports/zap-report.json ./zap-test-reports/${ZAP_REPORT_JSON}
                            scp ${ZAP_USER}@${ZAP_VM_IP}:~/zap-test-reports/zap-report.html ./zap-test-reports/${ZAP_REPORT_HTML}
                            scp ${ZAP_USER}@${ZAP_VM_IP}:~/zap-test-reports/zap-report.xml ./zap-test-reports/${ZAP_REPORT_XML}
                            
                            echo "Verificando reportes descargados..."
                            ls -la zap-test-reports/
                        """
                    }
                }
            }
        }

        stage('📊 Análizar resultados') {
            steps {
                echo '=== ANALIZANDO RESULTADOS ==='
                script {
                    // Analizar reporte JSON
                    if (fileExists("zap-test-reports/${ZAP_REPORT_JSON}")) {
                        def zapReport = readJSON file: "zap-test-reports/${ZAP_REPORT_JSON}"
                        
                        def highRiskCount = 0
                        def mediumRiskCount = 0
                        def lowRiskCount = 0
                        def infoCount = 0
                        
                        echo "📈 RESUMEN DE VULNERABILIDADES:"
                        echo "================================"
                        
                        // Contar alertas por nivel de riesgo
                        zapReport.site.each { site ->
                            site.alerts.each { alert ->
                                switch(alert.riskdesc) {
                                    case ~/.*High.*/:
                                        highRiskCount++
                                        echo "🔴 HIGH: ${alert.name} - ${alert.desc}"
                                        break
                                    case ~/.*Medium.*/:
                                        mediumRiskCount++
                                        echo "🟡 MEDIUM: ${alert.name}"
                                        break
                                    case ~/.*Low.*/:
                                        lowRiskCount++
                                        echo "🔵 LOW: ${alert.name}"
                                        break
                                    default:
                                        infoCount++
                                        echo "ℹ️  INFO: ${alert.name}"
                                }
                            }
                        }
                        
                        echo "================================"
                        echo "📊 TOTALES:"
                        echo "   🔴 Alto riesgo: ${highRiskCount}"
                        echo "   🟡 Medio riesgo: ${mediumRiskCount}"
                        echo "   🔵 Bajo riesgo: ${lowRiskCount}"
                        echo "   ℹ️  Informativo: ${infoCount}"
                        echo "================================"
                        
                        // Guardar métricas para el post
                        writeFile file: 'zap-metrics.txt', text: """
HIGH=${highRiskCount}
MEDIUM=${mediumRiskCount}
LOW=${lowRiskCount}
INFO=${infoCount}
"""
                        
                        // Determinar si el scan es aceptable
                        if (highRiskCount > 0) {
                            echo "⚠️  ADVERTENCIA: Se encontraron ${highRiskCount} vulnerabilidades de alto riesgo"
                        } else {
                            echo "✅ No se encontraron vulnerabilidades críticas"
                        }
                        
                    } else {
                        error "❌ No se pudo leer el reporte JSON"
                    }
                }
            }
        }
    }

    post {
        always {
            echo '=== POST-PROCESAMIENTO ==='
            
            // Archivar reportes
            archiveArtifacts artifacts: 'zap-test-reports/*', fingerprint: true, allowEmptyArchive: true
            
            // Publicar reporte HTML
            publishHTML([
                allowMissing: false,
                alwaysLinkToLastBuild: true,
                keepAll: true,
                reportDir: 'zap-test-reports',
                reportFiles: "${ZAP_REPORT_HTML}",
                reportName: 'ZAP Test Report',
                reportTitles: 'OWASP ZAP Security Report'
            ])
            
            // Mostrar resumen final
            script {
                if (fileExists('zap-metrics.txt')) {
                    def metrics = readFile('zap-metrics.txt')
                    echo "📋 RESUMEN FINAL DEL SCAN:"
                    echo metrics
                } else {
                    echo "⚠️  No se pudieron obtener métricas del scan"
                }
            }
            
            // Limpiar procesos ZAP remotos (por si acaso)
            script {
                try {
                    sshagent(['kali-ssh-key']) {
                        sh """
                            ssh -o StrictHostKeyChecking=no ${ZAP_USER}@${ZAP_VM_IP} \\
                            'pkill -f "zaproxy" || true; echo "Limpieza completada"'
                        """
                    }
                } catch (Exception e) {
                    echo "Advertencia: No se pudo limpiar procesos remotos: ${e.message}"
                }
            }
        }
        
        success {
            echo '🎉 PRUEBA ZAP COMPLETADA EXITOSAMENTE'
            echo '📊 Revisa el "ZAP Test Report" en la página del build'
            echo '📁 Los reportes están disponibles en los artefactos'
        }
        
        failure {
            echo '❌ PRUEBA ZAP FALLÓ'
            echo '🔍 Posibles causas:'
            echo '   - Problema de conectividad SSH'
            echo '   - ZAP no está instalado correctamente'
            echo '   - Aplicación objetivo no disponible'
            echo '   - Error en configuración de puertos'
            echo '   - Falta de dependencias (curl, jq)'
        }
        
        cleanup {
            echo '🧹 Limpiando archivos temporales...'
            sh 'rm -f zap-metrics.txt'
        }
    }
}
