<?php
/**
 * Ask Me Help - Real AI Floating Assistant
 * Floating component fixed at bottom-right of every page
 */
?>
<!-- Ask Me Help Floating Chatbot Widget -->
<div class="askme-container" id="askMeContainer">
    <!-- Floating Action Button (FAB) -->
    <button type="button" class="askme-fab" id="askMeFab" onclick="toggleAskMeChat()" aria-label="Open Ask Me Help Assistant">
        <span class="askme-fab-sparkle">✨</span>
        <div class="askme-fab-icon">
            <i class="fa-solid fa-comment-dots"></i>
        </div>
        <span class="askme-fab-label">Ask Me Help</span>
        <span class="askme-notif-dot" title="AI Ready"></span>
        <span class="askme-pulse-ring"></span>
    </button>

    <!-- Modern Fintech AI Floating Chat Window -->
    <div class="askme-chat-window" id="askMeChatWindow" role="dialog" aria-labelledby="askMeTitle">
        <!-- Chat Header -->
        <div class="askme-header">
            <div class="askme-header-left">
                <div class="askme-avatar">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </div>
                <div>
                    <div class="askme-brand-tag">✨ ExpenseIQ AI</div>
                    <h4 class="askme-title" id="askMeTitle">Ask Me Help</h4>
                    <div class="askme-status">
                        <span class="askme-status-dot"></span>
                        <span>Online &bull; Real AI Assistant</span>
                    </div>
                </div>
            </div>
            <div class="askme-header-actions">
                <button type="button" class="askme-header-btn" id="askMeMinimizeBtn" title="Minimize Chat" aria-label="Minimize" onclick="toggleAskMeChat()">
                    <i class="fa-solid fa-minus"></i>
                </button>
                <button type="button" class="askme-header-btn" id="askMeCloseBtn" title="Close Chat" aria-label="Close" onclick="closeAskMeChat()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <!-- Optional Quick Topic Pills (shortcuts) -->
        <div class="askme-faq-strip">
            <div class="askme-faq-pills">
                <button type="button" class="askme-faq-pill" onclick="sendQuickQuestion('How much did I spend this month?')">Monthly Spending</button>
                <button type="button" class="askme-faq-pill" onclick="sendQuickQuestion('How much did I spend on Food?')">Food Expenses</button>
                <button type="button" class="askme-faq-pill" onclick="sendQuickQuestion('What is my balance?')">Available Balance</button>
                <button type="button" class="askme-faq-pill" onclick="sendQuickQuestion('How much is left in my Food budget?')">Food Budget</button>
                <button type="button" class="askme-faq-pill" onclick="sendQuickQuestion('What is my financial health score?')">Health Score</button>
                <button type="button" class="askme-faq-pill" onclick="sendQuickQuestion('How do I add an expense?')">Add Expense Guide</button>
            </div>
        </div>

        <!-- Messages Thread -->
        <div class="askme-messages" id="askMeMessages">
            <!-- Initial AI Greeting -->
            <div class="askme-msg askme-msg-bot">
                <div class="askme-msg-avatar">
                    <i class="fa-solid fa-robot"></i>
                </div>
                <div>
                    <div class="askme-msg-bubble">
                        Hello! 👋 I am <strong>Ask Me Help</strong>, your ExpenseIQ AI Assistant.<br><br>
                        Ask me anything about your balance, expenses, budgets, savings goals, or financial health!
                    </div>
                    <div class="askme-msg-time" id="greetingTime">Just now</div>
                </div>
            </div>
        </div>

        <!-- Chat Input Footer -->
        <form class="askme-footer" id="askMeForm" onsubmit="handleChatSubmit(event)">
            <input type="text" class="askme-input" id="askMeInput" placeholder="Ask me anything..." autocomplete="off" required>
            <button type="submit" class="askme-send-btn" id="askMeSendBtn" aria-label="Send Question" title="Send Question (Enter)">
                <i class="fa-solid fa-paper-plane"></i>
            </button>
        </form>
    </div>
</div>

<script src="assets/js/chatbot.js"></script>
