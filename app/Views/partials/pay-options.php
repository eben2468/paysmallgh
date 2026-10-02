<?php
/**
 * How to pay the next installment. Expects:
 *   $plan, $amountLabel ("GHS 100"), $savedCards (list), $momoWallet ([phone, network]).
 * One form per choice, all posting to /plan/{id}/pay with a `method`.
 */
use App\Core\Csrf;
use App\Models\SavedCard;

$action = url('/plan/' . (int) $plan['id'] . '/pay');
[$walletPhone, $walletNet] = $momoWallet ?? [null, null];
$netNames = ['MTN' => 'MTN MoMo', 'VOD' => 'Telecel Cash', 'ATL' => 'AirtelTigo Money'];
?>
<div class="pay-options">
  <?php foreach (($savedCards ?? []) as $card): ?>
    <form method="post" action="<?= $action ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="method" value="card:<?= (int) $card['id'] ?>">
      <button class="pay-option" type="submit">
        <?= micon('credit_card', ['size' => 22]) ?>
        <span class="pay-option-text"><b>Pay <?= e($amountLabel) ?> with <?= e(SavedCard::label($card)) ?></b><small>One tap — no card details to type</small></span>
        <?= micon('chevron_right', ['size' => 20, 'class' => 'pay-option-go']) ?>
      </button>
    </form>
  <?php endforeach; ?>

  <?php if ($walletPhone && $walletNet): ?>
    <form method="post" action="<?= $action ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="method" value="momo">
      <button class="pay-option is-momo" type="submit">
        <?= micon('smartphone', ['size' => 22]) ?>
        <span class="pay-option-text"><b>Send a MoMo prompt to <?= e(pretty_phone($walletPhone)) ?></b><small><?= e($netNames[$walletNet] ?? $walletNet) ?> · approve <?= e($amountLabel) ?> on your phone</small></span>
        <?= micon('chevron_right', ['size' => 20, 'class' => 'pay-option-go']) ?>
      </button>
    </form>
  <?php endif; ?>

  <form method="post" action="<?= $action ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="method" value="checkout">
    <button class="pay-option" type="submit">
      <?= micon('open_in_new', ['size' => 22]) ?>
      <span class="pay-option-text"><b><?= ($savedCards ?? []) || $walletNet ? 'Other ways to pay' : 'Pay ' . e($amountLabel) . ' now' ?></b><small>MoMo, card or bank on the secure Paystack page</small></span>
      <?= micon('chevron_right', ['size' => 20, 'class' => 'pay-option-go']) ?>
    </button>
  </form>
  <p class="small muted"><a href="<?= url('/account/payment-methods') ?>">Manage payment methods</a></p>
</div>
