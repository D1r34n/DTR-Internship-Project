<?php
/**
 * Hybrid Chatbot (Rule-based + Ollama/Gemini AI fallback + Memory + Suggestions)
 * Configurable via frontend toggle: "gemini" | "ollama" | "none"
 * Endpoint: POST JSON { "message": "...", "provider": "gemini|ollama|none" }
 * Response: { "choices": [ { "message": { "content": "..." } } ] }
 */

header("Content-Type: application/json");
session_start();

include_once __DIR__ . '/../includes/db.php'; // provides $conn (mysqli)

// ====================== CONFIG ======================
$AI_PROVIDER   = "gemini"; // default provider (can be overridden by frontend)
$GEMINI_API_KEY = "AIzaSyBwDKV50DVt872KeCG5vxPI_o1FRLAPVtA"; // replace with your Gemini API key
// ====================================================

// ---------- Provider Check (manual ping) ----------
if (isset($_GET['provider'])) {
    echo json_encode(["provider" => strtoupper($AI_PROVIDER)]);
    exit;
}

// ---------- Read POST ----------
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);
$userMessageRaw = $data["message"] ?? "";
$userMessage    = strtolower(trim($userMessageRaw));

// ✅ Allow frontend toggle to override provider
if (!empty($data["provider"])) {
    $AI_PROVIDER = strtolower(trim($data["provider"]));
}

if ($userMessage === "") {
    echo json_encode(["choices" => [["message" => ["content" => "No message provided."]]]]);
    exit;
}

// ---------- Helpers ----------
function send($text) {
    echo json_encode([
        "choices" => [
            ["message" => ["content" => $text]]
        ]
    ]);
    exit;
}

// ---------- AI Functions ----------
function askOllama($userMessage, $systemHints = "") {
    $url = "http://localhost:11434/api/generate";

    $prompt = <<<PROMPT
You are AIRA, a helpful and knowledgeable assistant.
- You can answer any type of question (general knowledge, personal help, coding, advice, explanations, etc.).
- If the user specifically asks for an Employee 201 Management System command (like "open dashboard", "count employees", "show latest logs"), respond with the special command only (e.g., [redirect_dashboard]).
- Otherwise, act like a normal AI assistant: be helpful, clear, and friendly.
- If your reply relates to system features, you may also add a short suggestion in parentheses (e.g., (Suggestion: show latest logs)).

Here are some app commands the system understands:
- "count employees", "count male", "count female", "count admins"
- "hired this month"
- "get employee {id}"
- "list employees", "latest employees"
- "show latest logs", "recent activities"
- navigation: "open dashboard", "open manage", "open add"
- utility: "logout", "system info", "show my role", "clear chat"

$systemHints

User: {$userMessage}
Assistant:
PROMPT;

    $payload = json_encode([
        "model"  => "gemma3:1b",
        "prompt" => $prompt,
        "stream" => false
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 60
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || !$response) {
        return "I couldn't reach the Ollama service right now. Please check if Ollama is running.";
    }

    $result = json_decode($response, true);

    if (!$result || !isset($result["response"])) {
        return "No response from Ollama. (Raw: $response)";
    }

    return trim($result["response"]);
}

function askGemini($userMessage, $systemHints = "", $apiKey = "") {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . urlencode($apiKey);

    $prompt = <<<PROMPT
You are AIRA, a helpful and knowledgeable assistant.
- You can answer any type of question (general knowledge, personal help, coding, advice, explanations, etc.).
- If the user specifically asks for an Employee 201 Management System command (like "open dashboard", "count employees", "show latest logs"), respond with the special command only (e.g., [redirect_dashboard]).
- Otherwise, act like a normal AI assistant: be helpful, clear, and friendly.
- If your reply relates to system features, you may also add a short suggestion in parentheses (e.g., (Suggestion: show latest logs)).

Here are some app commands the system understands:
- "count employees", "count male", "count female", "count admins"
- "hired this month"
- "get employee {id}"
- "list employees", "latest employees"
- "show latest logs", "recent activities"
- navigation: "open dashboard", "open manage", "open add"
- utility: "logout", "system info", "show my role", "clear chat"

$systemHints

User: {$userMessage}
Assistant:
PROMPT;

    $payload = json_encode([
        "contents" => [[
            "parts" => [["text" => $prompt]]
        ]]
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CAINFO => "C:/wamp64/bin/php/php8.3.14/extras/ssl/cacert.pem"
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || !$response) {
        return "cURL Error: " . $err;
    }

    $result = json_decode($response, true);

    if (!$result || empty($result["candidates"][0]["content"]["parts"][0]["text"])) {
        return "No response from Gemini. (Raw: $response)";
    }

    return trim($result["candidates"][0]["content"]["parts"][0]["text"]);
}

// ---------- MEMORY CAPTURE ----------
if (preg_match('/my name is (.+)/i', $userMessageRaw, $m)) {
    $_SESSION['chat_user_name'] = trim($m[1]);
    send("Nice to meet you, " . $_SESSION['chat_user_name'] . "!");
}

$systemHints = "";
if (!empty($_SESSION['chat_user_name'])) {
    $systemHints .= "The user's name is " . $_SESSION['chat_user_name'] . ". ";
}
if (!empty($_SESSION['last_employee_data'])) {
    $emp = $_SESSION['last_employee_data'];
    $systemHints .= "The last employee the user asked about was Employee #{$emp['id']} named {$emp['first_name']} {$emp['last_name']}, Position: {$emp['position']}, Department: {$emp['department']}. ";
}

// ---------- RULE-BASED COMMANDS ----------
$role = $_SESSION['admin_role'] ?? 'guest';

if ($userMessage === "system info") {
    send("📊 You are using the Employee 201 Management System. Current AI provider: " . strtoupper($AI_PROVIDER));
}

if ($userMessage === "show my role") {
    send("👤 Your role is: " . ucfirst($role));
}

if ($userMessage === "count employees") {
    $res = $conn->query("SELECT COUNT(*) AS c FROM employees");
    $row = $res->fetch_assoc();
    send("👥 Total employees: " . $row['c']);
}

if ($userMessage === "count male") {
    $res = $conn->query("SELECT COUNT(*) AS c FROM employees WHERE gender = 'Male'");
    $row = $res->fetch_assoc();
    send("👨 Male employees: " . $row['c']);
}

if ($userMessage === "count female") {
    $res = $conn->query("SELECT COUNT(*) AS c FROM employees WHERE gender = 'Female'");
    $row = $res->fetch_assoc();
    send("👩 Female employees: " . $row['c']);
}

if ($userMessage === "count admins") {
    if ($role === "staff") {
        send("⛔ Access denied: Staff cannot view admin count.");
    }
    $res = $conn->query("SELECT COUNT(*) AS c FROM admin");
    $row = $res->fetch_assoc();
    send("🛡️ Total admins: " . $row['c']);
}

if ($userMessage === "hired this month") {
    $res = $conn->query("SELECT COUNT(*) AS c FROM employees WHERE MONTH(date_hired) = MONTH(CURRENT_DATE()) AND YEAR(date_hired) = YEAR(CURRENT_DATE())");
    $row = $res->fetch_assoc();
    send("📅 Employees hired this month: " . $row['c']);
}

// ✅ Rewritten: Get employee by ID
if (preg_match('/^get employee (\d+)/', $userMessage, $m)) {
    $id = (int)$m[1];
    $res = $conn->query("SELECT id, first_name, last_name, position, department FROM employees WHERE id = $id");
    if ($res && $res->num_rows > 0) {
        $emp = $res->fetch_assoc();
        $emp['position']   = $emp['position']   ?? 'N/A';
        $emp['department'] = $emp['department'] ?? 'N/A';
        $_SESSION['last_employee_data'] = $emp;
        send("👤 Employee #{$emp['id']}: {$emp['first_name']} {$emp['last_name']}, Position: {$emp['position']}, Department: {$emp['department']}");
    } else {
        send("❌ Employee not found with ID $id.");
    }
}

// ✅ Rewritten: Search employee by name
if (preg_match('/^(get|search) employee (.+)/i', $userMessageRaw, $m)) {
    $name = $conn->real_escape_string(trim($m[2]));
    $res = $conn->query("SELECT id, first_name, last_name, position, department 
                         FROM employees 
                         WHERE first_name LIKE '%$name%' OR last_name LIKE '%$name%' 
                         LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $emp = $res->fetch_assoc();
        $emp['position']   = $emp['position']   ?? 'N/A';
        $emp['department'] = $emp['department'] ?? 'N/A';
        $_SESSION['last_employee_data'] = $emp;
        send("[redirect_view_employee?id={$emp['id']}]");
    } else {
        send("❌ No employee found with the name \"$name\".");
    }
}

if ($userMessage === "list employees") {
    $res = $conn->query("SELECT id, first_name, last_name FROM employees LIMIT 5");
    $list = [];
    while ($row = $res->fetch_assoc()) {
        $list[] = "#{$row['id']} {$row['first_name']} {$row['last_name']}";
    }
    send("📋 Employees:\n" . implode("\n", $list));
}

if ($userMessage === "latest employees") {
    $res = $conn->query("SELECT id, first_name, last_name, date_hired FROM employees ORDER BY date_hired DESC LIMIT 5");
    $list = [];
    while ($row = $res->fetch_assoc()) {
        $list[] = "#{$row['id']} {$row['first_name']} {$row['last_name']} (Hired: {$row['date_hired']})";
    }
    send("🆕 Latest Employees:\n" . implode("\n", $list));
}

if ($userMessage === "show latest logs" || $userMessage === "recent activities") {
    if ($role === "staff") {
        send("⛔ Access denied: Staff cannot view system logs.");
    }
    $res = $conn->query("SELECT action, log_time FROM logs ORDER BY log_time DESC LIMIT 5");
    $logs = [];
    while ($row = $res->fetch_assoc()) {
        $logs[] = "{$row['log_time']} - {$row['action']}";
    }
    send("📜 Recent Activities:\n" . implode("\n", $logs));
}

// Navigation commands
if ($userMessage === "open dashboard") send("[redirect_dashboard]");
if ($userMessage === "open manage")    send("[redirect_manage]");
if ($userMessage === "open add")       send("[redirect_add]");

// Utility
if ($userMessage === "logout")    send("[logout]");
if ($userMessage === "clear chat") {
    session_destroy();
    send("🧹 Chat history cleared.");
}

// ---------- AI FALLBACK ----------
if ($AI_PROVIDER === "gemini") {
    $aiReply = askGemini($userMessageRaw, $systemHints, $GEMINI_API_KEY);
} elseif ($AI_PROVIDER === "ollama") {
    $aiReply = askOllama($userMessageRaw, $systemHints);
} else {
    $aiReply = "⚡ AI is turned off. You can switch back to Gemini or Ollama.";
}

send($aiReply);
