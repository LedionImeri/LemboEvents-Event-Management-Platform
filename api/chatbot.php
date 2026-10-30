<?php
/**
 * LemboBot endpoint. Browser → PHP (this file) → Groq → PHP → browser.
 * The Groq API key never leaves the server.
 *
 * GET  api/chatbot.php?action=status    → { enabled, online, label }
 * GET  api/chatbot.php?action=history   → { messages: [...] }
 * POST api/chatbot.php { action: "send", message }
 * POST api/chatbot.php { action: "clear" }
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/LemboBot.php';

$bot = new LemboBot(current_user(), session_id(), client_ip());

if (!is_post()) {
    $action = $_GET['action'] ?? 'status';
    if ($action === 'history') {
        json_response(['ok' => true, 'messages' => $bot->history(30), 'status' => LemboBot::status()]);
    }
    json_response(['ok' => true] + LemboBot::status());
}

csrf_verify();
$data = request_data();
$action = (string) ($data['action'] ?? 'send');

if ($action === 'clear') {
    $bot->clearHistory();
    json_response(['ok' => true]);
}

if ($action !== 'send') {
    json_error('Unknown action.', 422);
}
$message = $data['message'] ?? '';
if (!is_string($message)) {
    json_error('Invalid message.', 422);
}
if ($limit = $bot->rateLimitError()) {
    json_error($limit, 429);
}
try {
    $result = $bot->handle($message);
} catch (InvalidArgumentException $e) {
    json_error($e->getMessage(), 422);
}
json_response(['ok' => true] + $result);
