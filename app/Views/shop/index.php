<?php
use App\Models\Product;

$budget = $budget ?? null;
/** /shop link that keeps the current category and budget unless overridden (null drops one). */
$shopUrl = static function (array $override) use ($current, $budget): string {
    $params = array_filter(
        array_merge(['category' => $current ?: null, 'budget' => $budget], $override),
        static fn ($v) => $v !== null && $v !== ''
    );
    return url('/shop' . ($params ? '?' . http_build_query($params) : ''));
};
?>
<div class="wrap browse-layout">
  <!-- Sidebar filters -->
  <aside class="browse-aside">
    <div class="filter-group">
      <h3>Categories</h3>
      <div class="filter-list">
        <a class="filter-item <?= ($current === null || $current === '') && !$q ? 'active' : '' ?>" href="<?= $shopUrl(['category' => null]) ?>">
          <?= micon('grid_view', ['size' => 18]) ?> All products
        </a>
        <?php foreach (($categories ?? []) as $cat): ?>
          <a class="filter-item <?= $current === $cat ? 'active' : '' ?>" href="<?= $shopUrl(['category' => $cat]) ?>">
            <?= micon(product_micon($cat), ['size' => 18]) ?> <?= e(\App\Models\Product::categoryLabel($cat)) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="filter-divider"></div>
    <div class="filter-group">
      <h3>Weekly budget</h3>
      <div class="filter-list">
        <a class="filter-item <?= $budget === null ? 'active' : '' ?>" href="<?= $shopUrl(['budget' => null]) ?>">
          <?= micon('payments', ['size' => 18]) ?> Any budget
        </a>
        <?php foreach (Product::BUDGETS as $slug => $b): ?>
          <a class="filter-item <?= $budget === $slug ? 'active' : '' ?>" href="<?= $shopUrl(['budget' => $slug]) ?>">
            <?= micon('savings', ['size' => 18]) ?> <?= e($b['label']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="filter-divider"></div>
    <div class="filter-group">
      <h3>Why PaySmallSmall</h3>
      <div class="info-card" style="padding:1rem">
        <div class="info-ic"><?= micon('shield', ['size' => 20, 'fill' => true]) ?></div>
        <div>
          <h4 style="font-size:.92rem">Escrow protected</h4>
          <p style="font-size:.82rem">Your money is held safely. The shop is only paid once your plan finishes.</p>
        </div>
      </div>
    </div>
  </aside>

  <!-- Product grid -->
  <div class="browse-main">
    <div class="browse-head">
      <div>
        <h1><?= $q ? 'Results for "' . e($q) . '"' : ($current ? e(Product::categoryLabel($current)) : 'Explore plans') ?></h1>
        <p><?= $budget ? e(Product::BUDGETS[$budget]['label']) . ' (price spread over ' . Product::CARD_WEEKS . ' weeks). ' : '' ?>Every price shows two ways: cash, and small small.</p>
      </div>
      <span class="shop-count"><b><?= count($products) ?></b> product<?= count($products) === 1 ? '' : 's' ?><?= $q ? ' matching' : '' ?></span>
    </div>

    <?php if (empty($products)): ?>
      <div class="empty-card">
        <?php if ($q): ?>
          <h2>Nothing matched "<?= e($q) ?>"</h2>
          <p>Try a shorter word — "phone", "bed", "kaba".</p>
          <a class="btn btn-primary" href="<?= url('/shop') ?>">See all products</a>
        <?php else: ?>
          <h2>Nothing here yet</h2>
          <p>Check back soon — shops are adding products every week.</p>
          <?php if ($budget || $current): ?>
            <a class="btn btn-primary" href="<?= url('/shop') ?>">See all products</a>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="product-grid reveal stagger">
        <?php foreach ($products as $p): ?>
          <?= (new App\Core\View())->partial('partials/product-card', ['p' => $p]) ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
