<div class=panel>
	<fieldset>
		<legend><img src="{$logo_url}">{l s='Visma Pay payment settlement' mod='vismapay'}</legend>

		{if $message}
		{$message}
		{/if}

		{if $show_button}
		<form action="" method="POST">
			<button type="submit" class="btn btn-primary" id="vismapay_settlement" name="vismapay_settlement">{l s='Settle payment' mod='vismapay'}</button>
		</form>
		<br />
		{/if}
	</fieldset>
</div>
