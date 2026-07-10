<?php use App\Core\Csrf; ?>
<section class="wrap form-page">
  <h1><?= $product ? 'Edit product' : 'Add a product' ?></h1>
  <p>Write it the way you'd tell a customer standing in your shop.</p>
  <div class="form-card">
    <form method="post" enctype="multipart/form-data"
          action="<?= url($product ? '/merchant/products/' . $product['id'] . '/edit' : '/merchant/products/new') ?>">
      <?= Csrf::field() ?>
      <div class="field">
        <label for="name">Product name</label>
        <input id="name" name="name" type="text" required maxlength="160" value="<?= e($product['name'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="price">Cash price (GHS)</label>
        <input id="price" name="price" type="number" min="10" step="0.01" required
               value="<?= $product ? number_format(((int) $product['cash_price_pesewas']) / 100, 2, '.', '') : '' ?>">
        <p class="field-hint">The full price if someone paid today. Weekly amounts are worked out from this.</p>
      </div>
      <div class="field">
        <label for="category">Category</label>
        <input id="category" name="category" type="text" maxlength="60" list="cats" value="<?= e($product['category'] ?? '') ?>" placeholder="phones, furniture, fashion…">
        <datalist id="cats">
          <option value="phones"><option value="electronics"><option value="furniture"><option value="fashion"><option value="general">
        </datalist>
      </div>
      <div class="field">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4" maxlength="2000"><?= e($product['description'] ?? '') ?></textarea>
      </div>
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
        <label for="photos"><?= empty($images) ? 'Photos' : 'Add more photos' ?> (JPG/PNG/WebP, up to 4MB each)</label>
        <input id="photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple data-image-input>
        <p class="field-hint">You can pick several at once — real photos of the actual item from a few angles sell best. Up to 8 per upload.</p>
        <div class="img-preview" data-image-preview aria-live="polite"></div>
      </div>
      <div class="field">
        <label class="check-line"><input type="checkbox" name="active" <?= !$product || $product['active'] ? 'checked' : '' ?>> Visible in the shop</label>
      </div>
      <button class="btn btn-primary btn-block btn-lg" type="submit"><?= $product ? 'Save changes' : 'Add product' ?></button>
    </form>
  </div>
</section>
