<?php
// includes/footer.php

// Auto-path logic for pages inside /admin
$basePath = '';
if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
    $basePath = '../';
}
?>
<!-- Chatbot Widget HTML -->
<link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/chatbot.css">

<div id="chat-toggle" class="chat-toggle">💬</div>

<div id="chat-box" class="chat-box">
    <!-- Header with avatar -->
    <div class="chat-header">
        <img src="<?php echo $basePath; ?>assets/img/AIRA1.png" class="chat-avatar" alt="AIRA">

        <div class="chat-info">
            <div class="chat-name">AIRA</div>
            <div class="chat-status" id="chat-status">Online</div>
        </div>

        <!-- AI Switch (toggle between Ollama & Gemini) -->
        <div class="ai-switch">
            <label class="switch" title="Switch between Ollama (offline) and Gemini (cloud)">
                <input type="checkbox" id="provider-toggle">
                <span class="slider"></span>
            </label>
            <span id="provider-label" class="provider-label">Gemini</span>
        </div>

        <!-- Close X -->
        <span id="chat-close" class="chat-close">✕</span>
    </div>

    <div id="chat-log" class="chat-log"></div>

    <div id="typing-indicator" class="typing-indicator" style="display:none;">
        <div class="dot"></div><div class="dot"></div><div class="dot"></div>
    </div>

    <div class="input-row">
        <input id="chat-input" type="text" class="chat-input" placeholder="Type a message...">
        <button id="send-btn" class="send-btn">➤</button>
    </div>

    <div id="chat-suggestions"></div>
</div>

<script src="<?php echo $basePath; ?>assets/js/chatbot.js"></script>
</body>
</html>
