<form action="{if $core.config.robokassa_demo}http://test.robokassa.ru{else}https://merchant.roboxchange.com{/if}/Index.aspx" method="post">
	{foreach $formValues as $key => $value}
		<input type="hidden" name="{$key}" value="{$value}">
	{/foreach}
</form>