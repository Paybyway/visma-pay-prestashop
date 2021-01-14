{literal}
<script type="text/javascript">
$( document ).ready(function() {
	var pending = false;
	$("#vismapay_submit").click(function(e){
		e.preventDefault();
		if(pending)
			return false;
		pending = true;
		$("#vismapay_error").hide();
		var query = $.ajax({
			type: 'POST',
			url: '{/literal}{$process_url}{literal}',
			data: {cart_id: '{/literal}{$cart_id}{literal}'},
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
<div class="row">
	<div class="col-xs-12">
		<p class="payment_module">
			<a href="#" class="vismapay-link" id="vismapay_submit">
				{if !empty($banner_url)}
				<img id="vismapay_banner" src="{$banner_url}" alt="Visma Pay"/><br />
				<br style="clear:both;" />
				{/if}				
				{l s='Visma Pay' mod='vismapay'}&nbsp;<span>{l s='(Internet banking, credit card, credit invoice and wallet services.)' mod='vismapay'}</span>
			</a>
		</p>
	</div>
</div>
<div id="vismapay_error" style="display:none;"></div>

