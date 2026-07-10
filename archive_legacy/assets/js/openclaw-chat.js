// OpenClaw Chat Widget Logic

document.addEventListener('DOMContentLoaded', () => {
    // Inject HTML
    const chatHtml = `
    <div class="oc-chat-widget">
        <button id="ocChatBtn" class="oc-chat-button">💬</button>
        <div id="ocChatWindow" class="oc-chat-window">
            <div class="oc-chat-header">
                <div>
                    <h3>OpenClaw AI</h3>
                    <p>Trợ lý CSKH Điện Máy Hiếu</p>
                </div>
                <button id="ocCloseBtn" class="oc-close-btn">&times;</button>
            </div>
            <div id="ocChatMessages" class="oc-chat-messages">
                <div class="oc-msg ai">Xin chào! Tôi là trợ lý AI của dienmayhieu.com. Bạn cần tư vấn dịch vụ, sản phẩm hay gọi thợ ạ?</div>
            </div>
            <div class="oc-chat-input-area">
                <input type="text" id="ocChatInput" class="oc-chat-input" placeholder="Nhập tin nhắn..." autocomplete="off">
                <button id="ocSendBtn" class="oc-send-btn">➤</button>
            </div>
        </div>
    </div>
    `;

    document.body.insertAdjacentHTML('beforeend', chatHtml);

    const chatBtn = document.getElementById('ocChatBtn');
    const chatWindow = document.getElementById('ocChatWindow');
    const closeBtn = document.getElementById('ocCloseBtn');
    const chatInput = document.getElementById('ocChatInput');
    const sendBtn = document.getElementById('ocSendBtn');
    const chatMessages = document.getElementById('ocChatMessages');

    // Generate or get Session ID
    let sessionId = sessionStorage.getItem('oc_session_id');
    if (!sessionId) {
        sessionId = 'sess_' + Math.random().toString(36).substr(2, 9);
        sessionStorage.setItem('oc_session_id', sessionId);
    }

    // Toggle chat window
    chatBtn.addEventListener('click', () => {
        chatWindow.classList.add('active');
        chatBtn.style.display = 'none';
        chatInput.focus();
    });

    closeBtn.addEventListener('click', () => {
        chatWindow.classList.remove('active');
        chatBtn.style.display = 'flex';
    });

    // Send message
    const sendMessage = async () => {
        const text = chatInput.value.trim();
        if (!text) return;

        // Add user message to UI
        appendMessage('user', text);
        chatInput.value = '';
        
        // Show typing indicator
        const typingId = 'typing_' + Date.now();
        const typingHtml = `<div id="${typingId}" class="oc-typing">AI đang trả lời...</div>`;
        chatMessages.insertAdjacentHTML('beforeend', typingHtml);
        scrollToBottom();

        try {
            const response = await fetch('/api/openclaw_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    message: text,
                    session_id: sessionId
                })
            });
            
            const data = await response.json();
            
            // Remove typing indicator
            document.getElementById(typingId)?.remove();

            if (data.status === 'success') {
                appendMessage('ai', data.data.text, data.data.actions);
            } else {
                appendMessage('ai', 'Xin lỗi, hệ thống đang gặp sự cố. Bạn vui lòng thử lại sau.');
            }
            
        } catch (error) {
            document.getElementById(typingId)?.remove();
            appendMessage('ai', 'Lỗi kết nối đến máy chủ AI.');
        }
    };

    sendBtn.addEventListener('click', sendMessage);
    chatInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
    });

    function appendMessage(sender, text, actions = []) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `oc-msg ${sender}`;
        msgDiv.textContent = text;
        
        if (actions && actions.length > 0) {
            const actionsDiv = document.createElement('div');
            actionsDiv.className = 'oc-msg-actions';
            
            actions.forEach(act => {
                let actElement;
                if (act.type === 'link') {
                    actElement = document.createElement('a');
                    actElement.href = act.value;
                    actElement.target = '_blank';
                } else if (act.type === 'call') {
                    actElement = document.createElement('a');
                    actElement.href = act.value;
                } else {
                    actElement = document.createElement('button');
                    actElement.type = 'button';
                    actElement.onclick = () => {
                        // Custom logic for other buttons
                        alert('Hành động: ' + act.value);
                    };
                }
                
                actElement.className = 'oc-action-btn';
                actElement.textContent = act.label;
                actionsDiv.appendChild(actElement);
            });
            
            msgDiv.appendChild(actionsDiv);
        }

        chatMessages.appendChild(msgDiv);
        scrollToBottom();
    }

    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
});
