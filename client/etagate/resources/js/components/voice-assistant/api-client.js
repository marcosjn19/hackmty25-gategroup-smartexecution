/**
 * Cliente API para comunicación con el backend del asistente de voz
 * Maneja las peticiones a los endpoints de Laravel
 */
class VoiceAssistantApiClient {
    constructor() {
        this.baseUrl = window.location.origin;
        this.endpoints = {
            processText: '/api/voice-assistant/process-text',
            processVoice: '/api/voice-assistant/process', // Legacy
            getHistory: '/api/voice-assistant/history',
            cleanup: '/api/voice-assistant/cleanup'
        };
        
        // Token CSRF para Laravel
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        // Configuración por defecto
        this.defaultTimeout = 30000; // 30 segundos
        this.maxRetries = 3;
    }

    /**
     * Procesa un mensaje de texto transcrito
     * Envía texto, recibe respuesta de IA y audio de respuesta
     */
    async processTextMessage(transcribedText, options = {}) {
        try {
            const requestData = {
                message: transcribedText.trim()
            };
            
            const response = await this.makeRequest('POST', this.endpoints.processVoice, {
                body: requestData,
                timeout: options.timeout || this.defaultTimeout,
                retries: options.retries || this.maxRetries
            });

            if (!response.success) {
                throw new Error(response.error || 'Error procesando mensaje de texto');
            }

            return {
                success: true,
                userMessage: response.data.user_message,
                assistantMessage: response.data.assistant_message,
                audioResponse: response.data.audio_response,
                audioMimeType: response.data.audio_mime_type
            };
        } catch (error) {
            console.error('Error en processTextMessage:', error);
            throw this.handleApiError(error);
        }
    }

    /**
     * Obtiene el historial de conversaciones
     */
    async getConversationHistory(options = {}) {
        try {
            const response = await this.makeRequest('GET', this.endpoints.getHistory, {
                timeout: options.timeout || 10000
            });

            if (!response.success) {
                throw new Error(response.error || 'Error obteniendo historial');
            }

            return {
                success: true,
                conversations: response.data.conversations
            };
        } catch (error) {
            console.error('Error en getConversationHistory:', error);
            throw this.handleApiError(error);
        }
    }

    /**
     * Limpia archivos temporales de audio
     */
    async cleanupTempAudio() {
        try {
            const response = await this.makeRequest('POST', this.endpoints.cleanup, {
                timeout: 5000
            });

            return {
                success: response.success,
                deletedFiles: response.data?.deleted_files || 0
            };
        } catch (error) {
            console.error('Error en cleanupTempAudio:', error);
            // No lanzar error aquí ya que es una operación opcional
            return { success: false, deletedFiles: 0 };
        }
    }

    /**
     * Prepara FormData con el audio para envío
     */
    prepareAudioFormData(audioBlob) {
        const formData = new FormData();
        
        // Generar nombre único para el archivo
        const timestamp = Date.now();
        const filename = `voice_message_${timestamp}.webm`;
        
        formData.append('audio', audioBlob, filename);
        
        // Agregar metadatos
        formData.append('timestamp', timestamp.toString());
        formData.append('source', 'voice_assistant_widget');
        
        return formData;
    }

    /**
     * Realiza petición HTTP con reintentos y manejo de errores
     */
    async makeRequest(method, endpoint, options = {}) {
        const {
            body = null,
            timeout = 10000,
            retries = 1,
            headers = {}
        } = options;

        const url = `${this.baseUrl}${endpoint}`;
        
        // Configurar headers por defecto
        const requestHeaders = {
            'X-Requested-With': 'XMLHttpRequest',
            ...headers
        };

        // Agregar CSRF token para métodos que lo requieren
        if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase()) && this.csrfToken) {
            requestHeaders['X-CSRF-TOKEN'] = this.csrfToken;
        }

        // Si no es FormData, agregar Content-Type JSON
        if (body && !(body instanceof FormData)) {
            requestHeaders['Content-Type'] = 'application/json';
        }

        // Función para hacer la petición
        const doRequest = async () => {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), timeout);

            try {
                const response = await fetch(url, {
                    method: method.toUpperCase(),
                    headers: requestHeaders,
                    body: body instanceof FormData ? body : (body ? JSON.stringify(body) : null),
                    signal: controller.signal,
                    credentials: 'same-origin'
                });

                clearTimeout(timeoutId);

                // Verificar si la respuesta es exitosa
                if (!response.ok) {
                    const errorText = await response.text();
                    let errorData;
                    
                    try {
                        errorData = JSON.parse(errorText);
                    } catch {
                        errorData = { error: errorText || `HTTP Error ${response.status}` };
                    }
                    
                    throw new Error(errorData.error || `Error ${response.status}: ${response.statusText}`);
                }

                // Parsear respuesta JSON
                const data = await response.json();
                return data;
            } catch (error) {
                clearTimeout(timeoutId);
                
                if (error.name === 'AbortError') {
                    throw new Error('Tiempo de espera agotado');
                }
                
                throw error;
            }
        };

        // Implementar reintentos
        let lastError;
        for (let attempt = 0; attempt <= retries; attempt++) {
            try {
                return await doRequest();
            } catch (error) {
                lastError = error;
                
                // No reintentar en errores de cliente (4xx) excepto timeout
                if (error.message.includes('HTTP Error 4') && !error.message.includes('408')) {
                    throw error;
                }
                
                // Si no es el último intento, esperar antes de reintentar
                if (attempt < retries) {
                    await this.delay(Math.pow(2, attempt) * 1000); // Backoff exponencial
                    console.warn(`Reintentando petición (${attempt + 1}/${retries + 1})...`);
                }
            }
        }

        throw lastError;
    }

    /**
     * Convierte audio base64 a Blob para reproducción
     */
    base64ToAudioBlob(base64Data, mimeType = 'audio/mpeg') {
        try {
            const byteCharacters = atob(base64Data);
            const byteNumbers = new Array(byteCharacters.length);
            
            for (let i = 0; i < byteCharacters.length; i++) {
                byteNumbers[i] = byteCharacters.charCodeAt(i);
            }
            
            const byteArray = new Uint8Array(byteNumbers);
            return new Blob([byteArray], { type: mimeType });
        } catch (error) {
            console.error('Error convirtiendo base64 a blob:', error);
            throw new Error('Error procesando audio de respuesta');
        }
    }

    /**
     * Crea URL de objeto para reproducción de audio
     */
    createAudioUrl(audioBlob) {
        try {
            return URL.createObjectURL(audioBlob);
        } catch (error) {
            console.error('Error creando URL de audio:', error);
            throw new Error('Error preparando audio para reproducción');
        }
    }

    /**
     * Limpia URL de objeto para liberar memoria
     */
    revokeAudioUrl(audioUrl) {
        try {
            URL.revokeObjectURL(audioUrl);
        } catch (error) {
            console.error('Error liberando URL de audio:', error);
        }
    }

    /**
     * Maneja errores de la API y los convierte en mensajes amigables
     */
    handleApiError(error) {
        if (error.message.includes('Failed to fetch')) {
            return new Error('Error de conexión. Verifica tu conexión a internet.');
        }
        
        if (error.message.includes('Tiempo de espera agotado')) {
            return new Error('La petición tardó demasiado. Intenta de nuevo.');
        }
        
        if (error.message.includes('413')) {
            return new Error('El archivo de audio es demasiado grande.');
        }
        
        if (error.message.includes('422')) {
            return new Error('Formato de audio no válido.');
        }
        
        if (error.message.includes('429')) {
            return new Error('Demasiadas peticiones. Espera un momento antes de intentar de nuevo.');
        }
        
        if (error.message.includes('500')) {
            return new Error('Error interno del servidor. Intenta de nuevo más tarde.');
        }
        
        return error;
    }

    /**
     * Verifica el estado de la conexión con la API
     */
    async checkApiHealth() {
        try {
            const response = await this.makeRequest('GET', '/api/health', {
                timeout: 5000
            });
            
            return response.success === true;
        } catch (error) {
            console.error('Error verificando estado de la API:', error);
            return false;
        }
    }

    /**
     * Obtiene información de configuración del cliente
     */
    getClientInfo() {
        return {
            baseUrl: this.baseUrl,
            hasCSRFToken: !!this.csrfToken,
            defaultTimeout: this.defaultTimeout,
            maxRetries: this.maxRetries,
            userAgent: navigator.userAgent,
            timestamp: new Date().toISOString()
        };
    }

    /**
     * Función auxiliar para delays
     */
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Actualiza el token CSRF (útil si se regenera dinámicamente)
     */
    updateCSRFToken(newToken) {
        this.csrfToken = newToken;
    }

    /**
     * Configura timeouts personalizados
     */
    setTimeouts(defaultTimeout, maxRetries = null) {
        this.defaultTimeout = defaultTimeout;
        if (maxRetries !== null) {
            this.maxRetries = maxRetries;
        }
    }
}

// Exportar para uso en otros módulos
window.VoiceAssistantApiClient = VoiceAssistantApiClient;