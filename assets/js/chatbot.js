/**
 * Ask Me Help - Real AI Financial Assistant Client Script
 * Handles in-page floating chat window, real-time typing indicators,
 * markdown parsing, timestamps, and secure API communication.
 */

// Toggle chat window open/close
function toggleAskMeChat() {
    const chatWindow = document.getElementById('askMeChatWindow');
    const fab = document.getElementById('askMeFab');
    const input = document.getElementById('askMeInput');

    if (!chatWindow) return;

    const isActive = chatWindow.classList.toggle('active');
    if (fab) {
        fab.classList.toggle('active', isActive);
    }

    if (isActive) {
        sessionStorage.setItem('askme_chat_open', '1');
        setTimeout(() => {
            if (input) input.focus();
            scrollChatToBottom();
        }, 150);
    } else {
        sessionStorage.setItem('askme_chat_open', '0');
    }
}

function closeAskMeChat() {
    const chatWindow = document.getElementById('askMeChatWindow');
    const fab = document.getElementById('askMeFab');
    if (chatWindow) chatWindow.classList.remove('active');
    if (fab) fab.classList.remove('active');
    sessionStorage.setItem('askme_chat_open', '0');
}

function scrollChatToBottom() {
    const container = document.getElementById('askMeMessages');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

// Quick Topic Shortcut Button Click
function sendQuickQuestion(question) {
    const input = document.getElementById('askMeInput');
    if (input) {
        input.value = question;
        sendChatMessage(question);
        input.value = '';
    }
}

// Global Form Submit Handler
function handleChatSubmit(event) {
    if (event) event.preventDefault();
    const input = document.getElementById('askMeInput');
    if (!input) return;

    const query = input.value.trim();
    if (!query) return;

    input.value = '';
    sendChatMessage(query);
}

// Formats Markdown into clean HTML
function formatMarkdown(text) {
    if (!text) return '';
    let html = text;

    // Bold **text**
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

    // Inline `code`
    html = html.replace(/`([^`]+)`/g, '<code>$1</code>');

    // Bullet points
    html = html.replace(/^\s*[-•*]\s+(.*)$/gim, '<li>$1</li>');
    html = html.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');

    // Line breaks to <br> if not already HTML
    if (!html.includes('<br>') && !html.includes('<p>') && !html.includes('<ul>')) {
        html = html.replace(/\n\n/g, '<br><br>').replace(/\n/g, '<br>');
    }

    return html;
}

function getCurrentTimeStr() {
    const now = new Date();
    return now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// Send Message to Backend AI Endpoint
async function sendChatMessage(text) {
    const messagesContainer = document.getElementById('askMeMessages');
    const sendBtn = document.getElementById('askMeSendBtn');
    const input = document.getElementById('askMeInput');

    // Append User Message Bubble
    appendUserBubble(text);
    scrollChatToBottom();

    // Disable input while processing
    if (sendBtn) sendBtn.disabled = true;
    if (input) input.disabled = true;

    // Show Typing Indicator
    const typingId = showTypingIndicator();
    scrollChatToBottom();

    // Determine API endpoint path relative to current URL
    let apiUrl = 'api/chatbot_api.php';
    if (!window.location.pathname.endsWith('/') && window.location.pathname.includes('/')) {
        // If loaded in a subpage
        const segments = window.location.pathname.split('/');
        segments.pop();
        const basePath = segments.join('/');
        apiUrl = basePath + '/api/chatbot_api.php';
    }

    try {
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ message: text })
        });

        const data = await response.json();
        removeTypingIndicator(typingId);

        if (data && data.success && data.reply) {
            appendBotBubble(data.reply);
        } else {
            appendBotBubble(data.error || "I could not formulate an answer right now. Please try asking again.");
        }
    } catch (err) {
        // Fallback: try relative path if absolute failed
        try {
            const fallbackRes = await fetch('api/chatbot_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: text })
            });
            const fbData = await fallbackRes.json();
            removeTypingIndicator(typingId);
            if (fbData && fbData.success && fbData.reply) {
                appendBotBubble(fbData.reply);
            } else {
                appendBotBubble("Could not process request. Please try again.");
            }
        } catch (innerErr) {
            removeTypingIndicator(typingId);
            appendBotBubble("Could not connect to the AI assistant server. Please check your Apache/MySQL connection in XAMPP.");
        }
    } finally {
        if (sendBtn) sendBtn.disabled = false;
        if (input) {
            input.disabled = false;
            input.focus();
        }
        scrollChatToBottom();
    }
}

function appendUserBubble(text) {
    const container = document.getElementById('askMeMessages');
    if (!container) return;

    const msgDiv = document.createElement('div');
    msgDiv.className = 'askme-msg askme-msg-user';

    const contentDiv = document.createElement('div');

    const bubble = document.createElement('div');
    bubble.className = 'askme-msg-bubble';
    bubble.textContent = text;

    const time = document.createElement('div');
    time.className = 'askme-msg-time';
    time.textContent = getCurrentTimeStr();

    contentDiv.appendChild(bubble);
    contentDiv.appendChild(time);
    msgDiv.appendChild(contentDiv);
    container.appendChild(msgDiv);
}

function appendBotBubble(content) {
    const container = document.getElementById('askMeMessages');
    if (!container) return;

    const msgDiv = document.createElement('div');
    msgDiv.className = 'askme-msg askme-msg-bot';

    const avatar = document.createElement('div');
    avatar.className = 'askme-msg-avatar';
    avatar.innerHTML = '<i class="fa-solid fa-robot"></i>';

    const contentDiv = document.createElement('div');

    const bubble = document.createElement('div');
    bubble.className = 'askme-msg-bubble';
    bubble.innerHTML = formatMarkdown(content);

    const time = document.createElement('div');
    time.className = 'askme-msg-time';
    time.textContent = getCurrentTimeStr();

    contentDiv.appendChild(bubble);
    contentDiv.appendChild(time);

    msgDiv.appendChild(avatar);
    msgDiv.appendChild(contentDiv);
    container.appendChild(msgDiv);
}

function showTypingIndicator() {
    const container = document.getElementById('askMeMessages');
    if (!container) return null;

    const id = 'typing_' + Date.now();
    const typingDiv = document.createElement('div');
    typingDiv.className = 'askme-msg askme-msg-bot askme-typing-indicator';
    typingDiv.id = id;

    const avatar = document.createElement('div');
    avatar.className = 'askme-msg-avatar';
    avatar.innerHTML = '<i class="fa-solid fa-robot"></i>';

    const bubble = document.createElement('div');
    bubble.className = 'askme-msg-bubble';
    bubble.innerHTML = '<span style="font-size: 0.8rem; color: #94a3b8; margin-right: 8px;">AI is thinking...</span><span class="askme-dot"></span><span class="askme-dot"></span><span class="askme-dot"></span>';

    typingDiv.appendChild(avatar);
    typingDiv.appendChild(bubble);
    container.appendChild(typingDiv);
    return id;
}

function removeTypingIndicator(id) {
    if (!id) return;
    const el = document.getElementById(id);
    if (el) el.remove();
}

// Restore chat state on page load if opened previously
document.addEventListener('DOMContentLoaded', () => {
    const greetingTime = document.getElementById('greetingTime');
    if (greetingTime) {
        greetingTime.textContent = getCurrentTimeStr();
    }
    
    // Auto-restore open state if user navigated within the app
    if (sessionStorage.getItem('askme_chat_open') === '1') {
        const chatWindow = document.getElementById('askMeChatWindow');
        const fab = document.getElementById('askMeFab');
        if (chatWindow) chatWindow.classList.add('active');
        if (fab) fab.classList.add('active');
        setTimeout(scrollChatToBottom, 100);
    }
});
