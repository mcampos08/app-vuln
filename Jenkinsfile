pipeline {
    agent any

    environment {
        VM_STAGING_IP = '192.168.72.128'
        STAGING_USER = 'clindata'
        STAGING_PATH = '/var/www/html/clindata'
        GITHUB_REPO = 'https://github.com/mcampos08/owasp-app.git'

        SONARQUBE_SERVER = 'SonarQube-Local'
        SYFT_OUTPUT = 'sbom.json'
        GRYPE_REPORT = 'grype-report.json'
        GRYPE_SARIF = 'grype-report.sarif'
    }

    stages {

        // MÓDULO 1: ANÁLISIS DEL CÓDIGO
        stage('📥 Clonar código') {
            steps {
                echo 'Clonando repositorio...'
                git url: "${GITHUB_REPO}", branch: 'main'
            }
        }

        stage('📦 Instalar dependencias PHP') {
            steps {
                echo 'Instalando dependencias PHP...'
                sh 'composer install --no-interaction --prefer-dist'
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

        stage('✅ Evaluar Quality Gate') {
            steps {
                timeout(time: 2, unit: 'MINUTES') {
                    waitForQualityGate abortPipeline: true
                }
            }
        }

        // MÓDULO 2: ANÁLISIS DE DEPENDENCIAS
        stage('🧬 Generar SBOM con Syft') {
            steps {
                echo 'Generando SBOM...'
                sh "syft dir:. -o json > ${SYFT_OUTPUT}"
            }
        }

        stage('🧪 Escaneo con Grype') {
            steps {
                echo 'Ejecutando análisis de vulnerabilidades...'
                sh """
                    set +e
                    grype sbom:${SYFT_OUTPUT} -o json > ${GRYPE_REPORT}
                    grype sbom:${SYFT_OUTPUT} -o sarif > ${GRYPE_SARIF}
                    grype sbom:${SYFT_OUTPUT} -o table --fail-on high
                    if [ \$? -ne 0 ]; then
                        echo "BUILD_SHOULD_FAIL=true" > grype.fail
                    fi
                    set -e
                """
            }
        }

        // MÓDULO 3: DESPLIEGUE A VM-STAGING
        stage('🔗 Prueba de conexión SSH') {
            steps {
                echo 'Validando conexión a máquina de staging...'
                script {
                    sshagent(['staging-ssh-key']) {
                        sh """
                            ssh -o StrictHostKeyChecking=no ${STAGING_USER}@${VM_STAGING_IP} \\
                            'echo "[SSH OK] \$(hostname) - \$(date)"; uptime'
                        """
                    }
                }
            }
        }


        stage('🚀 Despliegue en Staging') {
            steps {
                echo 'Realizando despliegue...'
                script {
                    sshagent(['staging-ssh-key']) {
                        sh """
                            rsync -avz --delete ./ ${STAGING_USER}@${VM_STAGING_IP}:${STAGING_PATH}
                        """
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
                    echo '❌ Vulnerabilidades críticas detectadas. Build marcado como FALLIDO.'
                } else {
                    echo '✅ Análisis Grype aprobado.'
                }
            }
        }

        success {
            echo '🎉 PIPELINE COMPLETO - TODO CORRECTO'
        }

        failure {
            echo '🚨 ERROR EN ALGUNA ETAPA - REVISAR LOGS'
        }
    }
}
