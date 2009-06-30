<script type="text/javascript">
jQuery(document).ready(function() {
  jQuery('.prettybar-expand').show();
  jQuery('.prettybar-collapse').hide();
  jQuery('.prettybar-toggle-pane').hide();
  jQuery('.prettybar-toggle-button').click(function() {
    jQuery('.prettybar-toggle-pane').toggle();
    jQuery('.prettybar-expand').toggle();
    jQuery('.prettybar-collapse').toggle();
  });

  jQuery('.reporting-expand').show();
  jQuery('.reporting-collapse').hide();
  jQuery('.reporting-toggle-pane').hide();
  jQuery('.reporting-toggle-button').click(function() {
    jQuery('.reporting-toggle-pane').toggle();
    jQuery('.reporting-expand').toggle();
    jQuery('.reporting-collapse').toggle();
  });

  jQuery('.link-expand').show();
  jQuery('.link-collapse').hide();
  jQuery('.link-toggle-pane').hide();
  jQuery('.link-toggle-button').click(function() {
    jQuery('.link-toggle-pane').toggle();
    jQuery('.link-expand').toggle();
    jQuery('.link-collapse').toggle();
  });
});
</script>

<style type="text/css">
.toggle {
  cursor: pointer;
}
</style>
