(() => {
    'use strict';
    const form = document.getElementById('chatbotForm');
    const messages = document.getElementById('chatbotMessages');
    const input = document.getElementById('chatbotInput');
    const send = document.getElementById('chatbotSend');
    const microphone = document.getElementById('chatbotMic');
    const voiceLanguage = document.getElementById('chatbotVoiceLanguage');
    const voiceStatus = document.getElementById('chatbotVoiceStatus');
    let history = [];
    let recognition = null;
    let listening = false;
    if (!form || !messages || !input || !send) return;
    const scroll = () => { messages.scrollTop = messages.scrollHeight; };
    const bubble = (text, className) => {
        const element = document.createElement('p');
        element.className = `chatbot-message message-enter ${className}`;
        element.textContent = text;
        element.dir = 'auto';
        element.lang = /[\u0980-\u09ff]/.test(text) ? 'bn' : 'en';
        messages.appendChild(element);
        scroll();
        return element;
    };
    const imageUrl = value => {
        try {
            const url = new URL(value || '/Project/assets/product-placeholder.svg', window.location.origin);
            return ['http:', 'https:'].includes(url.protocol) ? url.href : '/Project/assets/product-placeholder.svg';
        } catch { return '/Project/assets/product-placeholder.svg'; }
    };
    const updateHistory = value => {
        if (!Array.isArray(value)) return;
        history = value.filter(turn => turn && ['user', 'assistant'].includes(turn.role) && typeof turn.content === 'string').slice(-6);
    };
    // Load the server catalog and restore this session's last six messages.
    const ready = (async () => {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 3500);
        try {
            const response = await fetch('/Project/ai_search.php', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body: new URLSearchParams({ action: 'init' }), signal: controller.signal
            });
            const data = await response.json();
            if (data.success) {
                updateHistory(data.history);
                for (const turn of history) bubble(turn.content, turn.role === 'user' ? 'user-message' : 'bot-message');
            }
        } catch { /* A failed preload must not disable the assistant or local fallback. */ }
        finally { clearTimeout(timeout); }
    })();
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const query = input.value.trim();
        if (!query || send.disabled) return;
        send.disabled = true;
        if (recognition && listening) recognition.abort();
        if (microphone) microphone.disabled = true;
        await ready;
        bubble(query, 'user-message');
        input.value = '';
        send.disabled = true;
        form.setAttribute('aria-busy', 'true');
        const loading = bubble('Searching...', 'bot-message loading');
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 9500);
        try {
            const response = await fetch('/Project/ai_search.php', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body: new URLSearchParams({ query }), signal: controller.signal
            });
            const data = await response.json();
            loading.remove();
            if (data.fallback) bubble('Showing keyword results (AI unavailable)', 'bot-message fallback-note');
            if (data.message) bubble(String(data.message), 'bot-message');
            if (data.success) updateHistory(data.history);
            const results = Array.isArray(data.results) ? data.results.slice(0, 6) : [];
            if (!results.length && !data.message) bubble(data.success === false ? 'Search unavailable. Please try again.' : 'No products found. Try describing what you need differently.', 'bot-message');
            for (const product of results) {
                const id = Number(product.product_id);
                if (!Number.isSafeInteger(id) || id <= 0) continue;
                const card = document.createElement('a');
                card.className = 'chatbot-product-card message-enter';
                card.href = `/Project/product.php?id=${id}`;
                const image = document.createElement('img');
                image.src = imageUrl(product.image_url);
                image.alt = String(product.name || 'Product');
                image.loading = 'lazy';
                image.addEventListener('error', () => { image.src = '/Project/assets/product-placeholder.svg'; }, { once: true });
                const title = document.createElement('strong');
                title.textContent = String(product.name || 'Product');
                const price = document.createElement('span');
                price.className = 'price';
                price.textContent = `৳${Number(product.price).toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                const link = document.createElement('span');
                link.textContent = 'View Product';
                card.append(image, title, price, link);
                messages.appendChild(card);
            }
        } catch {
            loading.remove();
            bubble('Connection error. Please try again.', 'bot-message');
        } finally {
            clearTimeout(timeout);
            send.disabled = false;
            if (microphone) microphone.disabled = !recognition;
            form.removeAttribute('aria-busy');
            scroll();
            input.focus();
        }
    });
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (microphone && voiceLanguage && voiceStatus) {
        if (!SpeechRecognition) {
            microphone.disabled = true;
            voiceLanguage.disabled = true;
            voiceStatus.textContent = 'Voice input is unavailable in this browser. You can still type.';
        } else {
            recognition = new SpeechRecognition();
            recognition.continuous = false;
            recognition.interimResults = false;
            recognition.maxAlternatives = 1;
            microphone.addEventListener('click', () => {
                if (send.disabled) return;
                if (listening) { recognition.stop(); return; }
                recognition.lang = voiceLanguage.value;
                try { recognition.start(); }
                catch { voiceStatus.textContent = 'Unable to start the microphone. Please try again.'; }
            });
            recognition.onstart = () => {
                listening = true;
                microphone.setAttribute('aria-pressed', 'true');
                microphone.setAttribute('aria-label', 'Stop voice input');
                voiceLanguage.disabled = true;
                voiceStatus.textContent = recognition.lang === 'bn-BD' ? 'শুনছি… বলুন।' : 'Listening… speak now.';
            };
            recognition.onresult = event => {
                let transcript = '';
                for (let i = event.resultIndex; i < event.results.length; i++) {
                    if (event.results[i].isFinal) transcript += event.results[i][0].transcript + ' ';
                }
                transcript = transcript.trim();
                if (!transcript || send.disabled) return;
                input.value = transcript;
                if (Array.from(transcript).length > 255) {
                    voiceStatus.textContent = 'Your message is too long. Shorten it to 255 characters before sending.';
                    input.focus();
                    return;
                }
                voiceStatus.textContent = '';
                form.requestSubmit();
            };
            recognition.onerror = event => {
                const errors = {
                    'not-allowed': 'Microphone permission was denied. Allow microphone access or type your message.',
                    'audio-capture': 'No microphone was found. You can type your message.',
                    'no-speech': 'No speech detected. Please try again.',
                    'network': 'Voice recognition is unavailable. Please type your message.',
                    'language-not-supported': 'This voice language is unavailable in your browser. Try another language or type.'
                };
                if (event.error !== 'aborted') voiceStatus.textContent = errors[event.error] || 'Voice input failed. Please try again.';
            };
            recognition.onend = () => {
                listening = false;
                microphone.setAttribute('aria-pressed', 'false');
                microphone.setAttribute('aria-label', 'Start voice input');
                voiceLanguage.disabled = false;
                if (voiceStatus.textContent === 'Listening… speak now.' || voiceStatus.textContent === 'শুনছি… বলুন।') voiceStatus.textContent = '';
            };
            document.getElementById('chatbotClose')?.addEventListener('click', () => { if (listening) recognition.abort(); });
            window.addEventListener('pagehide', () => { if (listening) recognition.abort(); });
        }
    }
})();
