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

{foreach from=$vismapay_payment_methods.creditcards key=name item=method}
<p class="payment_module">
	<a data-vismapay-button data-selected="creditcards" class="vismapay-button vismapay-{$name}" href="#">
		<img src="{$img_url}{$name}.png" width="45" alt="{$method}"/>
		{$method}
	</a>
</p>
{/foreach}
{foreach from=$vismapay_payment_methods.wallets key=name item=method}
<p class="payment_module">
	<a data-vismapay-button data-selected="{$name}" class="vismapay-button vismapay-{$name}" href="#">
		<img src="{$img_url}{$name}.png" width="45" alt="{$method}"/>
		{$method}
	</a>
</p>
{/foreach}
{foreach from=$vismapay_payment_methods.banks key=name item=method}
<p class="payment_module">
	<a data-vismapay-button data-selected="{$name}" class="vismapay-button vismapay-{$name}" href="#">
		<img src="{$img_url}{$name}.png" width="45" alt="{$method}"/>
		{$method}
	</a>
</p>
{/foreach}
{foreach from=$vismapay_payment_methods.creditinvoices key=name item=method}
<p class="payment_module">
	<a data-vismapay-button data-selected="{$name}" class="vismapay-button vismapay-{$name}" href="#">
		<img src="{$img_url}{$name}.png" width="45" alt="{$method}"/>
		{$method}
	</a>
</p>
{/foreach}
<div id="vismapay_error" style="display:none;"></div>