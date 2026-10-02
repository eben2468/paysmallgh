<?php
use App\Core\Csrf;
use App\Models\Product;

// After a failed save, re-show what the merchant typed instead of the stored values.
$old = $_SESSION['product_form_old'] ?? null;
unset($_SESSION['product_form_old']);
$old = is_array($old) ? $old : null;

/** Current value of a field: what was just typed, else what's stored. */
$val = static function (string $field, string $stored = '') use ($old): string {
    return $old !== null ? (string) ($old[$field] ?? '') : $stored;
};
$cedis = static fn ($p): string => $p === null || $p === '' ? '' : number_format(((int) $p) / 100, 2, '.', '');

if ($old !== null) {
    $variantRows = array_values(array_filter((array) ($old['variants'] ?? []), 'is_array'));
} else {
    $variantRows = array_map(static fn (array $v): array => [
        'id' => $v['id'], 'opt1' => $v['option1'], 'opt2' => $v['option2'], 'opt3' => $v['option3'],
        'sku' => $v['sku'] ?? '', 'price' => $cedis($v['price_pesewas']), 'stock' => $v['stock'] === null ? '' : (string) $v['stock'],
    ], $variants ?? []);
}
$optName = [];
for ($i = 1; $i <= 3; $i++) {
    $optName[$i] = $val("option{$i}_name", (string) ($product["option{$i}_name"] ?? ''));
}
?>
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
          <input id="name" name="name" type="text" required maxlength="160" value="<?= e($val('name', (string) ($product['name'] ?? ''))) ?>">
        </div>
        <div class="form-grid">
          <div class="field">
            <label for="category">Category</label>
            <?php $cat = $val('category', (string) ($product['category'] ?? '')); ?>
            <select id="category" name="category" required>
              <option value="" <?= $cat === '' ? 'selected' : '' ?> disabled>Choose a category</option>
              <?php foreach (Product::CATEGORIES as $slug => $label): ?>
                <option value="<?= e($slug) ?>" <?= $cat === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
              <?php if ($cat !== '' && !isset(Product::CATEGORIES[$cat])): ?>
                <option value="<?= e($cat) ?>" selected><?= e(ucfirst($cat)) ?> (current)</option>
              <?php endif; ?>
            </select>
          </div>
          <div class="field">
            <label for="sku">SKU / item code <span class="muted">(optional)</span></label>
            <input id="sku" name="sku" type="text" maxlength="64" value="<?= e($val('sku', (string) ($product['sku'] ?? ''))) ?>" placeholder="e.g. KM-A16-128">
            <p class="field-hint">Your own code for this item. Customers can search by it.</p>
          </div>
        </div>
        <div class="field">
          <label for="description">Description</label>
          <textarea id="description" name="description" rows="5" maxlength="2000"><?= e($val('description', (string) ($product['description'] ?? ''))) ?></textarea>
        </div>
        <div class="field">
          <label for="specs">Specifications <span class="muted">(optional)</span></label>
          <textarea id="specs" name="specs" rows="5" maxlength="3000" placeholder="Storage: 128GB&#10;Battery: 5000mAh&#10;Warranty: 1 year from the shop"><?= e($val('specs', (string) ($product['specs'] ?? ''))) ?></textarea>
          <p class="field-hint">One per line, as <b>Name: value</b>. Shown as a neat table on the product page.</p>
        </div>
        <div class="field mb-1">
          <label class="check-line"><input type="checkbox" name="active" <?= ($old !== null ? isset($old['active']) : (!$product || $product['active'])) ? 'checked' : '' ?>> Visible in the shop</label>
        </div>
      </div>
    </section>

    <section class="panel">
      <div class="panel-head"><h2><?= micon('sell', ['size' => 20]) ?> Price &amp; stock</h2></div>
      <div class="panel-body">
        <div class="form-grid">
          <div class="field">
            <label for="price">Cash price (GHS)</label>
            <input id="price" name="price" type="number" min="10" step="0.01" required
                   value="<?= e($val('price', $product ? $cedis($product['cash_price_pesewas']) : '')) ?>">
            <p class="field-hint">The full price if someone paid today. Weekly amounts are worked out from this.</p>
          </div>
          <div class="field">
            <label for="old_price">Old price (GHS) <span class="muted">(optional)</span></label>
            <input id="old_price" name="old_price" type="number" min="10" step="0.01"
                   value="<?= e($val('old_price', $product ? $cedis($product['compare_at_pesewas']) : '')) ?>">
            <p class="field-hint">Reduced the price? Put what it used to be. Shoppers see it crossed out with the % off.</p>
          </div>
        </div>
        <div class="field">
          <label for="stock">How many do you have? <span class="muted">(optional)</span></label>
          <input id="stock" name="stock" type="number" min="0" step="1" inputmode="numeric" style="max-width:12rem"
                 value="<?= e($val('stock', $product && $product['stock'] !== null ? (string) $product['stock'] : '')) ?>">
          <p class="field-hint">Leave empty if you don't count stock. When a plan's first payment lands, one comes off; a cancelled plan puts it back. At 0 the item shows as sold out. If you add options below, each option has its own stock instead.</p>
        </div>
      </div>
    </section>

    <section class="panel">
      <div class="panel-head"><h2><?= micon('tune', ['size' => 20]) ?> Options <span class="muted small">(size, colour, storage…)</span></h2></div>
      <div class="panel-body" data-variant-editor>
        <p class="small muted mb-2">Only if the customer has to choose. Name up to three options, then add one row for each version you sell. Leave a row's price empty to use the cash price above.</p>
        <div class="form-grid form-grid-3">
          <?php foreach ([1 => 'e.g. Colour', 2 => 'e.g. Storage', 3 => 'e.g. Size'] as $i => $ph): ?>
            <div class="field">
              <label for="option<?= $i ?>_name">Option <?= $i ?> name</label>
              <input id="option<?= $i ?>_name" name="option<?= $i ?>_name" type="text" maxlength="40" placeholder="<?= e($ph) ?>"
                     value="<?= e($optName[$i]) ?>" data-option-name="<?= $i ?>">
            </div>
          <?php endforeach; ?>
        </div>

        <div class="table-wrap variant-table-wrap">
          <table class="data variant-table">
            <thead>
              <tr>
                <?php for ($i = 1; $i <= 3; $i++): ?>
                  <th data-opt-col="<?= $i ?>"><span data-opt-head="<?= $i ?>"><?= e($optName[$i] !== '' ? $optName[$i] : 'Option ' . $i) ?></span></th>
                <?php endfor; ?>
                <th>Price (GHS)</th><th>Stock</th><th>SKU</th><th><span class="sr-only">Remove</span></th>
              </tr>
            </thead>
            <tbody data-variant-rows>
              <?php
                // Always one blank row at the end, so options can be added without JavaScript.
                $rowsOut = $variantRows;
                $rowsOut[] = ['id' => '', 'opt1' => '', 'opt2' => '', 'opt3' => '', 'sku' => '', 'price' => '', 'stock' => ''];
              ?>
              <?php foreach ($rowsOut as $n => $r): ?>
                <tr data-variant-row>
                  <?php for ($i = 1; $i <= 3; $i++): ?>
                    <td data-opt-col="<?= $i ?>"><input type="text" maxlength="60" name="variants[<?= $n ?>][opt<?= $i ?>]" value="<?= e((string) ($r["opt{$i}"] ?? '')) ?>" aria-label="Option <?= $i ?>"></td>
                  <?php endfor; ?>
                  <td><input type="number" min="10" step="0.01" name="variants[<?= $n ?>][price]" value="<?= e((string) ($r['price'] ?? '')) ?>" placeholder="Same" aria-label="Price"></td>
                  <td><input type="number" min="0" step="1" name="variants[<?= $n ?>][stock]" value="<?= e((string) ($r['stock'] ?? '')) ?>" placeholder="—" aria-label="Stock"></td>
                  <td><input type="text" maxlength="64" name="variants[<?= $n ?>][sku]" value="<?= e((string) ($r['sku'] ?? '')) ?>" aria-label="SKU">
                      <input type="hidden" name="variants[<?= $n ?>][id]" value="<?= e((string) ($r['id'] ?? '')) ?>"></td>
                  <td><button type="button" class="btn btn-sm btn-quiet" data-variant-remove aria-label="Remove this row"><?= micon('close', ['size' => 16]) ?></button></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <button type="button" class="btn btn-sm btn-ghost mt-2" data-variant-add hidden><?= micon('add', ['size' => 16]) ?> Add a row</button>
        <p class="field-hint mt-1">To remove a row, clear its option values (or tap the cross). Plans already running keep what the customer picked.</p>
      </div>
    </section>

    <section class="panel">
      <div class="panel-head"><h2><?= micon('calendar_month', ['size' => 20]) ?> How customers can pay</h2></div>
      <div class="panel-body">
        <p class="small muted mb-2">Tick the schedules you're happy with. Customers only see what you allow.</p>
        <?php
          $allowed = $old !== null
              ? array_values(array_intersect(array_keys(Product::FREQUENCIES), array_map('strval', (array) ($old['frequencies'] ?? []))))
              : ($product ? Product::allowedFrequencies($product) : array_keys(Product::FREQUENCIES));
        ?>
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

   <div class="stack">
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
                  <?= picture($img['path'], 'Product photo') ?>
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
          <p class="field-hint">Real photos of the actual item from a few angles sell best. Up to 8 per upload.<?= $old !== null ? ' <b>Pick your new photos again</b> — browsers forget them after an error.' : '' ?></p>
          <div class="img-preview" data-image-preview aria-live="polite"></div>
        </div>
      </div>
    </section>

    <section class="panel">
      <div class="panel-head"><h2><?= micon('local_shipping', ['size' => 20]) ?> Delivery &amp; returns</h2></div>
      <div class="panel-body">
        <div class="field">
          <label for="delivery_info">Delivery <span class="muted">(optional)</span></label>
          <textarea id="delivery_info" name="delivery_info" rows="3" maxlength="1000" placeholder="e.g. Free delivery within Kumasi. Outside Kumasi we send by VIP bus — you pay the bus fare."><?= e($val('delivery_info', (string) ($product['delivery_info'] ?? ''))) ?></textarea>
          <p class="field-hint">By default customers collect from your shop once their plan is fully paid. Say here if you also deliver.</p>
        </div>
        <div class="field">
          <label for="return_policy">Returns after collection <span class="muted">(optional)</span></label>
          <textarea id="return_policy" name="return_policy" rows="3" maxlength="1000" placeholder="e.g. Swap within 7 days if it's faulty — bring the box and receipt."><?= e($val('return_policy', (string) ($product['return_policy'] ?? ''))) ?></textarea>
          <p class="field-hint">Cancelling before the plan finishes is handled by PaySmallSmall (refund minus a small fee). This is for after they've collected.</p>
        </div>
      </div>
    </section>
   </div>
  </div>

  <div class="form-foot mt-3">
    <a class="btn btn-ghost" href="<?= url('/merchant/products') ?>">Cancel</a>
    <button class="btn btn-primary" type="submit"><?= micon('save', ['size' => 18]) ?> <?= $product ? 'Save changes' : 'Add product' ?></button>
  </div>
</form>
