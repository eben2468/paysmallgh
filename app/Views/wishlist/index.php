<?php $view = new App\Core\View(); ?>
<div class="wrap page-head">
  <h1>Saved items</h1>
  <p>Things you're eyeing. When you're ready, start a plan — or watch for the price to drop.</p>
</div>

<div class="wrap section-tight" style="padding-top:0">
  <?php if (empty($products)): ?>
    <div class="empty-card">
      <h2>Nothing saved yet</h2>
      <p>Tap the heart on any item to keep it here.</p>
      <a class="btn btn-primary" href="<?= url('/shop') ?>">Browse products</a>
    </div>
  <?php else: ?>
    <div class="product-grid">
      <?php foreach ($products as $p): ?>
        <?= $view->partial('partials/product-card', ['p' => $p]) ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
