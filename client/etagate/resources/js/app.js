import './bootstrap';
import Alpine from 'alpinejs';

// Importar servicios del asistente de voz
import './components/voice-assistant/audio-recorder.js';
import './components/voice-assistant/voice-transcription.js';
import './components/voice-assistant/api-client.js';
import './components/voice-assistant/voice-assistant-widget.js';

// Asegurar que Alpine esté disponible globalmente
window.Alpine = Alpine;

// Esperar a que todos los scripts se carguen antes de registrar el componente
document.addEventListener('DOMContentLoaded', () => {
    // Verificar que el componente esté disponible
    if (typeof window.voiceAssistantWidget === 'function') {
        // Registrar el componente antes de iniciar Alpine
        Alpine.data('voiceAssistantWidget', window.voiceAssistantWidget);
        console.log('Componente voiceAssistantWidget registrado correctamente');
    } else {
        console.error('Error: voiceAssistantWidget no esta definido');
        
        // Registrar un componente vacío para evitar errores de Alpine
        Alpine.data('voiceAssistantWidget', () => ({
            isOpen: false,
            isListening: false,
            isProcessing: false,
            conversations: [],
            currentStatus: '',
            init() {
                console.error('Voice Assistant Widget no pudo inicializarse correctamente');
            },
            getConnectionStatus() {
                return 'Error';
            }
        }));
    }
    
    // Iniciar Alpine
    Alpine.start();
    console.log('Alpine.js iniciado');
});

// Log para debugging
console.log('app.js cargado');