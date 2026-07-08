<?php use App\Core\Csrf; ?>
<section class="page-head wrap">
  <h1>My products</h1>
  <p>What customers see in the shop. Switch one off any time — running plans continue either way.</p>
</section>

<section class="wrap" style="padding-bottom:3rem">
  <div class="admin-nav">
    <a class="btn btn-sm" href="<?= url('/merchant/dashboard') ?>">&larr; Dashboard</a>
    <a class="btn btn-sm btn-primary" href="<?= url('/merchant/products/new') ?>">+ Add product</a>
  </div>

  <?php if (empty($products)): ?>
    <p class="muted">Nothing listed yet. Add your first product — name, price, short description, done.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="data">
        <thead><tr><th>Product</th><th>Cash price</th><th>Category</th><th>Visible</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($products as $p): ?>
            <tr>
              <td><?= e($p['name']) ?></td>
              <td><?= ghs((int) $p['cash_price_pesewas']) ?></td>
              <td><?= e($p['category']) ?></td>
              <td><?= $p['active'] ? 'Yes' : '<span class="muted">Hidden</span>' ?></td>
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
