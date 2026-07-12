<?php use App\Core\Config; ?>
<section class="page-head wrap">
  <h1>How PaySmallSmall works</h1>
  <p>The long version — though honestly, it's still short.</p>
</section>

<section class="wrap section" style="padding-top:0">
  <div class="card" style="max-width:46rem;margin-inline:auto">
    <h2 class="mb-2" style="font-size:1.5rem;color:var(--primary)">For you, the buyer</h2>
    <p class="mb-2">Say you want a phone that costs <strong>GHS 1,250</strong>. You don't have GHS 1,250 sitting down. Fine. Pick a plan — maybe <strong>GHS 105 a week for 12 weeks</strong> — and approve the first payment on your MoMo. That's it, your plan is running.</p>
    <p class="mb-2">Every week you pay, we text you a receipt showing exactly where you are: <em>"5 of 12 paid, GHS 735 to go."</em> The money doesn't go to the shop — it sits in escrow with us. The shop can't touch it, and they can't sell your phone to somebody else and vanish with your deposits.</p>
    <p>When the last payment lands, we pay the shop, and both of you get an SMS. You walk in, show the message, collect your phone. Done.</p>

    <div class="perf"></div>

    <h2 class="mb-2" style="font-size:1.3rem;color:var(--primary)">If you miss a week</h2>
    <p>Life happens — we know. You get a <strong>3-day grace period</strong> and a friendly reminder, no penalty. If you need to stop the plan entirely, we refund what you've paid back to your MoMo, minus a small cancellation fee (5%). Nobody keeps your money.</p>

    <div class="perf"></div>

    <h2 class="mb-2" style="font-size:1.3rem;color:var(--primary)">For shops</h2>
    <p class="mb-2">You list your products with the cash price. When a customer starts a plan, you'll see it in your dashboard — every payment they make, in real time. You release the item only when we've paid you, so there's zero risk of handing over goods that aren't paid for.</p>
    <p>We take a small platform fee (5%) from the payout. That's the whole business model — no subscriptions, no setup cost.</p>

    <div class="hero-actions mt-3">
      <a class="btn btn-primary" href="<?= url('/shop') ?>">Browse products</a>
      <a class="btn btn-ghost" href="<?= url('/merchant') ?>">Register your shop</a>
    </div>
  </div>
</section>
