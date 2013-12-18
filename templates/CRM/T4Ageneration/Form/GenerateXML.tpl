{* HEADER *}
<div class="crm-block crm-form-block">

  <div id="help">{ts}Use this form to generate an XML file for submission to the
    Canadian Revenue Agency.{/ts}</div>

  <table class="form-layout">

    {foreach from=$elementNames item=element}
      <tr>
        <td class="cra-label"><span>{$form.$element.label}
            {if isset($helpElements[$element]) }
              {help id=$element title=$form.$element.label}
            {/if}
          </span>
        </td>
        <td><span>{$form.$element.html}</span></td>
      </tr>
    {/foreach}

  </table>
  {* FOOTER *}
  <div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>
{* Trigger XML download when done generating *}
{if $download}
  <div id ="download">
    <a href={$download} class="download" "style="text-decoration: none;" >Download</a>
  </div>
{/if}
{literal}
<script type="text/javascript">
  var download = "{/literal}{$download}{literal}";
  if ( download ) {
    cj("#download").hide();
    window.location.href = cj(".download").attr('href');
  }
  cj(function() {
    cj().crmAccordions();
  });
</script>
{/literal}