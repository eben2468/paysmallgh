<?php
use App\Core\Csrf;
use App\Models\Address;
?>
<div class="wrap account-layout">
  <?= (new App\Core\View())->partial('partials/account-nav', ['tab' => $tab, 'user' => $user]) ?>

  <div class="account-main">
    <div class="account-head account-head-row">
      <div>
        <h1>Addresses</h1>
        <p>Where your items should go. When a plan is fully paid, the shop sees your <b>default</b> address — before that, nobody does.</p>
      </div>
      <a class="btn btn-primary" href="<?= url('/account/addresses/new') ?>"><?= micon('add', ['size' => 18]) ?> Add address</a>
    </div>

    <?php if (!$addresses): ?>
      <div class="empty-card">
        <h2>No addresses yet</h2>
        <p>Most people collect from the shop — but if a shop delivers, it helps to have your address ready.</p>
        <a class="btn btn-primary" href="<?= url('/account/addresses/new') ?>">Add your first address</a>
      </div>
    <?php else: ?>
      <div class="address-grid">
        <?php foreach ($addresses as $a): ?>
          <article class="address-card<?= $a['is_default'] ? ' is-default' : '' ?>">
            <div class="address-top">
              <b><?= e($a['label'] !== '' ? $a['label'] : 'Address') ?></b>
              <?php if ($a['is_default']): ?><span class="tag tag-approved">Default</span><?php endif; ?>
            </div>
            <p class="address-who"><?= e($a['recipient']) ?> · <?= e(pretty_phone($a['phone'])) ?></p>
            <p class="address-line"><?= e(Address::oneLine($a)) ?></p>
            <div class="address-actions">
              <a class="btn btn-sm btn-ghost" href="<?= url('/account/addresses/' . (int) $a['id'] . '/edit') ?>"><?= micon('edit', ['size' => 16]) ?> Edit</a>
              <?php if (!$a['is_default']): ?>
                <form class="inline-form" method="post" action="<?= url('/account/addresses/' . (int) $a['id'] . '/default') ?>">
                  <?= Csrf::field() ?><button class="btn btn-sm btn-quiet" type="submit">Make default</button>
                </form>
              <?php endif; ?>
              <form class="inline-form" method="post" action="<?= url('/account/addresses/' . (int) $a['id'] . '/delete') ?>" data-confirm="Delete this address?">
                <?= Csrf::field() ?><button class="btn btn-sm btn-quiet" type="submit" aria-label="Delete <?= e($a['label'] !== '' ? $a['label'] : 'address') ?>"><?= micon('delete', ['size' => 16]) ?></button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
