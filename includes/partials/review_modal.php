<?php /* Review modal — opened by [data-modal-open="reviewModal"][data-review-event][data-review-title]. Posts to api/reviews.php. */ ?>
<div class="modal" id="reviewModal" hidden>
    <div class="modal-backdrop"></div>
    <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="reviewModalTitle">
        <form data-review-form>
            <div class="modal-head">
                <h2 id="reviewModalTitle">Rate your experience</h2>
                <button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('close') ?></button>
            </div>
            <div class="modal-body form-grid">
                <p class="muted mb-0" data-review-event-name></p>
                <input type="hidden" name="event_id" value="">
                <div class="field">
                    <span class="label">Your rating <span class="req">*</span></span>
                    <div class="star-input" role="radiogroup" aria-label="Rating">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" id="rate<?= $i ?>" name="rating" value="<?= $i ?>">
                            <label for="rate<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>"><?= icon('star') ?><span class="sr-only"><?= $i ?> stars</span></label>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="field">
                    <label for="reviewTitle">Headline</label>
                    <input class="input" id="reviewTitle" name="title" maxlength="120" placeholder="Sum it up in a few words">
                </div>
                <div class="field">
                    <label for="reviewComment">Your review <span class="req">*</span></label>
                    <textarea class="textarea" id="reviewComment" name="comment" minlength="10" maxlength="2000" required placeholder="What did you love? What could be better?"></textarea>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-soft" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Publish review</button>
            </div>
        </form>
    </div>
</div>
