pipeline {
    agent any

    parameters {
        string(name: 'TARGET_URL', defaultValue: 'http://192.168.81.130', description: 'URL del target a escanear')
        choice(name: 'SCAN_TYPE', choices: ['full', 'quick', 'spider-only'], description: 'Tipo de escaneo')
        booleanParam(name: 'GENERATE_REPORT', defaultValue: true, description: 'Generar reporte HTML')
    }

    environment {
        VM_STAGING_IP = '192.168.72.130'
        STAGING_USER = 'clindata'
        STAGING_PATH = '/var/www/html/clindata'
        GITHUB_REPO = 'https://github.com/mcampos08/app-vuln.git'

        KALI_HOST = '192.168.81.131'
        KALI_USER = 'kali'
        ZAP_PORT = '8080'

        SONARQUBE_SERVER = 'SonarQube-Local'
        SYFT_OUTPUT = 'sbom.json'
        GRYPE_REPORT = 'grype-report.json'
        GRYPE_SARIF = 'grype-report.sarif'
    }

    stages {

        stage('📥 Clonar código') {
            steps {
                git url: "${GITHUB_REPO}", branch: 'main'
            }
        }

        
        stage('🔍 Análisis SAST con SonarQube') {
            steps {
                script {
                    def scannerHome = tool 'SonarQubeScanner'
                    withSonarQubeEnv("${SONARQUBE_SERVER}") {
                        sh """
                            ${scannerHome}/bin/sonar-scanner \
                                -Dsonar.projectKey=clindata-app \
                                -Dsonar.projectName='Clindata App' \
                                -Dsonar.sources=. \
                                -Dsonar.exclusions=**/*.log,**/*.md
                        """
                    }
                }
            }
        }

        stage('🧬 Generar SBOM con Syft') {
            steps {
                sh "syft dir:. -o json > ${SYFT_OUTPUT}"
            }
        }

        stage('🧪 Escaneo con Grype') {
            steps {
                sh """
                    set +e
                    grype sbom:${SYFT_OUTPUT} -o json > ${GRYPE_REPORT}
                    grype sbom:${SYFT_OUTPUT} -o sarif > ${GRYPE_SARIF}
                    grype sbom:${SYFT_OUTPUT} -o table --fail-on high
                    if [ \$? -ne 0 ]; then echo "BUILD_SHOULD_FAIL=true" > grype.fail; fi
                    set -e
                """
            }
        }

        stage('🚀 Despliegue en Staging') {
            steps {
                sshagent(['staging-ssh-key']) {
                    sh """
                        echo 'Desplegando app en VM Staging...'
                        rsync -avz --delete ./ ${STAGING_USER}@${VM_STAGING_IP}:${STAGING_PATH}
                    """
                }
            }
        }

        stage('⚡ Ejecutar ZAP Scan') {
            steps {
                sshagent(['kali-ssh-key']) {
                    script {
                        def remoteDir = "/tmp/zap-scan-${BUILD_NUMBER}"
                        sh """
                            ssh -o StrictHostKeyChecking=no ${KALI_USER}@${KALI_HOST} 'mkdir -p ${remoteDir}'
                            scp tools/zap/zap_scan.sh ${KALI_USER}@${KALI_HOST}:${remoteDir}/
                            ssh -o StrictHostKeyChecking=no ${KALI_USER}@${KALI_HOST} '
                                cd ${remoteDir}
                                chmod +x zap_scan.sh
                                ./zap_scan.sh "${params.TARGET_URL}" "${params.SCAN_TYPE}" "${params.GENERATE_REPORT}" "${ZAP_PORT}"
                            '
                        """
                    }
                }
            }
        }

        stage('📥 Recopilar Resultados ZAP') {
            steps {
                sshagent(['kali-ssh-key']) {
                    script {
                        def remoteDir = "/tmp/zap-scan-${BUILD_NUMBER}"
                        sh """
                            mkdir -p ${WORKSPACE}/zap-results
                            scp -o StrictHostKeyChecking=no -r ${KALI_USER}@${KALI_HOST}:${remoteDir}/resultados_zap/* ${WORKSPACE}/zap-results/
                            scp -o StrictHostKeyChecking=no ${KALI_USER}@${KALI_HOST}:${remoteDir}/build_status.txt ${WORKSPACE}/ || echo "BUILD_STATUS=UNKNOWN" > ${WORKSPACE}/build_status.txt
                            ssh -o StrictHostKeyChecking=no ${KALI_USER}@${KALI_HOST} 'rm -rf ${remoteDir}'
                        """
                    }
                }
            }
        }

        stage('📊 Publicar Resultados ZAP') {
            steps {
                script {
                    if (fileExists('zap-results/junit-report.xml')) {
                        junit 'zap-results/junit-report.xml'
                    }

                    archiveArtifacts artifacts: 'zap-results/**/*', allowEmptyArchive: true

                    if (params.GENERATE_REPORT && fileExists('zap-results/reporte.html')) {
                        publishHTML([
                            reportDir: 'zap-results',
                            reportFiles: 'reporte.html',
                            reportName: 'ZAP Security Report',
                            alwaysLinkToLastBuild: true,
                            keepAll: true,
                            allowMissing: false
                        ])
                    }
                }
            }
        }

        stage('📌 Determinar Estado del Build') {
            steps {
                script {
                    def buildStatus = readFile('build_status.txt').trim().split('=')[1]
                    switch(buildStatus) {
                        case 'SUCCESS':
                            echo "✅ Build exitoso"
                            break
                        case 'UNSTABLE':
                            echo "⚠️  Build inestable"
                            currentBuild.result = 'UNSTABLE'
                            break
                        case 'FAILURE':
                            echo "❌ Build fallido"
                            currentBuild.result = 'FAILURE'
                            break
                        default:
                            echo "❓ Estado desconocido"
                            currentBuild.result = 'UNSTABLE'
                    }
                }
            }
        }
    }

    post {
        always {
            archiveArtifacts artifacts: '*.json', fingerprint: true
            recordIssues(tools: [sarif(pattern: "${GRYPE_SARIF}")])

            script {
                if (fileExists('grype.fail')) {
                    currentBuild.result = 'FAILURE'
                    echo '❌ Vulnerabilidades críticas detectadas (Grype)'
                } else {
                    echo '✅ Análisis Grype aprobado'
                }
            }
        }

        success {
            echo '🎉 PIPELINE COMPLETADO CORRECTAMENTE'
        }

        failure {
            echo '🚨 FALLO EN PIPELINE - REVISAR LOGS'
        }
    }
}
