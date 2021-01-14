{literal}
<script type="text/javascript">
$( document ).ready(function() {
	var pending = false;
	$("[data-vismapay-button]").click(function(e){
		e.preventDefault();
		if(pending)
			return false;
		pending = true;
		$("#vismapay_error").hide();
		var query = $.ajax({
			type: 'POST',
			url: '{/literal}{$process_url}{literal}',
			data: {cart_id: '{/literal}{$cart_id}{literal}', selected: $(this).data('selected')},
			dataType: 'json',

			success: function(json) {
				if(json != null)
				{
					if(json.payment_url != null)
						location.href = json.payment_url;
					else if(json.error != null)
					{
						$("#vismapay_error").fadeIn();
						$("#vismapay_error").html(json.error);
						pending = false;
					}
				}
				else
				{
					location.reload();
				}
			}
		});
	})
});
</script>
{/literal}
<div class="row vismapay-row">
	<div class="col-xs-12">
		<p class="vismapay-title">{l s='Visma Pay' mod='vismapay'}&nbsp;<span>{l s='(Internet banking, credit card or credit invoice)' mod='vismapay'}</span></p>
	</div>
	{foreach from=$vismapay_payment_methods.creditcards key=name item=method}
	<div class="col-xs-12 col-sm-6 col-md-3 col-lg-2">
		<p class="payment_module vismapay">
			<a data-vismapay-button data-selected="creditcards" class="vismapay-button vismapay-{$name}" href="#">
				<img class="vismapay-pm-logo" src="{$img_url}{$name}.png" alt="{$method}"/>
			</a>
		</p>
	</div>
	{/foreach}
	{foreach from=$vismapay_payment_methods.wallets key=name item=method}
	<div class="col-xs-12 col-sm-6 col-md-3 col-lg-2">
		<p class="payment_module vismapay">
			<a data-vismapay-button data-selected="{$name}" class="vismapay-button vismapay-{$name}" href="#">
				<img class="vismapay-pm-logo" src="{$img_url}{$name}.png" alt="{$method}"/>
			</a>
		</p>
	</div>
	{/foreach}
	{foreach from=$vismapay_payment_methods.banks key=name item=method}
	<div class="col-xs-12 col-sm-6 col-md-3 col-lg-2">
		<p class="payment_module vismapay">
			<a data-vismapay-button data-selected="{$name}" class="vismapay-button vismapay-{$name}" href="#">
				<img class="vismapay-pm-logo" src="{$img_url}{$name}.png" alt="{$method}"/>
			</a>
		</p>
	</div>
	{/foreach}
	{foreach from=$vismapay_payment_methods.creditinvoices key=name item=method}
	<div class="col-xs-12 col-sm-6 col-md-3 col-lg-2">
		<p class="payment_module vismapay">
			<a data-vismapay-button data-selected="{$name}" class="vismapay-button vismapay-{$name}" href="#">
				<img class="vismapay-pm-logo" src="{$img_url}{$name}.png" alt="{$method}"/>
			</a>
		</p>
	</div>
	{/foreach}
	<br />
</div>

<div id="vismapay_error" style="display:none;"></div>

