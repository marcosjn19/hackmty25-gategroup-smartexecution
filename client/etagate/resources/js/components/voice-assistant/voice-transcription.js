/**
 * Servicio de transcripción de voz usando Web Speech API
 * Maneja la conversión de audio a texto en tiempo real
 */
class VoiceTranscriptionService {
    constructor() {
        this.recognition = null;
        this.isListening = false;
        this.onResult = null;
        this.onError = null;
        this.onStart = null;
        this.onEnd = null;
        this.language = 'es-MX'; // Español mexicano por defecto
        this.continuous = false;
        this.interimResults = true;
        this.maxAlternatives = 1;
    }

    /**
     * Inicializa el servicio de reconocimiento de voz
     */
    initialize() {
        try {
            // Verificar soporte del navegador
            if (!this.isSupported()) {
                throw new Error('Tu navegador no soporta reconocimiento de voz');
            }

            // Crear instancia de SpeechRecognition
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            this.recognition = new SpeechRecognition();

            // Configurar opciones
            this.recognition.continuous = this.continuous;
            this.recognition.interimResults = this.interimResults;
            this.recognition.lang = this.language;
            this.recognition.maxAlternatives = this.maxAlternatives;

            // Configurar eventos
            this.setupRecognitionEvents();

            return true;
        } catch (error) {
            console.error('Error inicializando transcripción de voz:', error);
            throw error;
        }
    }

    /**
     * Configura los eventos del reconocimiento de voz
     */
    setupRecognitionEvents() {
        // Cuando inicia el reconocimiento
        this.recognition.onstart = () => {
            this.isListening = true;
            console.log('Reconocimiento de voz iniciado');
            if (this.onStart) this.onStart();
        };

        // Cuando termina el reconocimiento
        this.recognition.onend = () => {
            this.isListening = false;
            console.log('Reconocimiento de voz terminado');
            if (this.onEnd) this.onEnd();
        };

        // Cuando hay un resultado
        this.recognition.onresult = (event) => {
            let finalTranscript = '';
            let interimTranscript = '';

            for (let i = event.resultIndex; i < event.results.length; i++) {
                const transcript = event.results[i][0].transcript;
                const confidence = event.results[i][0].confidence;

                if (event.results[i].isFinal) {
                    finalTranscript += transcript;
                } else {
                    interimTranscript += transcript;
                }
            }

            if (this.onResult) {
                this.onResult({
                    finalTranscript: finalTranscript.trim(),
                    interimTranscript: interimTranscript.trim(),
                    confidence: event.results[event.results.length - 1][0].confidence
                });
            }
        };

        // Cuando hay un error
        this.recognition.onerror = (event) => {
            const error = this.handleRecognitionError(event.error);
            console.error('Error en reconocimiento de voz:', error);
            
            this.isListening = false;
            
            if (this.onError) {
                this.onError(error);
            }
        };

        // Cuando no se detecta habla
        this.recognition.onnomatch = () => {
            console.warn('No se detectó habla clara');
            if (this.onError) {
                this.onError(new Error('No se pudo entender lo que dijiste. Intenta hablar más claro.'));
            }
        };

        // Cuando no hay sonido
        this.recognition.onsoundstart = () => {
            console.log('Sonido detectado');
        };

        this.recognition.onsoundend = () => {
            console.log('Sonido terminado');
        };

        this.recognition.onspeechstart = () => {
            console.log('Habla detectada');
        };

        this.recognition.onspeechend = () => {
            console.log('Habla terminada');
        };
    }

    /**
     * Inicia la transcripción de voz
     */
    async startTranscription() {
        try {
            if (!this.recognition) {
                this.initialize();
            }

            if (this.isListening) {
                console.warn('El reconocimiento de voz ya está activo');
                return false;
            }

            this.recognition.start();
            return true;
        } catch (error) {
            console.error('Error iniciando transcripción:', error);
            throw error;
        }
    }

    /**
     * Detiene la transcripción de voz
     */
    stopTranscription() {
        try {
            if (this.recognition && this.isListening) {
                this.recognition.stop();
            }
        } catch (error) {
            console.error('Error deteniendo transcripción:', error);
        }
    }

    /**
     * Cancela la transcripción de voz
     */
    cancelTranscription() {
        try {
            if (this.recognition && this.isListening) {
                this.recognition.abort();
                this.isListening = false;
            }
        } catch (error) {
            console.error('Error cancelando transcripción:', error);
        }
    }

    /**
     * Configura los callbacks de eventos
     */
    setCallbacks({ onResult, onError, onStart, onEnd }) {
        this.onResult = onResult;
        this.onError = onError;
        this.onStart = onStart;
        this.onEnd = onEnd;
    }

    /**
     * Configura el idioma de reconocimiento
     */
    setLanguage(language) {
        this.language = language;
        if (this.recognition) {
            this.recognition.lang = language;
        }
    }

    /**
     * Configura si debe ser continuo
     */
    setContinuous(continuous) {
        this.continuous = continuous;
        if (this.recognition) {
            this.recognition.continuous = continuous;
        }
    }

    /**
     * Configura si debe mostrar resultados intermedios
     */
    setInterimResults(interimResults) {
        this.interimResults = interimResults;
        if (this.recognition) {
            this.recognition.interimResults = interimResults;
        }
    }

    /**
     * Verifica si está escuchando actualmente
     */
    isCurrentlyListening() {
        return this.isListening;
    }

    /**
     * Verifica soporte del navegador
     */
    isSupported() {
        return !!(window.SpeechRecognition || window.webkitSpeechRecognition);
    }

    /**
     * Obtiene idiomas soportados comunes
     */
    getSupportedLanguages() {
        return {
            'es-MX': 'Español (México)',
            'es-ES': 'Español (España)',
            'es-AR': 'Español (Argentina)',
            'es-CO': 'Español (Colombia)',
            'es-CL': 'Español (Chile)',
            'es-PE': 'Español (Perú)',
            'en-US': 'English (United States)',
            'en-GB': 'English (United Kingdom)',
            'fr-FR': 'Français (France)',
            'de-DE': 'Deutsch (Deutschland)',
            'it-IT': 'Italiano (Italia)',
            'pt-BR': 'Português (Brasil)',
            'zh-CN': '中文 (普通话)',
            'ja-JP': '日本語 (日本)',
            'ko-KR': '한국어 (대한민국)'
        };
    }

    /**
     * Maneja errores del reconocimiento de voz
     */
    handleRecognitionError(errorType) {
        const errorMessages = {
            'no-speech': 'No se detectó habla. Intenta hablar más fuerte o acércate al micrófono.',
            'aborted': 'Reconocimiento cancelado.',
            'audio-capture': 'Error capturando audio. Verifica que tu micrófono esté funcionando.',
            'network': 'Error de red. Verifica tu conexión a internet.',
            'not-allowed': 'Permiso de micrófono denegado. Por favor, permite el acceso al micrófono.',
            'service-not-allowed': 'Servicio de reconocimiento no permitido.',
            'bad-grammar': 'Error en la gramática de reconocimiento.',
            'language-not-supported': 'Idioma no soportado.'
        };

        const friendlyMessage = errorMessages[errorType] || `Error desconocido en reconocimiento de voz: ${errorType}`;
        return new Error(friendlyMessage);
    }

    /**
     * Limpia recursos
     */
    cleanup() {
        try {
            if (this.recognition) {
                if (this.isListening) {
                    this.recognition.abort();
                }
                this.recognition = null;
            }
            
            this.isListening = false;
            this.onResult = null;
            this.onError = null;
            this.onStart = null;
            this.onEnd = null;
        } catch (error) {
            console.error('Error limpiando recursos de transcripción:', error);
        }
    }

    /**
     * Configuración optimizada para español mexicano
     */
    optimizeForSpanish() {
        this.setLanguage('es-MX');
        this.setContinuous(false);
        this.setInterimResults(true);
        
        // Ajustes específicos para mejor reconocimiento en español
        if (this.recognition) {
            this.recognition.maxAlternatives = 3; // Más alternativas para mejor precisión
        }
    }

    /**
     * Verifica compatibilidad del navegador y características
     */
    static checkBrowserSupport() {
        const support = {
            speechRecognition: !!(window.SpeechRecognition || window.webkitSpeechRecognition),
            continuous: true, // Asumimos que está soportado si SpeechRecognition existe
            interimResults: true,
            maxAlternatives: true
        };

        const isSupported = support.speechRecognition;
        
        return {
            isSupported,
            support,
            recommendedBrowsers: ['Chrome', 'Edge', 'Safari', 'Opera'],
            isWebKit: !!window.webkitSpeechRecognition
        };
    }
}

// Exportar para uso en otros módulos
window.VoiceTranscriptionService = VoiceTranscriptionService;