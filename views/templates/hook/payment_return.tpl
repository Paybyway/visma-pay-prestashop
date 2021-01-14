{if $status == 'ok'}
	<p>{l s='Your order is complete.' mod='vismapay'}
		<br /><br /><span class="bold">{l s='Your order will be shipped as soon as possible.' mod='vismapay'}</span>
		<br /><br />{l s='For any questions or for further information, please contact our' mod='vismapay'} <a href="{$link->getPageLink('contact', true)}">{l s='customer support' mod='vismapay'}</a>.
	</p>
{else}
	<p class="warning">{l s='Your payment was not accepted. If you think this is an error, contact our support' mod='vismapay'}</p>
{/if}

