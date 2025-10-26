import './bootstrap';
import modelCard from './model.js';
import './processes-index.js';
import Alpine from 'alpinejs';

// Voice assistant (como ya lo tenías)
import './components/voice-assistant/audio-recorder.js';
import './components/voice-assistant/voice-transcription.js';
import './components/voice-assistant/api-client.js';
import './components/voice-assistant/voice-assistant-widget.js';

window.Alpine = Alpine;

// Registrar helpers de Alpine **antes** de iniciar
// (disponible como x-data="modelCard({...})" en Blade)
window.modelCard = modelCard;

document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.voiceAssistantWidget === 'function') {
        Alpine.data('voiceAssistantWidget', window.voiceAssistantWidget);
    } else {
        Alpine.data('voiceAssistantWidget', () => ({
            isOpen: false,
            isListening: false,
            isProcessing: false,
            conversations: [],
            currentStatus: '',
            init() { },
            getConnectionStatus() { return 'Error'; }
        }));
    }

    Alpine.start();
    console.log('Alpine.js iniciado');
});

console.log('app.js cargado');
