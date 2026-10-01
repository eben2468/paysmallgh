<?php use App\Core\Csrf; ?>
<a class="pg-back" href="<?= url('/merchant/products') ?>"><?= micon('arrow_back', ['size' => 16]) ?> Products</a>
<div class="pg-head">
  <div>
    <h1><?= $product ? 'Edit product' : 'Add a product' ?></h1>
    <p>Write it the way you'd tell a customer standing in your shop.</p>
  </div>
</div>

<form method="post" enctype="multipart/form-data"
      action="<?= url($product ? '/merchant/products/' . $product['id'] . '/edit' : '/merchant/products/new') ?>">
  <?= Csrf::field() ?>
  <div class="grid-2">
   <div class="stack">
    <section class="panel">
      <div class="panel-head"><h2><?= micon('edit_note', ['size' => 20]) ?> Details</h2></div>
      <div class="panel-body">
        <div class="field">
          <label for="name">Product name</label>
          <input id="name" name="name" type="text" required maxlength="160" value="<?= e($product['name'] ?? '') ?>">
        </div>
        <div class="form-grid">
          <div class="field">
            <label for="price">Cash price (GHS)</label>
            <input id="price" name="price" type="number" min="10" step="0.01" required
                   value="<?= $product ? number_format(((int) $product['cash_price_pesewas']) / 100, 2, '.', '') : '' ?>">
            <p class="field-hint">The full price if someone paid today. Weekly amounts are worked out from this.</p>
          </div>
          <div class="field">
            <label for="category">Category</label>
            <?php $cat = (string) ($product['category'] ?? ''); ?>
            <select id="category" name="category" required>
              <option value="" <?= $cat === '' ? 'selected' : '' ?> disabled>Choose a category</option>
              <?php foreach (\App\Models\Product::CATEGORIES as $slug => $label): ?>
                <option value="<?= e($slug) ?>" <?= $cat === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
              <?php if ($cat !== '' && !isset(\App\Models\Product::CATEGORIES[$cat])): ?>
                <option value="<?= e($cat) ?>" selected><?= e(ucfirst($cat)) ?> (current)</option>
              <?php endif; ?>
            </select>
          </div>
        </div>
        <div class="field">
          <label for="description">Description</label>
          <textarea id="description" name="description" rows="5" maxlength="2000"><?= e($product['description'] ?? '') ?></textarea>
        </div>
        <div class="field mb-1">
          <label class="check-line"><input type="checkbox" name="active" <?= !$product || $product['active'] ? 'checked' : '' ?>> Visible in the shop</label>
        </div>
      </div>
    </section>

    <section class="panel">
      <div class="panel-head"><h2><?= micon('calendar_month', ['size' => 20]) ?> How customers can pay</h2></div>
      <div class="panel-body">
        <p class="small muted mb-2">Tick the schedules you're happy with. Customers only see what you allow.</p>
        <?php $allowed = $product ? \App\Models\Product::allowedFrequencies($product) : array_keys(\App\Models\Product::FREQUENCIES); ?>
        <div class="choice-grid">
          <?php foreach ([
              'daily' => ['today', 'Daily', 'Small amounts every day — good for traders paid daily.'],
              'weekly' => ['date_range', 'Weekly', 'The most popular. Pay every week, e.g. every Friday.'],
              'monthly' => ['calendar_month', 'Monthly', 'For salary earners — one payment a month.'],
          ] as $key => [$icon, $label, $hint]): ?>
            <label class="choice">
              <input type="checkbox" name="frequencies[]" value="<?= e($key) ?>" <?= in_array($key, $allowed, true) ? 'checked' : '' ?>>
              <span class="choice-box">
                <?= micon($icon, ['size' => 22]) ?>
                <b><?= e($label) ?></b>
                <span><?= e($hint) ?></span>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
        <p class="small muted mt-2"><?= micon('payments', ['size' => 16]) ?> Customers can <b>always pay the full price at once</b> too. You're paid out as soon as that payment clears.</p>
      </div>
    </section>
   </div>

    <section class="panel">
      <div class="panel-head"><h2><?= micon('photo_library', ['size' => 20]) ?> Photos</h2></div>
      <div class="panel-body">
        <?php if (!empty($images)): ?>
          <div class="field">
            <label>Current photos</label>
            <p class="field-hint">Tick a photo to remove it when you save. The first one is the cover shoppers see first.</p>
            <div class="img-manage">
              <?php foreach ($images as $img): ?>
                <label class="img-manage-item">
                  <img src="<?= url('/' . $img['path']) ?>" alt="Product photo">
                  <span class="img-remove">
                    <input type="checkbox" name="remove_images[]" value="<?= (int) $img['id'] ?>">
                    <span><?= micon('delete', ['size' => 15]) ?> Remove</span>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
        <div class="field">
          <label for="photos"><?= empty($images) ? 'Add photos' : 'Add more photos' ?> (JPG/PNG/WebP, up to 4MB each)</label>
          <input id="photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple data-image-input>
          <p class="field-hint">Real photos of the actual item from a few angles sell best. Up to 8 per upload.</p>
          <div class="img-preview" data-image-preview aria-live="polite"></div>
        </div>
      </div>
    </section>
  </div>

  <div class="form-foot mt-3">
    <a class="btn btn-ghost" href="<?= url('/merchant/products') ?>">Cancel</a>
    <button class="btn btn-primary" type="submit"><?= micon('save', ['size' => 18]) ?> <?= $product ? 'Save changes' : 'Add product' ?></button>
  </div>
</form>
