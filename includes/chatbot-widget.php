<?php
/**
 * chatbot-widget.php
 * One Palayan-Kalinga Hub - Floating AI Citizen's Charter Chatbot Widget
 * 
 * Powered by Google Gemini 1.5 Flash, OpenAI GPT-4o-mini, and
 * municipal CSWDO Citizen's Charter Knowledge Engine.
 */
?>
<!-- ========================================================
     PALAYAN CSWDO CITIZEN'S CHARTER AI CHATBOT WIDGET
     ======================================================== -->
<div id="charterChatbotRoot" class="charter-bot-container" aria-label="CSWDO Citizen's Charter AI Assistant">
    <!-- Floating Launcher Pill Button (Desktop Capsule / Mobile Tiny Icon) -->
    <button type="button" class="charter-bot-launcher" id="charterBotLauncher" title="Buksan ang Lingkod Kalinga AI Chatbot" aria-label="Lingkod Kalinga AI">
        <!-- Desktop Pill Elements -->
        <div class="launcher-avatar-ring">
            <img src="images/logo.png" alt="Palayan Logo" class="launcher-seal" onerror="this.src='../images/logo.png'">
            <span class="launcher-status-dot"></span>
        </div>
        <div class="launcher-text-block">
            <span class="launcher-main-title"><i class="fas fa-robot"></i> Kalinga AI</span>
            <span class="launcher-sub-title">Citizen's Charter</span>
        </div>
        <!-- Mobile Robot Icon (Hidden on Desktop) -->
        <i class="fas fa-robot launcher-robot-icon-mobile"></i>
        <span class="launcher-status-dot launcher-status-dot-mobile"></span>
        <span class="launcher-pulse-ring"></span>
    </button>

    <!-- Floating Chat Window Card -->
    <div class="charter-bot-window" id="charterBotWindow" style="display: none;">
        <!-- Header -->
        <div class="charter-bot-header">
            <div class="bot-header-left">
                <div class="bot-header-avatar">
                    <img src="images/logo.png" alt="Palayan Seal" onerror="this.src='../images/logo.png'">
                    <span class="bot-avatar-badge"><i class="fas fa-robot"></i></span>
                </div>
                <div class="bot-header-info">
                    <div class="bot-name-row">
                        <span class="bot-name">Lingkod Kalinga AI</span>
                        <span class="bot-status-tag"><i class="fas fa-circle"></i> Online 24/7</span>
                    </div>
                    <span class="bot-subtext">CSWDO Palayan Citizen's Charter Assistant</span>
                </div>
            </div>
            <div class="bot-header-actions">
                <button type="button" class="btn-bot-action" id="charterBotResetBtn" title="Bagong Usapan (Clear Chat)">
                    <i class="fas fa-redo-alt"></i>
                </button>
                <button type="button" class="btn-bot-action" id="charterBotCloseBtn" title="Isara (Close)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <!-- Quick Topic Suggestion Carousel -->
        <div class="charter-quick-topics" id="charterQuickTopics">
            <div class="quick-topics-track">
                <button type="button" class="quick-topic-chip" data-query="Ano ang requirements sa AICS Medical?">
                    <i class="fas fa-file-medical"></i> AICS Medical
                </button>
                <button type="button" class="quick-topic-chip" data-query="Paano mag-apply sa Pabahay / Akap Village?">
                    <i class="fas fa-home"></i> Pabahay / Housing
                </button>
                <button type="button" class="quick-topic-chip" data-query="Requirements sa AICS Burial / Libing?">
                    <i class="fas fa-cross"></i> Burial Assistance
                </button>
                <?php if (defined('FEATURE_ENABLE_PWD_SENIOR') && FEATURE_ENABLE_PWD_SENIOR): ?>
                <button type="button" class="quick-topic-chip" data-query="Paano kumuha ng Senior Citizen ID at Pension?">
                    <i class="fas fa-blind"></i> Senior Citizen OSCA
                </button>
                <button type="button" class="quick-topic-chip" data-query="Paano mag-apply ng PWD ID at Booklet?">
                    <i class="fas fa-wheelchair"></i> PWD ID &amp; Benefits
                </button>
                <?php endif; ?>
                <button type="button" class="quick-topic-chip" data-query="Paano mag-verify ng resident account?">
                    <i class="fas fa-id-card"></i> Verify Account
                </button>
                <button type="button" class="quick-topic-chip" data-query="Paano mag-book ng Appointment sa City Hall?">
                    <i class="fas fa-calendar-check"></i> Book Appointment
                </button>
                <button type="button" class="quick-topic-chip" data-query="Ano ang oras at lokasyon ng CSWDO sa City Hall?">
                    <i class="fas fa-clock"></i> Office Hours &amp; Location
                </button>
            </div>
        </div>

        <!-- Conversation History Scrollable Stream -->
        <div class="charter-messages-stream" id="charterMessagesStream">
            <!-- Initial Welcome Message from Bot -->
            <div class="chat-message-row bot-row">
                <div class="chat-msg-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="chat-msg-content">
                    <div class="chat-msg-bubble">
                        <p style="margin: 0 0 8px 0; font-weight: 700; color: #7b1113;">
                            Magandang araw po! Ako po si Lingkod Kalinga AI.
                        </p>
                        <p style="margin: 0 0 8px 0; font-size: 0.84rem; line-height: 1.5;">
                            Handa po akong magbigay ng agarang impormasyon at patnubay batay sa opisyal na <strong>Citizen's Charter ng CSWDO Palayan City</strong> ukol sa:
                        </p>
                        <ul style="margin: 0 0 10px 0; padding-left: 18px; font-size: 0.82rem; line-height: 1.5;">
                            <li><strong>AICS Assistance</strong> (Medical, Burol, Edukasyon, Ayuda)</li>
                            <li><strong>Socialized Housing</strong> (Akap Village, San Lorenzo)</li>
                            <?php if (defined('FEATURE_ENABLE_PWD_SENIOR') && FEATURE_ENABLE_PWD_SENIOR): ?>
                            <li><strong>Senior Citizen (OSCA)</strong> &amp; <strong>PWD (PDAO)</strong> IDs</li>
                            <?php endif; ?>
                            <li><strong>Online Appointments</strong> &amp; <strong>Resident Verification</strong></li>
                        </ul>
                        <p style="margin: 0; font-size: 0.8rem; color: #64748b;">
                            Pumili po ng paksa sa itaas o itype ang inyong katanungan sa ibaba sa Tagalog o English!
                        </p>
                    </div>
                    <span class="chat-msg-time">Lingkod Kalinga AI · Kakapasok lang</span>
                </div>
            </div>

            <!-- Typing Dots Indicator (Hidden by default) -->
            <div class="chat-message-row bot-row" id="charterTypingIndicator" style="display: none;">
                <div class="chat-msg-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="chat-msg-content">
                    <div class="chat-msg-bubble typing-bubble">
                        <span class="dot-bounce"></span>
                        <span class="dot-bounce"></span>
                        <span class="dot-bounce"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chat Input Footer Area -->
        <div class="charter-bot-footer">
            <form id="charterChatForm" class="charter-input-wrapper">
                <input type="text" id="charterChatInput" placeholder="Magtanong ukol sa serbisyo ng CSWDO..." autocomplete="off" required>
                <button type="submit" class="btn-chat-send" id="charterSendBtn" title="Ipadala">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
            <div class="charter-footer-meta">
                <span><i class="fas fa-shield-alt"></i> Official Palayan Citizen's Charter AI · RA 11032 Compliant</span>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================
     CHATBOT STYLES (MATCHES PALAYAN CITY MAROON & GOLD)
     ======================================================== -->
<style>
/* Keyframe animations for Kalinga AI appearing and vanishing */
@keyframes kaiAppearBounce {
    0% {
        transform: translateY(90px) scale(0.75);
        opacity: 0;
    }
    60% {
        transform: translateY(-8px) scale(1.04);
        opacity: 1;
    }
    80% {
        transform: translateY(3px) scale(0.98);
        opacity: 1;
    }
    100% {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
}

@keyframes kaiVanishSlide {
    0% {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
    25% {
        transform: translateY(-4px) scale(1.02);
        opacity: 0.95;
    }
    100% {
        transform: translateY(90px) scale(0.75);
        opacity: 0;
    }
}

/* Root Launcher Container */
.charter-bot-container {
    position: fixed;
    bottom: 22px;
    right: 22px;
    z-index: 99999;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    transform: translateY(90px) scale(0.75);
    opacity: 0;
    pointer-events: none;
    will-change: transform, opacity;
}

/* Kalinga AI Visible State with Animation */
.charter-bot-container.kai-visible {
    animation: kaiAppearBounce 0.65s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
    pointer-events: auto !important;
}

/* Kalinga AI Vanish State with Animation */
.charter-bot-container.kai-vanished {
    animation: kaiVanishSlide 0.55s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    pointer-events: none !important;
}

/* Chat Window Open State (stays visible while chatting) */
.charter-bot-container.kai-open {
    transform: translateY(0) scale(1) !important;
    opacity: 1 !important;
    pointer-events: auto !important;
    animation: none !important;
}

.charter-bot-launcher {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 12px;
    padding: 10px 18px 10px 12px;
    background: linear-gradient(135deg, #7b1113 0%, #9a1a1e 50%, #c9a86c 100%);
    color: #ffffff;
    border: 2px solid rgba(255, 255, 255, 0.4);
    border-radius: 50px;
    box-shadow: 0 8px 25px rgba(123, 17, 19, 0.35), 0 2px 6px rgba(0, 0, 0, 0.15);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    outline: none;
}

.charter-bot-launcher:hover {
    transform: translateY(-3px) scale(1.03);
    box-shadow: 0 12px 30px rgba(123, 17, 19, 0.45), 0 4px 10px rgba(0, 0, 0, 0.2);
}

.charter-bot-launcher:active {
    transform: translateY(0) scale(0.98);
}

.launcher-avatar-ring {
    position: relative;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #ffffff;
    padding: 2px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    flex-shrink: 0;
}

.launcher-seal {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.launcher-status-dot {
    position: absolute;
    bottom: -1px;
    right: -1px;
    width: 11px;
    height: 11px;
    border-radius: 50%;
    background: #10b981;
    border: 2px solid #ffffff;
    box-shadow: 0 0 6px #10b981;
}

.launcher-robot-icon-mobile,
.launcher-status-dot-mobile {
    display: none;
}

.launcher-text-block {
    display: flex;
    flex-direction: column;
    text-align: left;
    line-height: 1.2;
}

.launcher-main-title {
    font-size: 0.88rem;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: 0.2px;
}

.launcher-sub-title {
    font-size: 0.68rem;
    font-weight: 600;
    color: #fef08a;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Floating Window Container */
.charter-bot-window {
    position: fixed;
    bottom: 84px;
    right: 22px;
    width: 390px;
    max-width: calc(100vw - 32px);
    height: 580px;
    max-height: calc(100vh - 110px);
    background: #ffffff;
    border: 1px solid rgba(123, 17, 19, 0.2);
    border-radius: 20px;
    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.25), 0 5px 15px rgba(123, 17, 19, 0.15);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: charterSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    z-index: 100000;
}

@keyframes charterSlideUp {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.96);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Header */
.charter-bot-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    background: linear-gradient(135deg, #7b1113 0%, #94171a 100%);
    color: #ffffff;
    border-bottom: 2px solid #c9a86c;
}

.bot-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.bot-header-avatar {
    position: relative;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #ffffff;
    padding: 2px;
    flex-shrink: 0;
}

.bot-header-avatar img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    border-radius: 50%;
}

.bot-avatar-badge {
    position: absolute;
    bottom: -2px;
    right: -2px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #c9a86c;
    color: #7b1113;
    font-size: 0.6rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1.5px solid #ffffff;
}

.bot-header-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.bot-name-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.bot-name {
    font-size: 0.95rem;
    font-weight: 800;
    color: #ffffff;
    white-space: nowrap;
}

.bot-status-tag {
    font-size: 0.65rem;
    font-weight: 700;
    color: #34d399;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.bot-status-tag i {
    font-size: 0.45rem;
}

.bot-subtext {
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.85);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.bot-header-actions {
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-bot-action {
    background: rgba(255, 255, 255, 0.15);
    border: none;
    color: #ffffff;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.2s ease;
}

.btn-bot-action:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: scale(1.05);
}

/* Quick Topic Suggestion Carousel */
.charter-quick-topics {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 8px 10px;
    overflow-x: auto;
    scrollbar-width: none;
}

.charter-quick-topics::-webkit-scrollbar {
    display: none;
}

.quick-topics-track {
    display: flex;
    gap: 6px;
    white-space: nowrap;
}

.quick-topic-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 20px;
    font-size: 0.73rem;
    font-weight: 600;
    color: #334155;
    cursor: pointer;
    transition: all 0.2s ease;
}

.quick-topic-chip i {
    color: #7b1113;
    font-size: 0.75rem;
}

.quick-topic-chip:hover {
    background: #7b1113;
    color: #ffffff;
    border-color: #7b1113;
    transform: translateY(-1px);
}

.quick-topic-chip:hover i {
    color: #fef08a;
}

/* Messages Stream */
.charter-messages-stream {
    flex: 1;
    padding: 14px;
    overflow-y: auto;
    background: #fdfbf7;
    display: flex;
    flex-direction: column;
    gap: 14px;
    scroll-behavior: smooth;
}

.chat-message-row {
    display: flex;
    gap: 10px;
    max-width: 90%;
}

.chat-message-row.bot-row {
    align-self: flex-start;
}

.chat-message-row.user-row {
    align-self: flex-end;
    flex-direction: row-reverse;
}

.chat-msg-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #7b1113, #94171a);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(123, 17, 19, 0.2);
}

.chat-message-row.user-row .chat-msg-avatar {
    background: #0f172a;
}

.chat-msg-content {
    display: flex;
    flex-direction: column;
}

.chat-msg-bubble {
    padding: 12px 14px;
    border-radius: 16px;
    font-size: 0.84rem;
    line-height: 1.55;
    color: #1e293b;
    word-break: break-word;
}

.bot-row .chat-msg-bubble {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-top-left-radius: 4px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.user-row .chat-msg-bubble {
    background: linear-gradient(135deg, #7b1113, #94171a);
    color: #ffffff;
    border-top-right-radius: 4px;
    box-shadow: 0 2px 8px rgba(123, 17, 19, 0.2);
}

.chat-msg-time {
    font-size: 0.65rem;
    color: #94a3b8;
    margin-top: 4px;
    padding: 0 4px;
}

.user-row .chat-msg-time {
    text-align: right;
}

/* Action button tags */
.chat-actions-container {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 10px;
    padding-top: 8px;
    border-top: 1px dashed #e2e8f0;
}

.chat-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    background: #fffbeb;
    border: 1px solid #fde68a;
    color: #92400e;
    font-size: 0.74rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s ease;
}

.chat-action-btn:hover {
    background: #c9a86c;
    color: #ffffff;
    border-color: #c9a86c;
    transform: translateY(-1px);
}

/* Typing Indicator */
.typing-bubble {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 10px 14px;
}

.dot-bounce {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #7b1113;
    animation: dotBounce 1.4s infinite ease-in-out both;
}

.dot-bounce:nth-child(1) { animation-delay: -0.32s; }
.dot-bounce:nth-child(2) { animation-delay: -0.16s; }

@keyframes dotBounce {
    0%, 80%, 100% { transform: scale(0); opacity: 0.4; }
    40% { transform: scale(1); opacity: 1; }
}

/* Footer Input Area */
.charter-bot-footer {
    padding: 10px 14px 12px;
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
}

.charter-input-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    border-radius: 25px;
    padding: 4px 6px 4px 14px;
    transition: all 0.2s ease;
}

.charter-input-wrapper:focus-within {
    background: #ffffff;
    border-color: #7b1113;
    box-shadow: 0 0 0 3px rgba(123, 17, 19, 0.1);
}

#charterChatInput {
    flex: 1;
    border: none;
    background: transparent;
    font-size: 0.85rem;
    color: #0f172a;
    outline: none;
    padding: 6px 0;
}

.btn-chat-send {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #7b1113, #94171a);
    color: #ffffff;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.88rem;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.btn-chat-send:hover {
    transform: scale(1.06);
    box-shadow: 0 2px 8px rgba(123, 17, 19, 0.3);
}

.charter-footer-meta {
    margin-top: 6px;
    text-align: center;
    font-size: 0.65rem;
    color: #94a3b8;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .charter-bot-container {
        bottom: 18px !important;
        right: 14px !important;
    }
    .launcher-avatar-ring,
    .launcher-text-block {
        display: none !important;
    }
    .launcher-robot-icon-mobile {
        display: block !important;
        font-size: 1rem !important;
        color: #ffffff !important;
    }
    .launcher-status-dot-mobile {
        display: block !important;
        position: absolute !important;
        bottom: 1px !important;
        right: 1px !important;
        width: 7px !important;
        height: 7px !important;
        border-radius: 50% !important;
        background: #10b981 !important;
        border: 1.5px solid #ffffff !important;
    }
    .charter-bot-launcher {
        width: 36px !important;
        height: 36px !important;
        padding: 0 !important;
        border-radius: 50% !important;
        justify-content: center !important;
    }
    .charter-bot-window {
        right: 8px !important;
        bottom: 14px !important;
        width: calc(100vw - 16px) !important;
        height: min(560px, calc(100vh - 28px)) !important;
        max-height: calc(100vh - 28px) !important;
        border-radius: 16px;
    }
    .charter-bot-header {
        padding: 10px 14px !important;
    }
    .charter-bot-title {
        font-size: 0.88rem !important;
    }
    .charter-bot-sub {
        font-size: 0.65rem !important;
    }
    .bot-bubble, .user-bubble {
        font-size: 0.78rem !important;
        line-height: 1.4 !important;
        padding: 8px 12px !important;
    }
    .quick-topic-btn {
        font-size: 0.68rem !important;
        padding: 4px 8px !important;
    }
    .charter-chat-input {
        height: 36px !important;
        font-size: 0.78rem !important;
        padding: 0 10px !important;
    }
    .charter-send-btn {
        width: 32px !important;
        height: 32px !important;
        font-size: 0.75rem !important;
    }
}
</style>

<!-- ========================================================
     CHATBOT CLIENT JAVASCRIPT CONTROLLER
     ======================================================== -->
<script>
(function() {
    const launcher = document.getElementById('charterBotLauncher');
    const chatWindow = document.getElementById('charterBotWindow');
    const botRoot = document.getElementById('charterChatbotRoot');
    const closeBtn = document.getElementById('charterBotCloseBtn');
    const resetBtn = document.getElementById('charterBotResetBtn');
    const form = document.getElementById('charterChatForm');
    const input = document.getElementById('charterChatInput');
    const stream = document.getElementById('charterMessagesStream');
    const typing = document.getElementById('charterTypingIndicator');
    const quickTopics = document.getElementById('charterQuickTopics');

    if (!launcher || !chatWindow || !form) return;

    let conversationHistory = [];
    const STORAGE_KEY = 'pkh_charter_chat_history_v1';
    const OPEN_STATE_KEY = 'pkh_charter_chat_is_open';
    // Kalinga AI Animation Controller (Appearing & Vanishing Cycles)
    let vanishTimer = null;
    let reappearTimer = null;
    let isWindowOpen = false;

    // Remove legacy pinned keys from previous sessions
    try {
        sessionStorage.removeItem('pkh_kalinga_ai_pinned_v1');
    } catch(e) {}

    const DISPLAY_DURATION = 4200; // Visible for 4.2 seconds
    const REST_DURATION = 3800;    // Vanished / resting for 3.8 seconds

    function appearKalingaAI() {
        if (!botRoot || isWindowOpen) return;
        
        botRoot.classList.remove('kai-vanished');
        botRoot.classList.add('kai-visible');

        // Schedule vanishing after DISPLAY_DURATION
        if (vanishTimer) clearTimeout(vanishTimer);
        vanishTimer = setTimeout(() => {
            vanishKalingaAI();
        }, DISPLAY_DURATION);
    }

    function vanishKalingaAI() {
        if (!botRoot || isWindowOpen) return;

        botRoot.classList.remove('kai-visible');
        botRoot.classList.add('kai-vanished');

        // Schedule reappearing after REST_DURATION
        if (reappearTimer) clearTimeout(reappearTimer);
        reappearTimer = setTimeout(() => {
            appearKalingaAI();
        }, REST_DURATION);
    }

    // Hover pauses vanish countdown; leaving resumes it
    if (botRoot) {
        botRoot.addEventListener('mouseenter', () => {
            if (isWindowOpen) return;
            if (vanishTimer) {
                clearTimeout(vanishTimer);
                vanishTimer = null;
            }
        });
        botRoot.addEventListener('mouseleave', () => {
            if (isWindowOpen) return;
            if (botRoot.classList.contains('kai-visible')) {
                if (vanishTimer) clearTimeout(vanishTimer);
                vanishTimer = setTimeout(() => {
                    vanishKalingaAI();
                }, 2800);
            }
        });
    }

    // 1. Restore previous session messages
    try {
        const saved = sessionStorage.getItem(STORAGE_KEY);
        if (saved) {
            const parsed = jsonSafeParse(saved);
            if (Array.isArray(parsed) && parsed.length > 0) {
                conversationHistory = parsed;
                parsed.forEach(turn => {
                    renderBubble(turn.role === 'user' ? 'user' : 'bot', turn.content, turn.actions || []);
                });
            }
        }
    } catch(e) {}

    // 2. Toggle Window Open / Close
    function toggleChat(forceOpen = null) {
        const isOpen = (forceOpen !== null) ? forceOpen : (chatWindow.style.display === 'none');
        isWindowOpen = isOpen;

        if (isOpen) {
            if (vanishTimer) { clearTimeout(vanishTimer); vanishTimer = null; }
            if (reappearTimer) { clearTimeout(reappearTimer); reappearTimer = null; }
            botRoot.classList.remove('kai-vanished', 'kai-visible');
            botRoot.classList.add('kai-open');

            chatWindow.style.display = 'flex';
            try { sessionStorage.setItem(OPEN_STATE_KEY, 'true'); } catch(e) {}
            scrollBottom();
            setTimeout(() => input.focus(), 150);
        } else {
            chatWindow.style.display = 'none';
            try { sessionStorage.setItem(OPEN_STATE_KEY, 'false'); } catch(e) {}
            botRoot.classList.remove('kai-open');
            botRoot.classList.add('kai-visible');

            // Resume appear/vanish cycle: wait 2.8s before vanishing
            if (vanishTimer) clearTimeout(vanishTimer);
            vanishTimer = setTimeout(() => {
                vanishKalingaAI();
            }, 2800);
        }
    }

    launcher.addEventListener('click', () => {
        toggleChat();
    });
    closeBtn?.addEventListener('click', () => toggleChat(false));

    // Initial appearance sequence
    let wasOpen = false;
    try {
        wasOpen = (sessionStorage.getItem(OPEN_STATE_KEY) === 'true');
    } catch(e) {}

    if (wasOpen) {
        toggleChat(true);
    } else {
        setTimeout(() => {
            appearKalingaAI();
        }, 1200);
    }

    // 3. Reset / Clear Chat
    resetBtn?.addEventListener('click', () => {
        if (confirm("Nais niyo po bang simulan muli ang usapan?")) {
            conversationHistory = [];
            sessionStorage.removeItem(STORAGE_KEY);
            // Keep only the initial welcome message and typing indicator
            const initial = stream.children[0];
            stream.innerHTML = '';
            if (initial) stream.appendChild(initial);
            if (typing) stream.appendChild(typing);
        }
    });

    // 4. Quick Topic Chips
    quickTopics?.addEventListener('click', (e) => {
        const chip = e.target.closest('.quick-topic-chip');
        if (chip) {
            const query = chip.getAttribute('data-query');
            if (query) {
                handleUserSend(query);
            }
        }
    });

    // 5. Submit Message Form
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const msg = input.value.trim();
        if (msg) {
            handleUserSend(msg);
            input.value = '';
        }
    });

    // 6. Send & Fetch AI Response
    async function handleUserSend(text) {
        // Render user message bubble
        renderBubble('user', text);
        conversationHistory.push({ role: 'user', content: text });
        saveSession();
        scrollBottom();

        // Show typing dots
        if (typing) {
            typing.style.display = 'flex';
            stream.appendChild(typing);
            scrollBottom();
        }

        try {
            const res = await fetch('chatbot-ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    message: text,
                    history: conversationHistory
                })
            });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();

            if (typing) typing.style.display = 'none';

            if (data.success && data.reply) {
                renderBubble('bot', data.reply, data.actions || []);
                conversationHistory.push({
                    role: 'model',
                    content: data.reply,
                    actions: data.actions || []
                });
                saveSession();

                // Dynamically update quick topic chips if suggestions returned
                if (Array.isArray(data.suggestions) && data.suggestions.length > 0) {
                    renderQuickSuggestions(data.suggestions);
                }
            } else {
                renderBubble('bot', data.error || "Paumanhin po, nagkaroon ng pansamantalang aberya. Maaari po bang subukang magtanong muli?");
            }
        } catch (err) {
            if (typing) typing.style.display = 'none';
            renderBubble('bot', "Paumanhin po, hindi maabot ang server sa kasalukuyan. Pakitiyak ang inyong koneksyon at sumubok muli.");
        }

        scrollBottom();
    }

    // 7. Render Bubble Helper
    function renderBubble(sender, text, actions = []) {
        const row = document.createElement('div');
        row.className = `chat-message-row ${sender === 'user' ? 'user-row' : 'bot-row'}`;

        const avatar = document.createElement('div');
        avatar.className = 'chat-msg-avatar';
        avatar.innerHTML = sender === 'user' ? '<i class="fas fa-user"></i>' : '<i class="fas fa-robot"></i>';

        const content = document.createElement('div');
        content.className = 'chat-msg-content';

        const bubble = document.createElement('div');
        bubble.className = 'chat-msg-bubble';

        // Format markdown styling (bold, bullets, line breaks)
        let formatted = escapeHtml(text)
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/\n/g, '<br>');

        bubble.innerHTML = formatted;

        // Render Action Buttons if available
        if (actions && actions.length > 0) {
            const actionWrap = document.createElement('div');
            actionWrap.className = 'chat-actions-container';
            actions.forEach(act => {
                const a = document.createElement('a');
                a.className = 'chat-action-btn';
                a.href = act.url;
                a.innerHTML = `<i class="${act.icon || 'fas fa-arrow-right'}"></i> ${escapeHtml(act.label)}`;
                actionWrap.appendChild(a);
            });
            bubble.appendChild(actionWrap);
        }

        const time = document.createElement('span');
        time.className = 'chat-msg-time';
        const now = new Date();
        const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        time.textContent = (sender === 'user' ? 'Kayo' : 'Lingkod Kalinga AI') + ' · ' + timeStr;

        content.appendChild(bubble);
        content.appendChild(time);

        row.appendChild(avatar);
        row.appendChild(content);

        if (typing && typing.parentNode === stream) {
            stream.insertBefore(row, typing);
        } else {
            stream.appendChild(row);
        }
    }

    // 8. Render Dynamic Quick Suggestion Chips
    function renderQuickSuggestions(chips) {
        if (!quickTopics) return;
        const track = quickTopics.querySelector('.quick-topics-track');
        if (!track) return;
        track.innerHTML = '';
        chips.forEach(q => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'quick-topic-chip';
            btn.setAttribute('data-query', q);
            btn.innerHTML = `<i class="fas fa-comment-dots"></i> ${escapeHtml(q)}`;
            track.appendChild(btn);
        });
    }

    function scrollBottom() {
        setTimeout(() => {
            stream.scrollTop = stream.scrollHeight;
        }, 50);
    }

    function saveSession() {
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(conversationHistory.slice(-20)));
        } catch(e) {}
    }

    function jsonSafeParse(str) {
        try { return JSON.parse(str); } catch(e) { return null; }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();
</script>
