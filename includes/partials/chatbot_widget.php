<?php
/** LemboBot floating widget. The API key never reaches the browser — the widget only talks to api/chatbot.php. */
$botUser = current_user();
$botSuggestions = match ($botUser['role'] ?? 'guest') {
    'organizer' => ['How do I create an event?', 'How does QR check-in work?', 'How are my ticket sales going?', 'How do I add a VIP ticket type?'],
    'admin' => ['Give me a platform overview', 'How do I approve events?', 'What does suspending a user do?'],
    'attendee' => ['What events are happening in Prishtina?', 'Find me a tech event', 'What are my upcoming events?', 'How do I cancel a booking?'],
    default => ['What events are happening in Prishtina?', 'Find me a tech event', 'How do I buy a ticket?', 'VIP vs General Admission?', 'How can I create an event?'],
};
?>
<div class="lembobot" id="lembobot" data-api="<?= e(url('api/chatbot.php')) ?>">
    <button type="button" class="lembobot-fab" id="lembobotToggle" aria-label="Open LemboBot assistant" aria-expanded="false" aria-controls="lembobotPanel">
        <span class="fab-icon fab-open"><?= icon('bot') ?></span>
        <span class="fab-icon fab-close"><?= icon('close') ?></span>
        <span class="fab-pulse" aria-hidden="true"></span>
    </button>
    <section class="lembobot-panel" id="lembobotPanel" role="dialog" aria-modal="false" aria-labelledby="lembobotTitle" hidden>
        <header class="lembobot-head">
            <div class="lembobot-avatar" aria-hidden="true"><?= icon('bot') ?></div>
            <div class="lembobot-title">
                <strong id="lembobotTitle">LemboBot</strong>
                <span class="lembobot-status" data-bot-status><i></i><span>Connecting…</span></span>
            </div>
            <button type="button" class="icon-btn" data-bot-clear aria-label="Clear conversation" title="Clear conversation"><?= icon('refresh') ?></button>
            <button type="button" class="icon-btn" data-bot-close aria-label="Close assistant"><?= icon('close') ?></button>
        </header>
        <div class="lembobot-messages" data-bot-messages aria-live="polite">
            <div class="bot-msg bot">
                <div class="bot-bubble">
                    <p>Hi<?= $botUser ? ' ' . e($botUser['first_name']) : '' ?>! I'm <strong>LemboBot</strong> 🎟️ I can find events, explain tickets and walk you through LemboEvents. What are you looking for?</p>
                </div>
            </div>
        </div>
        <div class="lembobot-suggestions" data-bot-suggestions>
            <?php foreach ($botSuggestions as $s): ?>
                <button type="button" class="chip" data-bot-suggest><?= e($s) ?></button>
            <?php endforeach; ?>
        </div>
        <form class="lembobot-input" data-bot-form>
            <label class="sr-only" for="lembobotInput">Message LemboBot</label>
            <textarea id="lembobotInput" rows="1" maxlength="500" placeholder="Ask about events, tickets…" data-bot-input></textarea>
            <button type="submit" class="send-btn" aria-label="Send message" data-bot-send><?= icon('send') ?></button>
        </form>
        <p class="lembobot-note">AI answers can be imperfect. Never share passwords or card details.</p>
    </section>
</div>
