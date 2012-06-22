(function ($) {
  $(document).ready(function() {
    $("#edit-language")
      .change(function() {
        var language = $("#edit-language").val();
        var sourceLanguageSet = language != 'und';
        $('#lingotek_fieldset').drupalSetSummary(Drupal.t(sourceLanguageSet ? 'Enabled' : 'Disabled'));
        //Show all languages for MT Translation
        $('#lingotek_fieldset #edit-mttargets > div').show();
        if (sourceLanguageSet) {
          $('#edit-note').hide();
          $('#edit-content').show();
          $('#lingotek_fieldset .form-item-mtTargets-' + language).hide();
        }
        else {
          $('#edit-note').show();
          $('#edit-content').hide();
        }
      })
      .change();
    $("#edit-mtengine")
      .change(function() {
        var engine = $("#edit-mtengine").val();
        if (engine == '0') {
          $("#lingotek_fieldset .form-item-mtTargets").hide();
        }
        else {
          $("#lingotek_fieldset .form-item-mtTargets").show();
        }
      })
      .change();
  });
})(jQuery);