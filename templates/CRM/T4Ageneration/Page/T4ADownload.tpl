<p>Your download will start momentarily.</p>
<a href="{$backLink}">Back.</a>
{* Trigger ZIP download when done generating T4s *}
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