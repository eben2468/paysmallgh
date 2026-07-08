<section class="page-head wrap">
  <h1>Browse the shops</h1>
  <p>Every price shows two ways: what it costs cash, and what it costs small small.</p>
</section>

<section class="wrap" style="padding-bottom: 3rem;">
  <?php if (!empty($categories)): ?>
  <div class="pill-row">
    <a class="pill <?= $current === null || $current === '' ? 'active' : '' ?>" href="<?= url('/shop') ?>">All</a>
    <?php foreach ($categories as $cat): ?>
      <a class="pill <?= $current === $cat ? 'active' : '' ?>" href="<?= url('/shop?category=' . urlencode($cat)) ?>"><?= e(ucfirst($cat)) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (empty($products)): ?>
    <p class="muted">Nothing here yet. Check back soon — shops are adding products.</p>
  <?php else: ?>
    <div class="product-grid">
      <?php foreach ($products as $p): ?>
        <?= (new App\Core\View())->partial('partials/product-card', ['p' => $p]) ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
