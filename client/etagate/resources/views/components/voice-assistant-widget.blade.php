{{-- Asistente de Voz Widget - Componente modular con Alpine.js --}}
<div x-data="voiceAssistantWidget()" 
     x-init="initializeVoiceAssistant()"
     class="fixed bottom-6 right-6 z-50"
     x-cloak>
    
    {{-- Botón flotante del asistente --}}
    <div class="relative">
        {{-- Botón principal --}}
        <button @click="toggleAssistant()"
                :class="{ 
                    'bg-gradient-to-r from-etagate-orange to-orange-600 shadow-2xl scale-110': isOpen,
                    'bg-gradient-to-r from-etagate-orange to-orange-600 hover:shadow-xl hover:scale-105': !isOpen,
                    'animate-pulse': isListening || isProcessing
                }"
                class="w-16 h-16 rounded-full text-white transition-all duration-300 transform flex items-center justify-center group relative overflow-hidden">
            
            {{-- Icono del micrófono --}}
            <svg x-show="!isListening && !isProcessing" 
                 class="w-8 h-8" 
                 fill="none" 
                 stroke="currentColor" 
                 viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
            </svg>
            
            {{-- Icono de grabación activa --}}
            <svg x-show="isListening" 
                 class="w-8 h-8 text-red-300 animate-pulse" 
                 fill="currentColor" 
                 viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="6"/>
            </svg>
            
            {{-- Icono de procesamiento --}}
            <svg x-show="isProcessing" 
                 class="w-8 h-8 animate-spin" 
                 fill="none" 
                 stroke="currentColor" 
                 viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            
            {{-- Ondas de sonido animadas --}}
            <div x-show="isListening" class="absolute inset-0 flex items-center justify-center">
                <div class="absolute w-16 h-16 rounded-full border-2 border-white opacity-30 animate-ping"></div>
                <div class="absolute w-20 h-20 rounded-full border-2 border-white opacity-20 animate-ping animation-delay-200"></div>
            </div>
        </button>
        
        {{-- Indicador de estado --}}
        <div x-show="isListening || isProcessing" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-75"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-75"
             class="absolute -top-12 right-0 bg-gray-900 text-white px-3 py-1 rounded-lg text-sm whitespace-nowrap">
            <span x-text="currentStatus"></span>
            <div class="absolute top-full right-4 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"></div>
        </div>
    </div>
    
    {{-- Panel de conversación expandible --}}
    <div x-show="isOpen" 
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="opacity-0 translate-y-4 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 scale-95"
         class="absolute bottom-20 right-0 w-96 max-w-[calc(100vw-3rem)] bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
        
        {{-- Header del panel --}}
        <div class="bg-gradient-to-r from-etagate-orange to-orange-600 text-white p-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-lg">EtaGate Assistant</h3>
                    <p class="text-sm opacity-90" x-text="getConnectionStatus()"></p>
                </div>
            </div>
            
            <button @click="closeAssistant()" 
                    class="w-8 h-8 rounded-lg hover:bg-white hover:bg-opacity-20 flex items-center justify-center transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        
        {{-- Área de conversación --}}
        <div class="h-80 overflow-y-auto p-4 space-y-4 bg-gray-50" x-ref="conversationArea">
            {{-- Mensaje de bienvenida --}}
            <div x-show="conversations.length === 0" class="text-center py-8">
                <div class="w-16 h-16 bg-gradient-to-r from-etagate-orange to-orange-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                    </svg>
                </div>
                <h4 class="font-semibold text-gray-900 mb-2">¡Hola! Soy tu asistente EtaGate</h4>
                <p class="text-gray-600 text-sm">Haz clic en el micrófono para hablar conmigo</p>
            </div>
            
            {{-- Mensajes de conversación --}}
            <template x-for="(conversation, index) in conversations" :key="index">
                <div class="space-y-3">
                    {{-- Mensaje del usuario --}}
                    <div class="flex justify-end">
                        <div class="max-w-xs lg:max-w-sm bg-gradient-to-r from-etagate-orange to-orange-600 text-white rounded-2xl rounded-br-md px-4 py-2">
                            <p class="text-sm" x-text="conversation.userMessage"></p>
                            <div class="text-xs opacity-75 mt-1" x-text="formatTime(conversation.timestamp)"></div>
                        </div>
                    </div>
                    
                    {{-- Mensaje del asistente --}}
                    <div class="flex justify-start">
                        <div class="max-w-xs lg:max-w-sm bg-white border border-gray-200 rounded-2xl rounded-bl-md px-4 py-2 shadow-sm">
                            <div class="flex items-start space-x-2">
                                <div class="w-6 h-6 bg-gradient-to-r from-etagate-orange to-orange-600 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm text-gray-900" x-text="conversation.assistantMessage"></p>
                                    <div class="flex items-center justify-between mt-2">
                                        <div class="text-xs text-gray-500" x-text="formatTime(conversation.timestamp)"></div>
                                        <button @click="playAssistantAudio(conversation.audioData, conversation.audioMimeType)"
                                                :disabled="isPlayingAudio"
                                                :class="{ 'opacity-50': isPlayingAudio }"
                                                class="text-etagate-orange hover:text-orange-600 transition-colors">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M8 5v14l11-7z"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            
            {{-- Indicador de escribiendo --}}
            <div x-show="isProcessing" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="flex justify-start">
                <div class="bg-white border border-gray-200 rounded-2xl rounded-bl-md px-4 py-3 shadow-sm">
                    <div class="flex items-center space-x-2">
                        <div class="w-6 h-6 bg-gradient-to-r from-etagate-orange to-orange-600 rounded-full flex items-center justify-center">
                            <svg class="w-3 h-3 text-white animate-pulse" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                        </div>
                        <div class="flex space-x-1">
                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce animation-delay-100"></div>
                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce animation-delay-200"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        {{-- Panel de control inferior --}}
        <div class="border-t border-gray-200 p-4 bg-white">
            {{-- Controles de voz --}}
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    {{-- CAMBIO PRINCIPAL: @click en lugar de @mousedown/@mouseup --}}
                    <button @click="toggleListening()"
                            :disabled="isProcessing"
                            :class="{ 
                                'bg-red-500 shadow-lg scale-105': isListening,
                                'bg-gradient-to-r from-etagate-orange to-orange-600 hover:shadow-md hover:scale-105': !isListening && !isProcessing,
                                'opacity-50 cursor-not-allowed': isProcessing
                            }"
                            class="w-12 h-12 rounded-full text-white transition-all duration-200 transform flex items-center justify-center">
                        <svg x-show="!isListening" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                        </svg>
                        <svg x-show="isListening" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <rect x="6" y="6" width="12" height="12" rx="2"/>
                        </svg>
                    </button>
                    
                    {{-- TEXTO ACTUALIZADO --}}
                    <div class="text-sm text-gray-600">
                        <span x-show="!isListening && !isProcessing">Haz clic para hablar</span>
                        <span x-show="isListening" class="text-red-600 font-medium">Escuchando... (clic para detener)</span>
                        <span x-show="isProcessing" class="text-orange-600 font-medium">Procesando...</span>
                    </div>
                </div>
                
                {{-- Botón de limpiar conversación --}}
                <button @click="clearConversation()"
                        x-show="conversations.length > 0"
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1-1H8a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </div>
            
            {{-- Visualizador de niveles de audio --}}
            <div x-show="isListening" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 h-0"
                 x-transition:enter-end="opacity-100 h-auto"
                 class="mt-3 flex items-center justify-center space-x-1">
                <template x-for="i in 20" :key="i">
                    <div :style="{ height: Math.random() * 20 + 5 + 'px' }"
                         class="w-1 bg-gradient-to-t from-etagate-orange to-orange-400 rounded-full animate-pulse"></div>
                </template>
            </div>
        </div>
    </div>
    
    {{-- Audio element para reproducir respuestas --}}
    <audio x-ref="audioPlayer" preload="none" style="display: none;"></audio>
</div>

{{-- Estilos CSS adicionales para animaciones --}}
<style>
    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-10px); }
    }
    
    .animation-delay-100 { animation-delay: 0.1s; }
    .animation-delay-200 { animation-delay: 0.2s; }
    .animation-delay-300 { animation-delay: 0.3s; }
    
    [x-cloak] { display: none !important; }
</style>