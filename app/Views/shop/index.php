<section class="page-head wrap">
  <h1><?= $q ? 'Results for "' . e($q) . '"' : ($current ? ucfirst(e($current)) : 'Browse the shops') ?></h1>
  <p>Every price shows two ways: what it costs cash, and what it costs small small.</p>
</section>

<section class="wrap" style="padding-bottom: 3rem;">
  <div class="shop-toolbar">
    <?php if (!empty($categories)): ?>
    <div class="pill-row">
      <a class="pill <?= ($current === null || $current === '') && !$q ? 'active' : '' ?>" href="<?= url('/shop') ?>"><?= svg_icon('grid', 15) ?> All</a>
      <?php foreach ($categories as $cat): ?>
        <a class="pill <?= $current === $cat ? 'active' : '' ?>" href="<?= url('/shop?category=' . urlencode($cat)) ?>"><?= category_icon($cat, 15) ?> <?= e(ucfirst($cat)) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <span class="shop-count"><b><?= count($products) ?></b> product<?= count($products) === 1 ? '' : 's' ?><?= $q ? ' matching your search' : '' ?></span>
  </div>

  <?php if (empty($products)): ?>
    <?php if ($q): ?>
      <p class="muted mb-3">Nothing matched "<?= e($q) ?>". Try a shorter word — "phone", "bed", "kaba".</p>
      <a class="btn" href="<?= url('/shop') ?>">See all products</a>
    <?php else: ?>
      <p class="muted">Nothing here yet. Check back soon — shops are adding products.</p>
    <?php endif; ?>
  <?php else: ?>
    <div class="product-grid">
      <?php foreach ($products as $p): ?>
        <?= (new App\Core\View())->partial('partials/product-card', ['p' => $p]) ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
