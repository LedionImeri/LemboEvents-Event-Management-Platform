<?php
/**
 * LemboBot configuration.
 *
 * SECURITY: no API key is ever written in this file. GROQ_API_KEY is read from
 * config/local.php (git-ignored) or the server environment. The browser never sees it:
 * the widget only talks to api/chatbot.php, which calls Groq from PHP.
 *
 *   AI_PROVIDER = 'groq' when GROQ_API_KEY is set
 *   AI_PROVIDER = 'none' otherwise — LemboBot still works with rule-based answers + live event search
 */
$__groqKey = local_setting('GROQ_API_KEY', '', true);
define('AI_PROVIDER', $__groqKey !== '' ? 'groq' : 'none');

// Groq exposes an OpenAI-compatible Chat Completions API.
define('GROQ_DEFAULT_MODEL', 'openai/gpt-oss-120b');   // alternatives: openai/gpt-oss-20b, llama-3.3-70b-versatile
define('GROQ_API_KEY', $__groqKey);
$__model = local_setting('GROQ_MODEL', '', true);
define('GROQ_MODEL', preg_match('#^[a-z0-9][a-z0-9._/\-]{2,80}$#i', $__model) ? $__model : GROQ_DEFAULT_MODEL);
define('GROQ_API_BASE', 'https://api.groq.com/openai/v1');
define('GROQ_MAX_TOKENS', 900);
define('GROQ_TEMPERATURE', 0.4);
define('GROQ_REASONING_EFFORT', 'low');   // only sent to reasoning models (gpt-oss, qwen)
unset($__groqKey, $__model);

// Conversation
define('CHATBOT_NAME', 'LemboBot');
define('CHATBOT_MAX_MESSAGE_LENGTH', 500);
define('CHATBOT_HISTORY_LIMIT', 8);        // previous messages sent to the model
define('CHATBOT_MAX_EVENTS', 5);           // events included in an answer
define('CHATBOT_TIMEOUT', 20);             // seconds for the Groq request

// Rate limiting (per logged-in user, or per session + IP for guests)
define('CHATBOT_RATE_PER_MINUTE', 8);
define('CHATBOT_RATE_PER_DAY', 120);
define('CHATBOT_MIN_INTERVAL', 2);         // seconds between two messages

// Optional CA bundle for Windows/XAMPP cURL setups ("SSL certificate problem")
define('CHATBOT_CA_BUNDLE', local_setting('CHATBOT_CA_BUNDLE', '', true));
