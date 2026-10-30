<?php /* Report modal — opened by [data-modal-open="reportModal"][data-report-type][data-report-id]. Posts to api/reports.php. */ ?>
<div class="modal" id="reportModal" hidden>
    <div class="modal-backdrop"></div>
    <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="reportModalTitle">
        <form data-report-form>
            <div class="modal-head">
                <h2 id="reportModalTitle">Report content</h2>
                <button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('close') ?></button>
            </div>
            <div class="modal-body form-grid">
                <p class="muted mb-0">Reports are reviewed by the LemboEvents team. Thanks for helping keep the community safe.</p>
                <input type="hidden" name="target_type" value="">
                <input type="hidden" name="target_id" value="">
                <div class="field">
                    <label for="reportReason">Reason</label>
                    <select class="select" id="reportReason" name="reason" required>
                        <option value="spam">Spam or advertising</option>
                        <option value="inappropriate">Inappropriate content</option>
                        <option value="fraud">Fraud or scam</option>
                        <option value="misleading">Misleading information</option>
                        <option value="other">Something else</option>
                    </select>
                </div>
                <div class="field">
                    <label for="reportDetails">Details <span class="muted">(optional)</span></label>
                    <textarea class="textarea" id="reportDetails" name="details" maxlength="1000" placeholder="Tell us what's wrong"></textarea>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-soft" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-danger">Send report</button>
            </div>
        </form>
    </div>
</div>
