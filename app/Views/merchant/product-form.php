<?php use App\Core\Csrf; ?>
<section class="wrap form-page">
  <h1><?= $product ? 'Edit product' : 'Add a product' ?></h1>
  <p>Write it the way you'd tell a customer standing in your shop.</p>
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
      <input id="category" name="category" type="text" maxlength="60" list="cats" value="<?= e($product['category'] ?? '') ?>" placeholder="phones, furniture, fashion...">
      <datalist id="cats">
        <option value="phones"><option value="electronics"><option value="furniture"><option value="fashion"><option value="general">
      </datalist>
    </div>
    <div class="field">
      <label for="description">Description</label>
      <textarea id="description" name="description" rows="4" maxlength="2000"><?= e($product['description'] ?? '') ?></textarea>
    </div>
    <div class="field">
      <label for="photo">Photo (JPG/PNG/WebP, up to 4MB)</label>
      <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
      <p class="field-hint">A simple, honest photo of the actual item sells best. 4:3 works nicely.</p>
    </div>
    <div class="field">
      <label><input type="checkbox" name="active" <?= !$product || $product['active'] ? 'checked' : '' ?>> Visible in the shop</label>
    </div>
    <button class="btn btn-primary btn-block" type="submit"><?= $product ? 'Save changes' : 'Add product' ?></button>
  </form>
</section>
