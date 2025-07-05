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
                withCredentials([sshUserPrivateKey(credentialsId: 'kali-ssh-key', keyFileVariable: 'SSH_KEY')]) {
                    script {
                        // Ejecutar el escaneo con ZAP desde Kali
                        sh '''
                        ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no $KALI_USER@$KALI_HOST << 'ENDSSH'
                            if ! systemctl is-active --quiet zaproxy; then
                                echo "ERROR: ZAP no está corriendo"
                                exit 1
                            fi

                            if ! curl -f http://127.0.0.1:8080/ > /dev/null; then
                                echo "ERROR: ZAP API no responde"
                                exit 1
                            fi

                            TARGET_URL="http://192.168.81.129"

                            echo "=== Ejecutando spider ==="
                            SPIDER_ID=$(curl -s "http://127.0.0.1:8080/JSON/spider/action/scan/?url=${TARGET_URL}" | jq -r .scan)
                            echo "Spider ID: $SPIDER_ID"
                            sleep 60

                            echo "=== Ejecutando active scan ==="
                            ASCAN_ID=$(curl -s "http://127.0.0.1:8080/JSON/ascan/action/scan/?url=${TARGET_URL}" | jq -r .scan)
                            echo "Active scan ID: $ASCAN_ID"
                            sleep 120

                            echo "=== Generando reporte ==="
                            curl "http://127.0.0.1:8080/OTHER/core/other/htmlreport/" > /tmp/zap-report.html

                            echo "Scan completado"
                        ENDSSH
                        '''

                        // Copiar el reporte de Kali a Jenkins
                        sh '''
                        scp -i "$SSH_KEY" -o StrictHostKeyChecking=no $KALI_USER@$KALI_HOST:/tmp/zap-report.html ./zap-report.html
                        '''
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
    }

    post {
        always {
            withCredentials([sshUserPrivateKey(credentialsId: 'kali-ssh-key', keyFileVariable: 'SSH_KEY')]) {
                sh '''
                ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no $KALI_USER@$KALI_HOST 'rm -f /tmp/zap-report.html'
                '''
            }
        }
    }
}

