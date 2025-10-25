/**
 * Componente principal del asistente de voz para Alpine.js
 * Orquesta todos los servicios y maneja la lógica de la interfaz
 */
function voiceAssistantWidget() {
    return {
        // Estado del componente
        isOpen: false,
        isListening: false,
        isProcessing: false,
        isPlayingAudio: false,
        currentStatus: '',
        conversations: [],
        currentTranscription: '', // Para mostrar transcripción en tiempo real
        
        // Servicios
        audioRecorder: null,
        voiceTranscription: null,
        apiClient: null,
        currentAudioUrl: null,
        
        // Configuración
        maxConversations: 50,
        autoCleanupInterval: 300000, // 5 minutos
        cleanupTimer: null,

        /**
         * Inicializa el asistente de voz cuando Alpine.js monta el componente
         */
        init() {
            this.initializeVoiceAssistant();
        },

        /**
         * Inicializa el asistente de voz
         */
        async initializeVoiceAssistant() {
            try {
                console.log('Inicializando asistente de voz...');
                
                // Verificar soporte del navegador
                this.checkBrowserSupport();
                
                // Inicializar servicios
                this.audioRecorder = new AudioRecorderService();
                this.voiceTranscription = new VoiceTranscriptionService();
                this.apiClient = new VoiceAssistantApiClient();
                
                // Configurar transcripción para español mexicano
                this.voiceTranscription.optimizeForSpanish();
                
                // Configurar callbacks de transcripción
                this.voiceTranscription.setCallbacks({
                    onResult: this.handleTranscriptionResult.bind(this),
                    onError: this.handleTranscriptionError.bind(this),
                    onStart: this.handleTranscriptionStart.bind(this),
                    onEnd: this.handleTranscriptionEnd.bind(this)
                });
                
                // Cargar historial de conversaciones si existe
                await this.loadConversationHistory();
                
                // Configurar limpieza automática
                this.setupAutoCleanup();
                
                console.log('Asistente de voz inicializado correctamente');
            } catch (error) {
                console.error('Error inicializando asistente de voz:', error);
                this.showError(error.message);
            }
        },

        /**
         * Alterna la visibilidad del panel del asistente
         */
        toggleAssistant() {
            this.isOpen = !this.isOpen;
            
            if (this.isOpen) {
                this.scrollToBottom();
                // Verificar estado de la API
                this.checkApiConnection();
            } else {
                // Detener cualquier grabación activa
                this.stopListening();
            }
        },

        /**
         * Cierra el panel del asistente
         */
        closeAssistant() {
            this.isOpen = false;
            this.stopListening();
        },

        /**
         * Solicita permisos de micrófono explícitamente
         */
        async requestMicrophonePermission() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                // Cerrar el stream inmediatamente después de obtener permiso
                stream.getTracks().forEach(track => track.stop());
                return true;
            } catch (error) {
                console.error('Error solicitando permiso de micrófono:', error);
                if (error.name === 'NotAllowedError') {
                    alert('Por favor permite el acceso al micrófono en tu navegador.\n\n' +
                          '1. Haz clic en el icono de candado/info en la barra de direcciones\n' +
                          '2. Busca "Micrófono" o "Microphone"\n' +
                          '3. Cambia a "Permitir" o "Allow"\n' +
                          '4. Recarga la página');
                }
                return false;
            }
        },

        /**
         * Alterna entre iniciar y detener la escucha (toggle)
         */
        async toggleListening() {
            if (this.isListening) {
                await this.stopListening();
            } else {
                await this.startListening();
            }
        },

        /**
         * Inicia la escucha de voz
         */
        async startListening() {
            try {
                if (this.isProcessing) {
                    return;
                }

                // Solicitar permisos si no están concedidos
                if (!this.audioRecorder.isReady()) {
                    this.currentStatus = 'Solicitando permisos...';
                    const hasPermission = await this.requestMicrophonePermission();
                    
                    if (!hasPermission) {
                        this.currentStatus = '';
                        return;
                    }
                }

                // Inicializar grabador si es necesario
                if (!this.audioRecorder.isReady()) {
                    await this.audioRecorder.initialize();
                }

                // Inicializar transcripción si es necesario
                if (!this.voiceTranscription.recognition) {
                    this.voiceTranscription.initialize();
                }

                // Iniciar grabación y transcripción
                await this.audioRecorder.startRecording();
                await this.voiceTranscription.startTranscription();
                
                this.isListening = true;
                this.currentStatus = 'Escuchando...';
                
                console.log('Grabación y transcripción iniciadas');
            } catch (error) {
                console.error('Error iniciando escucha:', error);
                this.showError(error.message);
                this.isListening = false;
                this.currentStatus = '';
            }
        },

        /**
         * Detiene la escucha de voz
         */
        async stopListening() {
            try {
                if (!this.isListening) {
                    return;
                }

                this.isListening = false;
                this.currentStatus = 'Finalizando transcripción...';
                
                // Detener grabación (solo para efectos visuales)
                if (this.audioRecorder.isCurrentlyRecording()) {
                    await this.audioRecorder.stopRecording();
                }
                
                // Detener transcripción y esperar resultado final
                this.voiceTranscription.stopTranscription();
                
                // La transcripción final se manejará en handleTranscriptionResult
                
            } catch (error) {
                console.error('Error deteniendo escucha:', error);
                this.showError(error.message);
                this.isProcessing = false;
                this.currentStatus = '';
            }
        },

        /**
         * Procesa el mensaje de texto transcrito
         */
        async processTranscribedMessage(transcribedText) {
            try {
                this.isProcessing = true;
                this.currentStatus = 'Enviando a la IA...';
                
                // Validar que hay texto
                if (!transcribedText || transcribedText.trim().length < 2) {
                    throw new Error('Mensaje demasiado corto. Intenta hablar más tiempo.');
                }
                
                // Enviar al backend para procesamiento
                const result = await this.apiClient.processTextMessage(transcribedText);
                
                if (result.success) {
                    // Agregar conversación al historial
                    const conversation = {
                        id: Date.now(),
                        timestamp: new Date(),
                        userMessage: result.userMessage,
                        assistantMessage: result.assistantMessage,
                        audioData: result.audioResponse,
                        audioMimeType: result.audioMimeType || 'audio/mpeg'
                    };
                    
                    this.addConversation(conversation);
                    
                    // Reproducir respuesta automáticamente
                    await this.playAssistantAudio(result.audioResponse, result.audioMimeType);
                } else {
                    throw new Error('Error procesando mensaje');
                }
                
            } catch (error) {
                console.error('Error procesando texto transcrito:', error);
                this.showError(error.message);
            } finally {
                this.isProcessing = false;
                this.currentStatus = '';
            }
        },

        /**
         * Reproduce el audio de respuesta del asistente
         */
        async playAssistantAudio(audioBase64, mimeType = 'audio/mpeg') {
            try {
                if (this.isPlayingAudio) {
                    return;
                }

                this.isPlayingAudio = true;
                
                // Convertir base64 a blob
                const audioBlob = this.apiClient.base64ToAudioBlob(audioBase64, mimeType);
                
                // Crear URL de objeto
                const audioUrl = this.apiClient.createAudioUrl(audioBlob);
                
                // Limpiar URL anterior si existe
                if (this.currentAudioUrl) {
                    this.apiClient.revokeAudioUrl(this.currentAudioUrl);
                }
                
                this.currentAudioUrl = audioUrl;
                
                // Reproducir audio
                const audioElement = this.$refs.audioPlayer;
                if (!audioElement) {
                    throw new Error('Elemento de audio no encontrado');
                }
                
                audioElement.src = audioUrl;
                
                // Configurar eventos
                audioElement.onended = () => {
                    this.isPlayingAudio = false;
                    this.apiClient.revokeAudioUrl(audioUrl);
                    this.currentAudioUrl = null;
                };
                
                audioElement.onerror = () => {
                    this.isPlayingAudio = false;
                    this.showError('Error reproduciendo audio de respuesta');
                };
                
                await audioElement.play();
                
            } catch (error) {
                console.error('Error reproduciendo audio:', error);
                this.showError(error.message);
                this.isPlayingAudio = false;
            }
        },

        /**
         * Agrega una conversación al historial
         */
        addConversation(conversation) {
            this.conversations.push(conversation);
            
            // Limitar número de conversaciones
            if (this.conversations.length > this.maxConversations) {
                this.conversations = this.conversations.slice(-this.maxConversations);
            }
            
            // Guardar en localStorage
            this.saveConversationHistory();
            
            // Hacer scroll al final
            this.$nextTick(() => {
                this.scrollToBottom();
            });
        },

        /**
         * Limpia el historial de conversaciones
         */
        clearConversation() {
            if (confirm('¿Estás seguro de que quieres borrar toda la conversación?')) {
                this.conversations = [];
                this.saveConversationHistory();
            }
        },

        /**
         * Carga el historial de conversaciones desde localStorage
         */
        async loadConversationHistory() {
            try {
                const stored = localStorage.getItem('voice_assistant_conversations');
                if (stored) {
                    const parsed = JSON.parse(stored);
                    this.conversations = parsed.map(conv => ({
                        ...conv,
                        timestamp: new Date(conv.timestamp)
                    }));
                }
            } catch (error) {
                console.error('Error cargando historial:', error);
                this.conversations = [];
            }
        },

        /**
         * Guarda el historial de conversaciones en localStorage
         */
        saveConversationHistory() {
            try {
                localStorage.setItem('voice_assistant_conversations', JSON.stringify(this.conversations));
            } catch (error) {
                console.error('Error guardando historial:', error);
            }
        },

        /**
         * Maneja resultado de transcripción
         */
        handleTranscriptionResult(result) {
            console.log('Transcripción:', result);
            
            // Actualizar transcripción actual
            if (result.finalTranscript) {
                this.currentTranscription = result.finalTranscript;
                console.log('Transcripción final:', result.finalTranscript);
                
                // Si no está escuchando activamente, procesar inmediatamente
                if (!this.isListening && !this.isProcessing) {
                    this.processTranscribedMessage(result.finalTranscript);
                }
            } else if (result.interimTranscript && this.isListening) {
                // Mostrar transcripción provisional
                this.currentTranscription = result.interimTranscript;
            }
        },

        /**
         * Maneja errores de transcripción
         */
        handleTranscriptionError(error) {
            console.error('Error de transcripción:', error);
            this.isListening = false;
            this.currentStatus = '';
            this.showError(error.message);
        },

        /**
         * Maneja inicio de transcripción
         */
        handleTranscriptionStart() {
            console.log('Transcripción iniciada');
        },

        /**
         * Maneja fin de transcripción
         */
        handleTranscriptionEnd() {
            console.log('Transcripción terminada');
        },

        /**
         * Hace scroll al final del área de conversación
         */
        scrollToBottom() {
            this.$nextTick(() => {
                const conversationArea = this.$refs.conversationArea;
                if (conversationArea) {
                    conversationArea.scrollTop = conversationArea.scrollHeight;
                }
            });
        },

        /**
         * Formatea tiempo para mostrar en la interfaz
         */
        formatTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString('es-MX', {
                hour: '2-digit',
                minute: '2-digit'
            });
        },

        /**
         * Obtiene el estado de conexión para mostrar en la interfaz
         */
        getConnectionStatus() {
            if (this.isProcessing) {
                return 'Procesando...';
            }
            if (this.isListening) {
                return 'Escuchando';
            }
            return 'En línea';
        },

        /**
         * Muestra error en consola y opcionalmente en UI
         */
        showError(message) {
            console.error('Error del asistente de voz:', message);
            
            // Mostrar alert solo si es error de permisos o critico
            if (message.includes('micrófono') || message.includes('permiso')) {
                alert('Error: ' + message);
            }
        },

        /**
         * Verifica soporte del navegador
         */
        checkBrowserSupport() {
            const recorderSupport = AudioRecorderService.checkBrowserSupport();
            const transcriptionSupport = VoiceTranscriptionService.checkBrowserSupport();
            
            if (!recorderSupport.isSupported) {
                throw new Error('Tu navegador no soporta grabación de audio');
            }
            
            if (!transcriptionSupport.isSupported) {
                console.warn('Reconocimiento de voz no soportado, usando solo grabación');
            }
        },

        /**
         * Verifica conexión con la API
         */
        async checkApiConnection() {
            try {
                const isHealthy = await this.apiClient.checkApiHealth();
                if (!isHealthy) {
                    console.warn('API del asistente de voz no disponible');
                }
            } catch (error) {
                console.error('Error verificando API:', error);
            }
        },

        /**
         * Configura limpieza automática
         */
        setupAutoCleanup() {
            if (this.cleanupTimer) {
                clearInterval(this.cleanupTimer);
            }
            
            this.cleanupTimer = setInterval(async () => {
                try {
                    await this.apiClient.cleanupTempAudio();
                } catch (error) {
                    console.error('Error en limpieza automática:', error);
                }
            }, this.autoCleanupInterval);
        },

        /**
         * Limpia recursos al destruir el componente
         */
        destroy() {
            try {
                // Limpiar servicios
                if (this.audioRecorder) {
                    this.audioRecorder.cleanup();
                }
                
                if (this.voiceTranscription) {
                    this.voiceTranscription.cleanup();
                }
                
                // Limpiar timers
                if (this.cleanupTimer) {
                    clearInterval(this.cleanupTimer);
                }
                
                // Limpiar URLs de audio
                if (this.currentAudioUrl) {
                    this.apiClient.revokeAudioUrl(this.currentAudioUrl);
                }
                
                console.log('Asistente de voz limpio correctamente');
            } catch (error) {
                console.error('Error limpiando asistente de voz:', error);
            }
        },

        /**
         * Función auxiliar para delays
         */
        delay(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }
    };
}

// Exportar función para uso global
window.voiceAssistantWidget = voiceAssistantWidget;