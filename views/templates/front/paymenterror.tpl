{extends "$layout"}

{block name="content"}
<div>
	<p class="alert alert-warning warning">
	{$vp_error}
	<br/><br/>
	<a href="{$vp_link|escape:'html'}">{l s='Back to cart.' mod='vismapay'}</a>
	</p>
</div>
{/block}