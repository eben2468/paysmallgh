<?php use App\Core\Csrf; ?>
<section class="wrap" style="max-width:520px">
  <p class="small muted mb-2"><a href="<?= url('/plan/' . $plan['id']) ?>">&larr; Back to my plan</a></p>

  <div class="receipt reveal">
    <span class="receipt-tag">Secure checkout &middot; test mode</span>
    <h1 class="receipt-title" style="font-size:1.6rem">Pay <?= ghs((int) $tx['amount_pesewas']) ?></h1>
    <p class="receipt-sub"><?= e(plan_item($plan)) ?> &middot; <?= e($plan['shop_name']) ?></p>

    <p class="small muted mt-2">This is a stand-in for Paystack's checkout so you can test the full flow without moving real money. In live mode the customer lands on Paystack's own hosted page here.</p>

    <div class="pay-methods mt-3" role="tablist" aria-label="Payment method">
      <button type="button" class="pay-method is-active" data-method="momo"><?= micon('smartphone', ['size' => 18]) ?> Mobile money</button>
      <button type="button" class="pay-method" data-method="card"><?= micon('credit_card', ['size' => 18]) ?> Card</button>
      <button type="button" class="pay-method" data-method="bank"><?= micon('account_balance', ['size' => 18]) ?> Bank</button>
    </div>

    <p class="small muted mt-2" data-method-note>Enter your mobile money number and approve the prompt.</p>

    <form method="post" action="<?= url('/checkout/mock/confirm') ?>" class="mt-2">
      <?= Csrf::field() ?>
      <input type="hidden" name="ref" value="<?= e($ref) ?>">
      <input type="hidden" name="method" value="momo" data-method-input>
      <button class="btn btn-momo btn-lg btn-block" type="submit">Pay <?= ghs((int) $tx['amount_pesewas']) ?> now</button>
    </form>
    <p class="small muted mt-2" style="text-align:center">Test payment &middot; no real charge</p>
  </div>
</section>

<script>
  (function () {
    var notes = {
      momo: 'Enter your mobile money number and approve the prompt.',
      card: 'Enter your card number, expiry and CVV. (Test card: it can be saved for one-tap payments.)',
      bank: 'Choose your bank and authorise the transfer.'
    };
    var note = document.querySelector('[data-method-note]');
    document.querySelectorAll('.pay-method').forEach(function (b) {
      b.addEventListener('click', function () {
        document.querySelectorAll('.pay-method').forEach(function (x) { x.classList.remove('is-active'); });
        b.classList.add('is-active');
        if (note) note.textContent = notes[b.getAttribute('data-method')] || '';
        var input = document.querySelector('[data-method-input]');
        if (input) input.value = b.getAttribute('data-method');
      });
    });
  })();
</script>
