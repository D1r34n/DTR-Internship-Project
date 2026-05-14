// assets/js/chatbot.js
document.addEventListener("DOMContentLoaded", () => {
  const chatToggle      = document.getElementById("chat-toggle");
  const chatBox         = document.getElementById("chat-box");
  const chatClose       = document.getElementById("chat-close");
  const chatStatus      = document.getElementById("chat-status");
  const chatLog         = document.getElementById("chat-log");
  const chatInput       = document.getElementById("chat-input");
  const sendBtn         = document.getElementById("send-btn");
  const typingIndicator = document.getElementById("typing-indicator");
  const suggestionsBar  = document.getElementById("chat-suggestions");

  // Toggle + label
  const providerToggle  = document.getElementById("provider-toggle");
  const providerLabel   = document.getElementById("provider-label");

  if (!chatToggle) return;

  // Default provider (load from localStorage)
  let provider = localStorage.getItem("aiProvider") || "gemini";

  // Sync toggle + label with stored value
  function updateProviderUI() {
    if (!providerToggle || !providerLabel) return;

    if (provider === "gemini") {
      providerToggle.checked = true;
      providerLabel.textContent = "Gemini";
    } else {
      providerToggle.checked = false;
      providerLabel.textContent = "Ollama";
    }
  }
  updateProviderUI();

  // Handle toggle change
  if (providerToggle) {
    providerToggle.addEventListener("change", () => {
      provider = providerToggle.checked ? "gemini" : "ollama";
      localStorage.setItem("aiProvider", provider);
      updateProviderUI();
      addMessage("bot", `✅ Switched to ${provider === "gemini" ? "Gemini AI (cloud)" : "Ollama AI (offline)"}.`);
    });
  }

  // Status text
  function setStatus(text) {
    if (chatStatus) {
      chatStatus.textContent = text;
    }
  }

  // Close chat on "x"
  if (chatClose) {
    chatClose.addEventListener("click", () => {
      chatBox.style.display = "none";
      setStatus("Online");
    });
  }

  let welcomeShown = false;

  // Chatbox toggle
  chatToggle.addEventListener("click", () => {
    const open = chatBox.style.display === "flex";
    chatBox.style.display = open ? "none" : "flex";
    if (!open && !welcomeShown) {
      addMessage("bot", "Hello! I'm AIRA. How can I assist you today?");
      // Show provider info
      addMessage("bot", `🤖 Currently using ${provider === "gemini" ? "Gemini AI (cloud)" : "Ollama AI (offline)"}.`);
      welcomeShown = true;
    }
  });

  sendBtn.addEventListener("click", sendMessage);
  chatInput.addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
      sendMessage();
    }
  });

  function sendMessage() {
    const msg = chatInput.value.trim();
    if (!msg) return;

    addMessage("user", msg);
    clearSuggestions();
    chatInput.value = "";
    showTypingIndicator();
    setStatus("Typing...");

    // Otherwise call API
    fetch(getApiPath(), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ message: msg, provider })
    })
      .then(res => res.text())
      .then(text => {
        hideTypingIndicator();
        setStatus("Online");

        let reply = "";
        try {
          const data = JSON.parse(text);
          reply =
            (data.choices?.[0]?.message?.content || "").trim() ||
            data.reply ||
            "No reply";
        } catch {
          reply = text;
        }

        // Handle system command shortcuts
        if (reply === "[clear_chat]") {
          chatLog.innerHTML = "";
          return;
        }
        if (reply === "[redirect_manage]") {
          addMessage("bot", "Opening Manage Employee page...");
          window.location.href = (window.location.pathname.includes("/admin/") ? "manage_employee.php" : "admin/manage_employee.php");
          return;
        }
        if (reply === "[redirect_dashboard]") {
          addMessage("bot", "Opening Dashboard...");
          window.location.href = (window.location.pathname.includes("/admin/") ? "dashboard.php" : "admin/dashboard.php");
          return;
        }
        if (reply === "[logout]") {
          window.location.href = "logout.php";
          return;
        }
        if (reply === "[redirect_add]") {
          addMessage("bot", "Opening Add Employee page...");
          window.location.href = (window.location.pathname.includes("/admin") ? "add_employee.php" : "admin/add_employee.php");
          return;
        }
        if (reply.startsWith("[redirect_view_employee?id=")) {
          addMessage("bot", "Opening employee profile...");
          // Example: [redirect_view_employee?id=5]
          const url = (window.location.pathname.includes("/admin/") 
                       ? "view_employee.php" 
                       : "admin/view_employee.php") 
                       + reply.replace("[redirect_view_employee", "").replace("]", "");
          window.location.href = url;
          return;
        }

        // Suggestion extraction
        let suggestion = null;
        const match = reply.match(/\(Suggestion:\s*(.+?)\)$/i);
        if (match) {
          suggestion = match[1].trim();
          reply = reply.replace(/\(Suggestion:.+?\)$/, "").trim();
        }

        addMessage("bot", reply);

        if (suggestion) {
          addSuggestionButton(suggestion);
        }
      })
      .catch(() => {
        hideTypingIndicator();
        setStatus("Online");
        addMessage("bot", "Error contacting AI");
      });
  }

  function addMessage(who, text) {
    const msgDiv = document.createElement("div");
    msgDiv.className = "message " + (who === "user" ? "user" : "bot");
    msgDiv.textContent = text;
    chatLog.appendChild(msgDiv);
    chatLog.scrollTop = chatLog.scrollHeight;
  }

  function showTypingIndicator() {
    typingIndicator.style.display = "block";
  }
  function hideTypingIndicator() {
    typingIndicator.style.display = "none";
  }

  function getApiPath() {
    return window.location.pathname.includes("/admin/") ? "../api/chatbot_ai.php" : "api/chatbot_ai.php";
  }

  function addSuggestionButton(label) {
    const btn = document.createElement("button");
    btn.className = "suggestion-btn";
    btn.textContent = label;
    btn.addEventListener("click", () => {
      chatInput.value = label;
      sendMessage();
    });
    suggestionsBar.appendChild(btn);
  }

  function clearSuggestions() {
    suggestionsBar.innerHTML = "";
  }
});
