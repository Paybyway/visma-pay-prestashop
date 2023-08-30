<div class="clear-fix additional-information vismapay-payment-method-wrapper">
<form method="POST" action="{$action}" id="payment-form">
{foreach from=$vismapay_payment_methods.banks key=name item=method}
<div class="col-xs-12 col-md-6">
<div class="vismapay-payment-method">
  <span class="custom-radio pull-xs-left vismapay-checkbox">
    <input class="ps-shown-by-js " type="radio" required id="pm-{$name}" name="selected" value="{$name}" />
    <span></span>
  </span>
  <label for="pm-{$name}">
    <span><img class="vismapay-pm-logo-pointer" src="{$img_url}{$name}.png" alt="{$method}"/></span>
  </label>
</div>
</div>
{/foreach}
{foreach from=$vismapay_payment_methods.creditcards key=name item=method}
<div class="col-xs-12 col-md-6">
<div class="vismapay-payment-method">
  <span class="custom-radio pull-xs-left vismapay-checkbox">
    <input class="ps-shown-by-js " type="radio" required id="pm-{$name}" name="selected" value="creditcards" />
    <span></span>
  </span>
  <label for="pm-{$name}">
    <span><img class="vismapay-pm-logo-pointer" src="{$img_url}{$name}.png" alt="{$method}"/></span>
  </label>
</div>
</div>
{/foreach}
{foreach from=$vismapay_payment_methods.wallets key=name item=method}
<div class="col-xs-12 col-md-6">
<div class="vismapay-payment-method">
  <span class="custom-radio pull-xs-left vismapay-checkbox">
    <input class="ps-shown-by-js " type="radio" required id="pm-{$name}" name="selected" value="{$name}" />
    <span></span>
  </span>
  <label for="pm-{$name}">
    <span><img class="vismapay-pm-logo-pointer" src="{$img_url}{$name}.png" alt="{$method}"/></span>
  </label>
</div>
</div>
{/foreach}
{foreach from=$vismapay_payment_methods.creditinvoices key=name item=method}
<div class="col-xs-12 col-md-6">
<div class="vismapay-payment-method">
  <span class="custom-radio pull-xs-left vismapay-checkbox">
    <input class="ps-shown-by-js " type="radio" required id="pm-{$name}" name="selected" value="{$name}" />
    <span></span>
  </span>
  <label for="pm-{$name}">
    <span><img class="vismapay-pm-logo-pointer" src="{$img_url}{$name}.png" alt="{$method}"/></span>
  </label>
</div>
</div>
{/foreach}
<div class="clear-fix col-md-12"></div>
</form>
</div>