<?php use App\Core\Csrf; ?>
<div class="pg-head">
  <div>
    <h1>Products</h1>
    <p>What customers see in your shop. Hide one any time &mdash; plans already running carry on either way.</p>
  </div>
  <div class="pg-actions">
    <a class="btn btn-sm btn-primary" href="<?= url('/merchant/products/new') ?>"><?= micon('add', ['size' => 18]) ?> Add product</a>
  </div>
</div>

<section class="panel">
  <?php if (empty($products)): ?>
    <div class="panel-empty">
      <?= micon('inventory_2') ?>
      <p class="mb-2">Nothing listed yet. Add your first product &mdash; name, price, a photo, done.</p>
      <a class="btn btn-primary btn-sm" href="<?= url('/merchant/products/new') ?>"><?= micon('add', ['size' => 18]) ?> Add a product</a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Product</th><th>Category</th><th class="right">Cash price</th><th>Stock</th><th>Customers can pay</th><th>In shop</th><th class="right">Actions</th></tr></thead>
        <tbody>
          <?php foreach ($products as $p): ?>
            <tr>
              <td>
                <a class="cell-main" href="<?= url('/merchant/products/' . $p['id'] . '/edit') ?>"><?= e($p['name']) ?></a>
                <?php if (!empty($p['sku'])): ?><span class="cell-sub mono">SKU <?= e($p['sku']) ?></span><?php endif; ?>
              </td>
              <td class="small muted"><?= e(\App\Models\Product::categoryLabel((string) $p['category'])) ?></td>
              <td class="right nowrap mono"><?= e(ghs((int) $p['cash_price_pesewas'])) ?><?php if (!empty($p['compare_at_pesewas'])): ?><span class="cell-sub"><s><?= e(ghs((int) $p['compare_at_pesewas'])) ?></s></span><?php endif; ?></td>
              <td class="small nowrap">
                <?php
                  $vc = (int) $p['variant_count'];
                  $units = $vc > 0
                      ? ((int) $p['variant_untracked'] > 0 ? null : (int) $p['variant_stock'])
                      : ($p['stock'] === null ? null : (int) $p['stock']);
                ?>
                <?php if ($units === null): ?>
                  <span class="muted">Not counted</span>
                <?php elseif ($units === 0): ?>
                  <span class="tag tag-flagged">Sold out</span>
                <?php else: ?>
                  <?= $units ?> in stock
                <?php endif; ?>
                <?php if ($vc > 0): ?><span class="cell-sub"><?= $vc ?> option<?= $vc === 1 ? '' : 's' ?></span><?php endif; ?>
              </td>
              <td class="small"><?= e(implode(' · ', array_map(fn($f) => \App\Models\Product::FREQUENCIES[$f], \App\Models\Product::allowedFrequencies($p)))) ?><span class="cell-sub">or in full</span></td>
              <td>
                <?php if ($p['active']): ?>
                  <span class="tag tag-active"><?= micon('visibility', ['size' => 14]) ?> Visible</span>
                <?php else: ?>
                  <span class="tag"><?= micon('visibility_off', ['size' => 14]) ?> Hidden</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="row-actions">
                  <a class="btn btn-sm btn-ghost" href="<?= url('/merchant/products/' . $p['id'] . '/edit') ?>"><?= micon('edit', ['size' => 16]) ?> Edit</a>
                  <form class="inline-form" method="post" action="<?= url('/merchant/products/' . $p['id'] . '/toggle') ?>">
                    <?= Csrf::field() ?>
                    <button class="btn btn-sm btn-quiet" type="submit"><?= $p['active'] ? 'Hide' : 'Show' ?></button>
                  </form>
                  <form class="inline-form" method="post" action="<?= url('/merchant/products/' . $p['id'] . '/delete') ?>"
                        data-confirm="Delete this product for good? (Only works if no customer has a plan on it.)">
                    <?= Csrf::field() ?>
                    <button class="btn btn-sm btn-quiet" type="submit" aria-label="Delete <?= e($p['name']) ?>"><?= micon('delete', ['size' => 16]) ?></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
