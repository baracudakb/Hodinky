<p class="payment_module">
	<a href="{$this_path_ssl}validation.php" title="{l s='Cash on delivery (COD)' mod='cashondeliverywithfee'}">
		<img src="{$this_path}cashondelivery.jpg" alt="{l s='Cash on delivery (COD)' mod='cashondeliverywithfee'}" />
		{if $isFee}
                    {l s='Cash on delivery (COD) : you pay for the merchandise upon delivery, additional cost: ' mod='cashondeliverywithfee'}
                    {convertPrice price=$fee}
                {else}
                    {l s='You pay for the merchandise upon delivery' mod='cashondeliverywithfee'}
                {/if}
                </a>
</p>