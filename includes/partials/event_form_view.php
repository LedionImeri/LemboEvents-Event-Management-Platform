<?php
/** Event editor UI. Expects $values, $errors, $event (nullable), $org. */
$categories = db_all('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order');
$soldMap = $event ? array_column(db_all('SELECT id, quantity_sold FROM ticket_types WHERE event_id = ?', [$event['id']]), 'quantity_sold', 'id') : [];
$err = fn(string $k) => isset($errors[$k]) ? '<span class="field-error">' . e($errors[$k]) . '</span>' : '';
$has = fn(string $k) => isset($errors[$k]) ? ' has-error' : '';
$status = $event['status'] ?? 'new';
?>
<?php if ($errors): ?>
    <div class="alert alert-error mb-3" role="alert"><?= icon('alert') ?><div><strong>Please fix the following:</strong><ul><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="editor-layout" data-validate data-loading data-event-form>
    <?= csrf_field() ?>
    <div class="form-grid">
        <section class="card">
            <div class="card-head"><h2>Basics</h2></div>
            <div class="form-grid">
                <div class="field<?= $has('title') ?>">
                    <label for="title">Event title <span class="req">*</span></label>
                    <input class="input" id="title" name="title" value="<?= e($values['title']) ?>" required minlength="3" maxlength="150" placeholder="e.g. Kosovo Tech Summit 2026">
                    <?= $err('title') ?>
                </div>
                <div class="form-row">
                    <div class="field<?= $has('category_id') ?>">
                        <label for="category_id">Category <span class="req">*</span></label>
                        <select class="select" id="category_id" name="category_id" required>
                            <option value="">Choose…</option>
                            <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= (int) $values['category_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                        </select>
                        <?= $err('category_id') ?>
                    </div>
                    <div class="field">
                        <span class="label">Status</span>
                        <div style="min-height:48px;display:flex;align-items:center"><?= $event ? status_badge($status) : '<span class="badge badge-neutral">New draft</span>' ?></div>
                    </div>
                </div>
                <div class="field<?= $has('short_description') ?>">
                    <label for="short_description">Summary <span class="req">*</span></label>
                    <input class="input" id="short_description" name="short_description" value="<?= e($values['short_description']) ?>" required minlength="10" maxlength="255" placeholder="One sentence that sells the event">
                    <span class="hint">Shown on event cards and in search results.</span>
                    <?= $err('short_description') ?>
                </div>
                <div class="field<?= $has('description') ?>">
                    <label for="description">Description <span class="req">*</span></label>
                    <textarea class="textarea" id="description" name="description" required minlength="30" maxlength="10000" rows="9" placeholder="What can attendees expect? Lines starting with “- ” become bullet points."><?= e($values['description']) ?></textarea>
                    <?= $err('description') ?>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><h2>Date &amp; location</h2></div>
            <div class="form-grid">
                <div class="form-row">
                    <div class="field<?= $has('start') ?>">
                        <label for="start_date">Starts <span class="req">*</span></label>
                        <div class="form-row" style="gap:8px">
                            <input class="input" id="start_date" type="date" name="start_date" value="<?= e($values['start_date']) ?>" required>
                            <input class="input" type="time" name="start_time" value="<?= e($values['start_time']) ?>" required aria-label="Start time">
                        </div>
                        <?= $err('start') ?>
                    </div>
                    <div class="field<?= $has('end') ?>">
                        <label for="end_date">Ends <span class="req">*</span></label>
                        <div class="form-row" style="gap:8px">
                            <input class="input" id="end_date" type="date" name="end_date" value="<?= e($values['end_date']) ?>" required>
                            <input class="input" type="time" name="end_time" value="<?= e($values['end_time']) ?>" required aria-label="End time">
                        </div>
                        <?= $err('end') ?>
                    </div>
                </div>
                <div class="form-row">
                    <div class="field<?= $has('venue_name') ?>">
                        <label for="venue_name">Venue <span class="req">*</span></label>
                        <input class="input" id="venue_name" name="venue_name" value="<?= e($values['venue_name']) ?>" required minlength="2" maxlength="120">
                        <?= $err('venue_name') ?>
                    </div>
                    <div class="field<?= $has('city') ?>">
                        <label for="city">City <span class="req">*</span></label>
                        <input class="input" id="city" name="city" value="<?= e($values['city']) ?>" required minlength="2" maxlength="80" list="evCities">
                        <datalist id="evCities"><?php foreach (array_keys(city_coordinates()) as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
                        <?= $err('city') ?>
                    </div>
                </div>
                <div class="field<?= $has('address') ?>">
                    <label for="address">Street address</label>
                    <input class="input" id="address" name="address" value="<?= e($values['address']) ?>" maxlength="190">
                </div>
                <div class="form-row">
                    <div class="field<?= $has('latitude') ?>">
                        <label for="latitude">Latitude</label>
                        <input class="input" id="latitude" name="latitude" value="<?= e($values['latitude']) ?>" inputmode="decimal" placeholder="42.6629">
                        <?= $err('latitude') ?>
                    </div>
                    <div class="field">
                        <label for="longitude">Longitude</label>
                        <input class="input" id="longitude" name="longitude" value="<?= e($values['longitude']) ?>" inputmode="decimal" placeholder="21.1655">
                        <span class="hint">Optional — we use the city centre if empty.</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-head">
                <h2>Ticket types</h2>
                <button type="button" class="btn btn-soft btn-sm" data-add-ticket><?= icon('plus') ?> Add ticket type</button>
            </div>
            <?php if (isset($errors['tickets'])): ?><div class="alert alert-error mb-2"><?= icon('alert') ?><div><?= e($errors['tickets']) ?></div></div><?php endif; ?>
            <div class="tt-editor" data-tt-list>
                <?php foreach ($values['tickets'] as $i => $t): $sold = (int) ($soldMap[$t['id']] ?? 0); ?>
                    <div class="tt-row" data-tt-row>
                        <div class="tt-row-head">
                            <strong>Ticket type</strong>
                            <div class="flex items-center gap-2">
                                <label class="check small"><input type="checkbox" name="tickets[<?= $i ?>][is_active]" value="1"<?= $t['is_active'] ? ' checked' : '' ?>> On sale</label>
                                <?php if ($t['id']): ?>
                                    <label class="check small" title="<?= $sold ? 'Has sales: will be hidden instead of deleted' : 'Delete on save' ?>"><input type="checkbox" name="tickets[<?= $i ?>][remove]" value="1"<?= $t['remove'] ? ' checked' : '' ?>> Remove</label>
                                <?php else: ?>
                                    <button type="button" class="icon-btn" data-remove-ticket aria-label="Remove ticket type"><?= icon('trash') ?></button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <input type="hidden" name="tickets[<?= $i ?>][id]" value="<?= (int) $t['id'] ?>">
                        <div class="form-row form-row-3">
                            <div class="field"><label>Name</label><input class="input" name="tickets[<?= $i ?>][name]" value="<?= e($t['name']) ?>" maxlength="80" placeholder="VIP"></div>
                            <div class="field"><label>Price (€)</label><input class="input" type="number" name="tickets[<?= $i ?>][price]" value="<?= e((string) $t['price']) ?>" min="0" max="10000" step="0.01"></div>
                            <div class="field"><label>Quantity</label><input class="input" type="number" name="tickets[<?= $i ?>][quantity]" value="<?= e((string) $t['quantity']) ?>" min="<?= max(1, $sold) ?>" max="100000" step="1"></div>
                        </div>
                        <div class="field"><label>Description</label><input class="input" name="tickets[<?= $i ?>][description]" value="<?= e((string) $t['description']) ?>" maxlength="255" placeholder="What's included?"></div>
                        <div class="form-row form-row-3">
                            <div class="field"><label>Sale starts</label><input class="input" type="datetime-local" name="tickets[<?= $i ?>][sale_starts_at]" value="<?= e((string) $t['sale_starts_at']) ?>"></div>
                            <div class="field"><label>Sale ends</label><input class="input" type="datetime-local" name="tickets[<?= $i ?>][sale_ends_at]" value="<?= e((string) $t['sale_ends_at']) ?>"></div>
                            <div class="field"><label>Max per order</label><input class="input" type="number" name="tickets[<?= $i ?>][max_per_order]" value="<?= e((string) $t['max_per_order']) ?>" min="1" max="<?= MAX_TICKETS_PER_ORDER ?>"></div>
                        </div>
                        <?php if (isset($errors["ticket_$i"])): ?><span class="field-error"><?= e($errors["ticket_$i"]) ?></span><?php endif; ?>
                        <?php if ($sold): ?><span class="tt-sold-note"><?= icon('ticket', 'icon-sm') ?> <?= $sold ?> sold · <?= max(0, (int) $t['quantity'] - $sold) ?> available</span><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <template data-tt-template>
                <div class="tt-row" data-tt-row>
                    <div class="tt-row-head">
                        <strong>New ticket type</strong>
                        <div class="flex items-center gap-2">
                            <label class="check small"><input type="checkbox" name="tickets[__i__][is_active]" value="1" checked> On sale</label>
                            <button type="button" class="icon-btn" data-remove-ticket aria-label="Remove ticket type"><?= icon('trash') ?></button>
                        </div>
                    </div>
                    <input type="hidden" name="tickets[__i__][id]" value="0">
                    <div class="form-row form-row-3">
                        <div class="field"><label>Name</label><input class="input" name="tickets[__i__][name]" maxlength="80" placeholder="VIP"></div>
                        <div class="field"><label>Price (€)</label><input class="input" type="number" name="tickets[__i__][price]" value="0" min="0" max="10000" step="0.01"></div>
                        <div class="field"><label>Quantity</label><input class="input" type="number" name="tickets[__i__][quantity]" value="100" min="1" max="100000" step="1"></div>
                    </div>
                    <div class="field"><label>Description</label><input class="input" name="tickets[__i__][description]" maxlength="255" placeholder="What's included?"></div>
                    <div class="form-row form-row-3">
                        <div class="field"><label>Sale starts</label><input class="input" type="datetime-local" name="tickets[__i__][sale_starts_at]"></div>
                        <div class="field"><label>Sale ends</label><input class="input" type="datetime-local" name="tickets[__i__][sale_ends_at]"></div>
                        <div class="field"><label>Max per order</label><input class="input" type="number" name="tickets[__i__][max_per_order]" value="10" min="1" max="<?= MAX_TICKETS_PER_ORDER ?>"></div>
                    </div>
                </div>
            </template>
        </section>
    </div>

    <aside class="editor-side">
        <section class="card">
            <div class="card-head"><h2>Cover image</h2></div>
            <label class="file-drop<?= isset($errors['cover']) ? ' has-error' : '' ?>">
                <?php if ($event && $event['cover_image']): ?><img src="<?= e(image_thumb($event['cover_image'], 700)) ?>" alt="Current cover"><?php endif; ?>
                <span class="file-drop-text"<?= $event && $event['cover_image'] ? ' hidden' : '' ?>><?= icon('image') ?><strong>Upload a cover</strong><br><span class="small muted">JPG, PNG or WebP · max 5 MB · 1600×900 recommended</span></span>
                <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" data-max-bytes="<?= MAX_IMAGE_SIZE ?>">
            </label>
            <?= $err('cover') ?>
            <?php if ($event && $event['cover_image']): ?><p class="small muted mt-1 mb-0">Choose a new file to replace the current cover.</p><?php endif; ?>
        </section>

        <section class="card">
            <div class="card-head"><h2>Gallery</h2><span class="small muted">Up to 6 photos</span></div>
            <?php $gallery = $event ? db_all('SELECT * FROM event_images WHERE event_id = ? ORDER BY sort_order, id', [$event['id']]) : []; ?>
            <?php if ($gallery): ?>
                <div class="gallery-edit">
                    <?php foreach ($gallery as $g): ?>
                        <label class="gallery-thumb">
                            <img src="<?= e(image_url($g['path'])) ?>" alt="" loading="lazy">
                            <span class="check small"><input type="checkbox" name="remove_images[]" value="<?= (int) $g['id'] ?>"> Remove</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="field mt-1">
                <label for="gallery">Add photos</label>
                <input class="input" id="gallery" type="file" name="gallery[]" accept="image/jpeg,image/png,image/webp" multiple>
                <span class="hint">JPG, PNG or WebP · max 5 MB each</span>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><h2>Publishing</h2></div>
            <div class="status-steps mb-3">
                <div class="done"><i><?= icon('check') ?></i> Details</div>
                <div class="<?= in_array($status, ['pending', 'published'], true) ? 'done' : '' ?>"><i><?= icon('check') ?></i> Submitted for review</div>
                <div class="<?= $status === 'published' ? 'done' : '' ?>"><i><?= icon('check') ?></i> Live on LemboEvents</div>
            </div>
            <div class="form-grid" style="gap:10px">
                <?php if (in_array($status, ['new', 'draft', 'rejected'], true)): ?>
                    <button type="submit" name="intent" value="publish" class="btn btn-primary btn-block"<?= $org['status'] !== 'approved' ? ' title="Available after your organizer account is approved"' : '' ?>><?= icon('zap') ?> Save &amp; publish</button>
                    <button type="submit" name="intent" value="draft" class="btn btn-outline btn-block">Save draft</button>
                <?php else: ?>
                    <button type="submit" name="intent" value="save" class="btn btn-primary btn-block"><?= icon('check') ?> Save changes</button>
                <?php endif; ?>
                <?php if ($event): ?><a class="btn btn-ghost btn-block" href="<?= e(url('event.php?slug=' . $event['slug'])) ?>" target="_blank" rel="noopener"><?= icon('eye') ?> Preview page</a><?php endif; ?>
            </div>
            <?php if ($org['status'] !== 'approved'): ?><p class="small muted mt-2 mb-0">Publishing unlocks after your organizer account is approved.</p><?php endif; ?>
            <?php if ($status === 'published'): ?><p class="small muted mt-2 mb-0">Changes to date or venue notify every ticket holder automatically.</p><?php endif; ?>
        </section>
    </aside>
</form>
