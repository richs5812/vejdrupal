(function ($, Drupal, drupalSettings) {
Drupal.behaviors.photos = {
  attach: function(context) {

    if ($("#photos-sortable").length)  {
      $("#photos-sortable").sortable({
        stop: function(event, ui) {
          var pid = drupalSettings.photos.pid;
          var uid = drupalSettings.photos.uid;
          var type = drupalSettings.photos.sort;
          var sortedIDs = $("#photos-sortable").sortable("toArray");
          var sortUrl = drupalSettings.path.baseUrl + 'photos/ajax/rearrange';
          $('#photos-sort-updates').load(sortUrl, { order : sortedIDs, pid : pid, uid : uid, type : type }, function() {
            $('#photos-sort-updates').show();
            $('#photos-sort-updates').delay(500).fadeOut(500);
          });
        }
      });
      $( "#photos-sortable" ).disableSelection();
    }

  }
};
})(jQuery, Drupal, drupalSettings);
