<?php use App\Core\Csrf; ?>
<section class="page-head wrap">
  <h1><?= e($merchant['shop_name']) ?></h1>
  <p>
    <span class="tag tag-<?= $merchant['status'] === 'approved' ? 'completed' : ($merchant['status'] === 'pending' ? 'pending' : 'cancelled') ?>"><?= e($merchant['status']) ?></span>
    <?php if ($merchant['verified']): ?><span class="tag tag-verified"><?= micon('verified', ['size' => 14, 'fill' => true]) ?> verified</span><?php endif; ?>
  </p>
</section>

<section class="wrap detail-grid" style="padding-bottom:3rem">
  <div>
    <div class="admin-nav">
      <a class="btn btn-sm" href="<?= url('/admin') ?>">&larr; Admin home</a>
      <?php if ($merchant['status'] === 'pending'): ?>
        <form class="inline-form" method="post" action="<?= url('/admin/merchant/' . $merchant['id'] . '/approve') ?>">
          <?= Csrf::field() ?><button class="btn btn-sm btn-green" type="submit">Approve shop</button>
        </form>
      <?php elseif ($merchant['status'] === 'approved'): ?>
        <form class="inline-form" method="post" action="<?= url('/admin/merchant/' . $merchant['id'] . '/suspend') ?>" data-confirm="Suspend this shop?">
          <?= Csrf::field() ?><button class="btn btn-sm btn-quiet" type="submit">Suspend</button>
        </form>
      <?php elseif ($merchant['status'] === 'suspended'): ?>
        <form class="inline-form" method="post" action="<?= url('/admin/merchant/' . $merchant['id'] . '/reactivate') ?>">
          <?= Csrf::field() ?><button class="btn btn-sm btn-green" type="submit">Reactivate</button>
        </form>
      <?php endif; ?>
      <?php if ($merchant['verified']): ?>
        <form class="inline-form" method="post" action="<?= url('/admin/merchant/' . $merchant['id'] . '/unverify') ?>" data-confirm="Remove verified badge?">
          <?= Csrf::field() ?><button class="btn btn-sm btn-quiet" type="submit">Unverify</button>
        </form>
      <?php else: ?>
        <form class="inline-form" method="post" action="<?= url('/admin/merchant/' . $merchant['id'] . '/verify') ?>">
          <?= Csrf::field() ?><button class="btn btn-sm btn-green" type="submit"><?= micon('verified', ['size' => 14]) ?> Verify identity</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="table-scroll" style="padding:1.3rem">
      <h2 style="font-size:1.15rem;margin-bottom:.8rem">Shop details</h2>
      <dl class="kv">
        <div><dt>Owner</dt><dd><?= e($merchant['owner_name']) ?></dd></div>
        <div><dt>Phone</dt><dd class="mono"><?= e(pretty_phone($merchant['phone'])) ?></dd></div>
        <div><dt>Location</dt><dd><?= e($merchant['location'] ?: '—') ?></dd></div>
        <div><dt>Payout</dt><dd><?= e($merchant['payout_channel']) ?> &middot; <span class="mono"><?= e(pretty_phone($merchant['payout_number'])) ?></span></dd></div>
        <div><dt>Ghana Card</dt><dd class="mono"><?= e($merchant['id_number'] ?: '—') ?></dd></div>
        <div><dt>Business reg</dt><dd><?= e($merchant['business_reg'] ?: '—') ?></dd></div>
        <div><dt>Joined</dt><dd><?= e(date('j M Y', strtotime((string) $merchant['created_at']))) ?></dd></div>
        <?php if (!empty($merchant['verified_at'])): ?>
          <div><dt>Verified</dt><dd><?= e(date('j M Y', strtotime((string) $merchant['verified_at']))) ?></dd></div>
        <?php endif; ?>
      </dl>
    </div>

    <h2 style="font-size:1.15rem" class="mt-3 mb-2">Products (<?= count($products) ?>)</h2>
    <div class="table-scroll">
      <?php if (empty($products)): ?>
        <p class="muted" style="padding:1.1rem">No products listed yet.</p>
      <?php else: ?>
        <table class="data">
          <thead><tr><th>Name</th><th>Category</th><th>Price</th><th>Live</th></tr></thead>
          <tbody>
            <?php foreach ($products as $pr): ?>
              <tr>
                <td><?= e($pr['name']) ?></td>
                <td class="small muted"><?= e(ucfirst((string) $pr['category'])) ?></td>
                <td class="nowrap"><?= ghs((int) $pr['cash_price_pesewas']) ?></td>
                <td><?= $pr['active'] ? '<span class="tag tag-completed">active</span>' : '<span class="tag">hidden</span>' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <h2 style="font-size:1.15rem" class="mt-3 mb-2">Customer plans (<?= count($plans) ?>)</h2>
    <div class="table-scroll">
      <?php if (empty($plans)): ?>
        <p class="muted" style="padding:1.1rem">No plans on this shop's products yet.</p>
      <?php else: ?>
        <table class="data">
          <thead><tr><th>#</th><th>Customer</th><th>Product</th><th>Progress</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($plans as $pl): ?>
              <tr>
                <td><a href="<?= url('/admin/plan/' . $pl['id']) ?>">#<?= (int) $pl['id'] ?></a></td>
                <td><?= e($pl['customer_name']) ?></td>
                <td><?= e($pl['product_name']) ?></td>
                <td class="nowrap"><?= (int) $pl['installments_paid'] ?>/<?= (int) $pl['installments_total'] ?></td>
                <td><span class="tag tag-<?= e($pl['status']) ?>"><?= e($pl['status']) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <aside>
    <h2 style="font-size:1.15rem" class="mb-2">Ghana Card (KYC)</h2>
    <?php if (!empty($merchant['id_card_path'])): ?>
      <div class="id-doc">
        <a href="<?= url('/admin/merchant/' . $merchant['id'] . '/id-card') ?>" target="_blank" rel="noopener">
          <img src="<?= url('/admin/merchant/' . $merchant['id'] . '/id-card') ?>" alt="Ghana Card for <?= e($merchant['shop_name']) ?>">
        </a>
        <p class="small muted mt-1"><?= micon('lock', ['size' => 13, 'fill' => true]) ?> Confidential — visible to admins only. Click to open full size.</p>
      </div>
    <?php else: ?>
      <div class="info-card">
        <div class="info-ic"><?= micon('image_not_supported', ['size' => 20]) ?></div>
        <div>
          <h4>No card uploaded</h4>
          <p>This merchant registered without uploading a Ghana Card image. Verify with care, or ask them to send it.</p>
        </div>
      </div>
    <?php endif; ?>
  </aside>
</section>
