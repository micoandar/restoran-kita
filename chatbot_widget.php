<?php
/**
 * Chatbot Widget — Include di index.php dan order.php
 * Cara pakai: <?php include 'chatbot_widget.php'; ?>
 */
?>
<!-- ===== CHATBOT WIDGET ===== -->
<style>
    .chatbot-toggle {
        position: fixed;
        bottom: 25px;
        right: 25px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 100%);
        color: white;
        border: none;
        box-shadow: 0 10px 30px rgba(255, 107, 107, 0.4);
        cursor: pointer;
        z-index: 9998;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        animation: chatbotPulse 2s ease-in-out infinite;
    }
    .chatbot-toggle:hover {
        transform: scale(1.1);
        box-shadow: 0 15px 40px rgba(255, 107, 107, 0.5);
    }
    .chatbot-toggle.active {
        background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
        animation: none;
    }
    .chatbot-toggle .chatbot-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        width: 20px;
        height: 20px;
        background: #10B981;
        border-radius: 50%;
        border: 2px solid white;
    }
    @keyframes chatbotPulse {
        0%, 100% { box-shadow: 0 10px 30px rgba(255, 107, 107, 0.4); }
        50% { box-shadow: 0 10px 40px rgba(255, 107, 107, 0.7); }
    }
    .chatbot-window {
        position: fixed;
        bottom: 100px;
        right: 25px;
        width: 380px;
        max-width: calc(100vw - 40px);
        height: 550px;
        max-height: calc(100vh - 120px);
        background: white;
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        z-index: 9999;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transform: translateY(20px) scale(0.95);
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
    }
    .chatbot-window.open {
        transform: translateY(0) scale(1);
        opacity: 1;
        visibility: visible;
    }
    .chatbot-header {
        background: linear-gradient(135deg, #1A1F36 0%, #2D3748 100%);
        color: white;
        padding: 18px 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-shrink: 0;
    }
    .chatbot-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
        position: relative;
    }
    .chatbot-avatar::after {
        content: '';
        position: absolute;
        bottom: 2px;
        right: 2px;
        width: 10px;
        height: 10px;
        background: #10B981;
        border: 2px solid #1A1F36;
        border-radius: 50%;
    }
    .chatbot-header-info { flex: 1; min-width: 0; }
    .chatbot-header-name { font-weight: 700; font-size: 1rem; margin-bottom: 2px; }
    .chatbot-header-status { font-size: 0.75rem; opacity: 0.8; display: flex; align-items: center; gap: 5px; }
    .chatbot-header-status::before { content: ''; width: 6px; height: 6px; background: #10B981; border-radius: 50%; }
    .chatbot-close {
        background: rgba(255, 255, 255, 0.1);
        border: none;
        color: white;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    .chatbot-close:hover { background: rgba(255, 255, 255, 0.2); }
    .chatbot-body {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        background: #F8FAFC;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .chatbot-body::-webkit-scrollbar { width: 6px; }
    .chatbot-body::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 10px; }
    .chat-message {
        display: flex;
        gap: 8px;
        max-width: 85%;
        animation: chatFadeIn 0.3s ease-out;
    }
    .chat-message.user { align-self: flex-end; flex-direction: row-reverse; }
    .chat-message.bot { align-self: flex-start; }
    @keyframes chatFadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .chat-bubble {
        padding: 10px 14px;
        border-radius: 16px;
        font-size: 0.9rem;
        line-height: 1.5;
        word-wrap: break-word;
        white-space: pre-wrap;
    }
    .chat-message.user .chat-bubble {
        background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 100%);
        color: white;
        border-bottom-right-radius: 4px;
    }
    .chat-message.bot .chat-bubble {
        background: white;
        color: #1A1F36;
        border-bottom-left-radius: 4px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }
    .chat-bubble strong { font-weight: 700; }
    .typing-indicator {
        display: flex;
        gap: 4px;
        padding: 12px 16px;
        background: white;
        border-radius: 16px;
        border-bottom-left-radius: 4px;
        width: fit-content;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }
    .typing-indicator span {
        width: 8px; height: 8px; background: #94A3B8; border-radius: 50%;
        animation: typingBounce 1.4s infinite;
    }
    .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
    .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
    @keyframes typingBounce {
        0%, 60%, 100% { transform: translateY(0); opacity: 0.5; }
        30% { transform: translateY(-8px); opacity: 1; }
    }

    /* ============================================================
       QUICK REPLIES — horizontal scroll + tombol bisa di-scroll
       ============================================================ */
    .chat-quick-replies-wrapper {
        background: #F8FAFC;
        flex-shrink: 0;
    }
    .chat-quick-replies {
        display: flex;
        gap: 6px;
        padding: 0 20px 12px;
        overflow-x: auto;
        overflow-y: hidden;
        scrollbar-width: thin;
        scrollbar-color: #CBD5E1 transparent;
        -webkit-overflow-scrolling: touch;
    }
    .chat-quick-replies::-webkit-scrollbar {
        height: 4px;
    }
    .chat-quick-replies::-webkit-scrollbar-track {
        background: transparent;
    }
    .chat-quick-replies::-webkit-scrollbar-thumb {
        background: #CBD5E1;
        border-radius: 10px;
    }
    .quick-reply-btn {
        background: white;
        border: 1px solid #E2E8F0;
        color: #475569;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.78rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        white-space: nowrap;
        flex-shrink: 0;
        font-family: inherit;
    }
    .quick-reply-btn:hover {
        background: #FF6B6B;
        color: white;
        border-color: #FF6B6B;
        transform: translateY(-1px);
    }
    .chatbot-footer {
        padding: 12px 16px;
        background: white;
        border-top: 1px solid #E2E8F0;
        display: flex;
        gap: 8px;
        align-items: center;
        flex-shrink: 0;
    }
    .chatbot-input {
        flex: 1;
        padding: 12px 16px;
        border: 2px solid #E2E8F0;
        border-radius: 25px;
        font-size: 0.9rem;
        outline: none;
        font-family: inherit;
        transition: all 0.2s;
    }
    .chatbot-input:focus {
        border-color: #FF6B6B;
        box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
    }
    .chatbot-send {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 100%);
        border: none;
        color: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        flex-shrink: 0;
    }
    .chatbot-send:hover:not(:disabled) {
        transform: scale(1.05);
        box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
    }
    .chatbot-send:disabled { opacity: 0.5; cursor: not-allowed; }
    @media (max-width: 480px) {
        .chatbot-window {
            width: calc(100vw - 20px);
            right: 10px;
            bottom: 90px;
            height: calc(100vh - 120px);
            max-height: 600px;
        }
        .chatbot-toggle { bottom: 15px; right: 15px; width: 56px; height: 56px; }
    }
</style>

<button class="chatbot-toggle" id="chatbotToggle" aria-label="Buka chat">
    <i class="fas fa-comment-dots" id="chatbotToggleIcon"></i>
    <span class="chatbot-badge"></span>
</button>

<div class="chatbot-window" id="chatbotWindow">
    <div class="chatbot-header">
        <div class="chatbot-avatar"><i class="fas fa-robot"></i></div>
        <div class="chatbot-header-info">
            <div class="chatbot-header-name">Asisten Restoran</div>
            <div class="chatbot-header-status">Online sekarang</div>
        </div>
        <button class="chatbot-close" id="chatbotClose" aria-label="Tutup">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="chatbot-body" id="chatbotBody"></div>
    
    <!-- ============================================================
         QUICK REPLIES — banyak tombol, bisa scroll horizontal
         ============================================================ -->
    <div class="chat-quick-replies-wrapper">
        <div class="chat-quick-replies" id="chatbotQuickReplies">
            <!-- Info Restoran -->
            <button class="quick-reply-btn" data-msg="Jam buka berapa?">🕐 Jam buka</button>
            <button class="quick-reply-btn" data-msg="Alamat di mana?">📍 Lokasi</button>
            <button class="quick-reply-btn" data-msg="Kontak restoran">📞 Kontak</button>
            
            <!-- Menu -->
            <button class="quick-reply-btn" data-msg="Ada menu apa saja?">📋 Lihat menu</button>
            <button class="quick-reply-btn" data-msg="Menu makanan apa saja?">🍽 Makanan</button>
            <button class="quick-reply-btn" data-msg="Menu minuman apa saja?">🥤 Minuman</button>
            <button class="quick-reply-btn" data-msg="Menu dessert apa saja?">🍰 Dessert</button>
            <button class="quick-reply-btn" data-msg="Menu paket apa saja?">🍱 Paket</button>
            
            <!-- Rekomendasi -->
            <button class="quick-reply-btn" data-msg="Rekomendasi menu terlaris">⭐ Rekomendasi</button>
            <button class="quick-reply-btn" data-msg="Menu termurah">💸 Termurah</button>
            <button class="quick-reply-btn" data-msg="Menu termahal">👑 Termahal</button>
            
            <!-- Cara Pesan & Bayar -->
            <button class="quick-reply-btn" data-msg="Cara pesan bagaimana?">🛒 Cara pesan</button>
            <button class="quick-reply-btn" data-msg="Cara bayar">💳 Pembayaran</button>
            
            <!-- Promo -->
            <button class="quick-reply-btn" data-msg="Ada promo?">🎉 Promo</button>
        </div>
    </div>
    
    <div class="chatbot-footer">
        <input type="text" class="chatbot-input" id="chatbotInput" 
               placeholder="Ketik pertanyaan..." maxlength="500" autocomplete="off">
        <button class="chatbot-send" id="chatbotSend" aria-label="Kirim">
            <i class="fas fa-paper-plane"></i>
        </button>
    </div>
</div>

<script>
(function() {
    'use strict';
    
    const toggle    = document.getElementById('chatbotToggle');
    const toggleIcon= document.getElementById('chatbotToggleIcon');
    const windowEl  = document.getElementById('chatbotWindow');
    const closeBtn  = document.getElementById('chatbotClose');
    const body      = document.getElementById('chatbotBody');
    const input     = document.getElementById('chatbotInput');
    const sendBtn   = document.getElementById('chatbotSend');
    const quickReplies = document.getElementById('chatbotQuickReplies');
    
    let isOpen = false;
    let isFirstOpen = true;
    let isSending = false;
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatMessage(text) {
        let html = escapeHtml(text);
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        return html;
    }
    
    function addMessage(text, sender) {
        const wrap = document.createElement('div');
        wrap.className = 'chat-message ' + sender;
        const bubble = document.createElement('div');
        bubble.className = 'chat-bubble';
        if (sender === 'bot') {
            bubble.innerHTML = formatMessage(text);
        } else {
            bubble.textContent = text;
        }
        wrap.appendChild(bubble);
        body.appendChild(wrap);
        scrollToBottom();
    }
    
    function showTyping() {
        const wrap = document.createElement('div');
        wrap.className = 'chat-message bot';
        wrap.id = 'typingIndicator';
        wrap.innerHTML = '<div class="typing-indicator"><span></span><span></span><span></span></div>';
        body.appendChild(wrap);
        scrollToBottom();
    }
    
    function hideTyping() {
        const el = document.getElementById('typingIndicator');
        if (el) el.remove();
    }
    
    function scrollToBottom() {
        setTimeout(() => { body.scrollTop = body.scrollHeight; }, 50);
    }
    
    async function sendMessage(msg) {
        if (isSending) return;
        msg = msg.trim();
        if (msg === '') return;
        
        isSending = true;
        sendBtn.disabled = true;
        // Quick replies tetap tampil — user bisa klik beberapa kali
        
        addMessage(msg, 'user');
        input.value = '';
        showTyping();
        
        try {
            const formData = new FormData();
            formData.append('message', msg);
            
            const response = await fetch('chatbot.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            
            const rawText = await response.text();
            let data;
            try {
                data = JSON.parse(rawText);
            } catch (e) {
                console.error('Response bukan JSON:', rawText.substring(0, 300));
                hideTyping();
                addMessage('Maaf, terjadi kesalahan sistem. Coba lagi ya.', 'bot');
                isSending = false;
                sendBtn.disabled = false;
                return;
            }
            
            hideTyping();
            setTimeout(() => {
                addMessage(data.reply || 'Maaf, saya tidak bisa merespon sekarang.', 'bot');
                isSending = false;
                sendBtn.disabled = false;
                input.focus();
            }, 400);
        } catch (err) {
            console.error(err);
            hideTyping();
            addMessage('Koneksi gagal. Periksa internet Anda dan coba lagi.', 'bot');
            isSending = false;
            sendBtn.disabled = false;
        }
    }
    
    function openChat() {
        isOpen = true;
        windowEl.classList.add('open');
        toggle.classList.add('active');
        toggleIcon.className = 'fas fa-times';
        
        if (isFirstOpen) {
            isFirstOpen = false;
            setTimeout(() => {
                addMessage("Halo! 👋 Saya Asisten Restoran Kita.\n\nSaya bisa membantu Anda dengan:\n• Info menu & harga\n• Rekomendasi terlaris\n• Status pesanan\n• Cara pesan & bayar\n\nKetik pertanyaan atau pilih tombol cepat di bawah ya!", 'bot');
            }, 300);
        }
        setTimeout(() => input.focus(), 400);
    }
    
    function closeChat() {
        isOpen = false;
        windowEl.classList.remove('open');
        toggle.classList.remove('active');
        toggleIcon.className = 'fas fa-comment-dots';
    }
    
    toggle.addEventListener('click', () => isOpen ? closeChat() : openChat());
    closeBtn.addEventListener('click', closeChat);
    sendBtn.addEventListener('click', () => sendMessage(input.value));
    
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage(input.value);
        }
    });
    
    quickReplies.addEventListener('click', (e) => {
        const btn = e.target.closest('.quick-reply-btn');
        if (btn) {
            const msg = btn.getAttribute('data-msg');
            if (msg) sendMessage(msg);
        }
    });
})();
</script>
<!-- ===== END CHATBOT WIDGET ===== -->