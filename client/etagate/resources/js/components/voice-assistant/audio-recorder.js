/**
 * Servicio de grabación de audio usando MediaRecorder API
 * Maneja la captura de audio del micrófono del usuario
 */
class AudioRecorderService {
    constructor() {
        this.mediaRecorder = null;
        this.audioChunks = [];
        this.stream = null;
        this.isRecording = false;
        this.analyser = null;
        this.audioContext = null;
        this.dataArray = null;
    }

    /**
     * Solicita permisos y configura el grabador de audio
     */
    async initialize() {
        try {
            // Verificar soporte del navegador
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error('Tu navegador no soporta grabación de audio');
            }

            // Solicitar acceso al micrófono
            this.stream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true,
                    sampleRate: 44100,
                    channelCount: 1
                }
            });

            // Configurar contexto de audio para visualización
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            this.analyser = this.audioContext.createAnalyser();
            const source = this.audioContext.createMediaStreamSource(this.stream);
            source.connect(this.analyser);
            
            this.analyser.fftSize = 256;
            const bufferLength = this.analyser.frequencyBinCount;
            this.dataArray = new Uint8Array(bufferLength);

            // Configurar MediaRecorder
            const options = {
                mimeType: this.getSupportedMimeType(),
                audioBitsPerSecond: 128000
            };

            this.mediaRecorder = new MediaRecorder(this.stream, options);
            this.setupRecorderEvents();

            return true;
        } catch (error) {
            console.error('Error inicializando grabador de audio:', error);
            throw this.handleRecorderError(error);
        }
    }

    /**
     * Obtiene el tipo MIME soportado por el navegador
     */
    getSupportedMimeType() {
        const types = [
            'audio/webm;codecs=opus',
            'audio/webm',
            'audio/mp4',
            'audio/mpeg',
            'audio/wav'
        ];

        for (const type of types) {
            if (MediaRecorder.isTypeSupported(type)) {
                return type;
            }
        }

        return ''; // Usar tipo por defecto
    }

    /**
     * Configura los eventos del MediaRecorder
     */
    setupRecorderEvents() {
        this.mediaRecorder.ondataavailable = (event) => {
            if (event.data.size > 0) {
                this.audioChunks.push(event.data);
            }
        };

        this.mediaRecorder.onstop = () => {
            console.log('Grabación detenida');
        };

        this.mediaRecorder.onerror = (event) => {
            console.error('Error en MediaRecorder:', event.error);
        };
    }

    /**
     * Inicia la grabación de audio
     */
    async startRecording() {
        try {
            if (!this.mediaRecorder) {
                await this.initialize();
            }

            if (this.mediaRecorder.state === 'recording') {
                console.warn('Ya se está grabando');
                return false;
            }

            this.audioChunks = [];
            this.mediaRecorder.start(100); // Recopilar datos cada 100ms
            this.isRecording = true;

            return true;
        } catch (error) {
            console.error('Error iniciando grabación:', error);
            throw error;
        }
    }

    /**
     * Detiene la grabación y retorna el audio grabado
     */
    async stopRecording() {
        return new Promise((resolve, reject) => {
            if (!this.mediaRecorder || this.mediaRecorder.state === 'inactive') {
                reject(new Error('No hay grabación activa'));
                return;
            }

            this.mediaRecorder.onstop = () => {
                try {
                    const audioBlob = new Blob(this.audioChunks, { 
                        type: this.getSupportedMimeType() || 'audio/webm' 
                    });
                    
                    this.isRecording = false;
                    this.audioChunks = [];
                    
                    resolve(audioBlob);
                } catch (error) {
                    reject(error);
                }
            };

            this.mediaRecorder.stop();
        });
    }

    /**
     * Obtiene los niveles de audio para visualización
     */
    getAudioLevels() {
        if (!this.analyser || !this.dataArray) {
            return Array(20).fill(0);
        }

        this.analyser.getByteFrequencyData(this.dataArray);
        
        // Convertir a porcentajes para visualización
        const levels = [];
        const step = Math.floor(this.dataArray.length / 20);
        
        for (let i = 0; i < 20; i++) {
            const index = i * step;
            const level = this.dataArray[index] / 255;
            levels.push(Math.floor(level * 100));
        }
        
        return levels;
    }

    /**
     * Convierte Blob de audio a FormData para envío
     */
    blobToFormData(audioBlob, fieldName = 'audio') {
        const formData = new FormData();
        const timestamp = Date.now();
        const filename = `audio_${timestamp}.webm`;
        
        formData.append(fieldName, audioBlob, filename);
        return formData;
    }

    /**
     * Verifica si el grabador está listo
     */
    isReady() {
        return this.mediaRecorder !== null && this.stream !== null;
    }

    /**
     * Verifica si está grabando actualmente
     */
    isCurrentlyRecording() {
        return this.isRecording && this.mediaRecorder && this.mediaRecorder.state === 'recording';
    }

    /**
     * Limpia recursos y cierra streams
     */
    cleanup() {
        try {
            if (this.mediaRecorder && this.mediaRecorder.state === 'recording') {
                this.mediaRecorder.stop();
            }

            if (this.stream) {
                this.stream.getTracks().forEach(track => track.stop());
                this.stream = null;
            }

            if (this.audioContext && this.audioContext.state !== 'closed') {
                this.audioContext.close();
                this.audioContext = null;
            }

            this.mediaRecorder = null;
            this.analyser = null;
            this.audioChunks = [];
            this.isRecording = false;
        } catch (error) {
            console.error('Error limpiando recursos de audio:', error);
        }
    }

    /**
     * Maneja errores comunes del grabador
     */
    handleRecorderError(error) {
        const errorMessages = {
            'NotAllowedError': 'Permiso de micrófono denegado. Por favor, permite el acceso al micrófono.',
            'NotFoundError': 'No se encontró micrófono. Verifica que tengas un micrófono conectado.',
            'NotReadableError': 'Error accediendo al micrófono. Puede estar siendo usado por otra aplicación.',
            'OverconstrainedError': 'El micrófono no cumple con los requisitos técnicos.',
            'SecurityError': 'Error de seguridad. Asegúrate de estar usando HTTPS.',
            'AbortError': 'Operación cancelada por el usuario.',
            'NetworkError': 'Error de red al acceder al micrófono.',
            'TypeError': 'Error de configuración del grabador de audio.'
        };

        const friendlyMessage = errorMessages[error.name] || `Error desconocido: ${error.message}`;
        return new Error(friendlyMessage);
    }

    /**
     * Verifica compatibilidad del navegador
     */
    static checkBrowserSupport() {
        const support = {
            mediaDevices: !!navigator.mediaDevices,
            getUserMedia: !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia),
            mediaRecorder: !!window.MediaRecorder,
            audioContext: !!(window.AudioContext || window.webkitAudioContext),
            blob: !!window.Blob,
            formData: !!window.FormData
        };

        const isSupported = Object.values(support).every(Boolean);
        
        return {
            isSupported,
            support,
            missingFeatures: Object.keys(support).filter(key => !support[key])
        };
    }
}

// Exportar para uso en otros módulos
window.AudioRecorderService = AudioRecorderService;