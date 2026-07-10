<?php use App\Core\Csrf; ?>
<section class="page-head wrap">
  <h1>My products</h1>
  <p>What customers see in the shop. Switch one off any time — running plans continue either way.</p>
</section>

<section class="wrap" style="padding-bottom:3rem">
  <div class="admin-nav">
    <a class="btn btn-sm" href="<?= url('/merchant/dashboard') ?>">&larr; Dashboard</a>
    <a class="btn btn-sm btn-primary" href="<?= url('/merchant/products/new') ?>"><?= micon('add', ['size' => 18]) ?> Add product</a>
  </div>

  <?php if (empty($products)): ?>
    <div class="empty-card">
      <h2>Nothing listed yet</h2>
      <p>Add your first product — name, price, short description, done.</p>
      <a class="btn btn-primary" href="<?= url('/merchant/products/new') ?>">Add a product</a>
    </div>
  <?php else: ?>
    <div class="table-scroll">
      <table class="data">
        <thead><tr><th>Product</th><th>Cash price</th><th>Category</th><th>Visible</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($products as $p): ?>
            <tr>
              <td style="font-weight:600"><?= e($p['name']) ?></td>
              <td class="mono"><?= ghs((int) $p['cash_price_pesewas']) ?></td>
              <td><?= e($p['category']) ?></td>
              <td>
                <?php if ($p['active']): ?>
                  <span class="tag tag-active"><?= micon('visibility', ['size' => 14]) ?> Live</span>
                <?php else: ?>
                  <span class="tag"><?= micon('visibility_off', ['size' => 14]) ?> Hidden</span>
                <?php endif; ?>
              </td>
              <td class="right nowrap">
                <a class="btn btn-sm btn-quiet" href="<?= url('/merchant/products/' . $p['id'] . '/edit') ?>">Edit</a>
                <form class="inline-form" method="post" action="<?= url('/merchant/products/' . $p['id'] . '/toggle') ?>">
                  <?= Csrf::field() ?>
                  <button class="btn btn-sm btn-quiet" type="submit"><?= $p['active'] ? 'Hide' : 'Show' ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
