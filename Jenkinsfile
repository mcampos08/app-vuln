pipeline {
    agent any
    
    environment {
        ZAP_HOST = '192.168.81.131'  // IP de tu Kali
        ZAP_PORT = '8080'
        TARGET_URL = 'https://192.168.81.129'
    }
    
    stages {
        stage('DAST Scan') {
            steps {
                script {
                    // Verificar conexión
                    sh "curl -f http://${ZAP_HOST}:${ZAP_PORT}/ || exit 1"
                    
                    // Scan básico
                    sh '''
                        # Spider
                        curl "http://${ZAP_HOST}:${ZAP_PORT}/JSON/spider/action/scan/?url=${TARGET_URL}"
                        echo "Esperando spider..."
                        sleep 60
                        
                        # Active Scan
                        curl "http://${ZAP_HOST}:${ZAP_PORT}/JSON/ascan/action/scan/?url=${TARGET_URL}"
                        echo "Esperando active scan..."
                        sleep 180
                        
                        # Generar reporte
                        curl "http://${ZAP_HOST}:${ZAP_PORT}/OTHER/core/other/htmlreport/" > zap-report.html
                    '''
                }
                
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
}

