(function ($, Drupal, drupalSettings) {
Drupal.behaviors.photosJeditable = {
  attach: function(context) {

    var atext = [Drupal.t('Save'), Drupal.t('Cancel'), Drupal.t('Being updated...'), Drupal.t('Click to edit')];
    $('.jQueryeditable_edit_title, .jQueryeditable_edit_des').hover(function(){
      $(this).addClass('photos_ajax_hover');
    },function(){
      $(this).removeClass('photos_ajax_hover');
    });

    // Edit image title.
    $('.jQueryeditable_edit_title').editable(drupalSettings.path.baseUrl + 'photos/image/update', {
      loadurl : drupalSettings.path.baseUrl + 'photos/image/update/load',
      type : 'textarea',
      submit : atext[0],
      cancel : atext[1],
      indicator : atext[2],
      tooltip : atext[3],
      loadtype : 'POST',
      loadtext   : Drupal.t('Loading...'),
      submitdata : {},
      callback : function(value, settings) {
        // Success.
        // @todo add option for title selector i.e. #page-title $('#page-title').text(value);
        // @note test on album page (make sure album title does not change).
        $(this).removeClass('photos_ajax_hover');
      }
    }, function(){
      // Cancel.
      $(this).removeClass('photos_ajax_hover');
      return false;
    });
    // Edit image description.
    $('.jQueryeditable_edit_des').editable(drupalSettings.path.baseUrl + 'photos/image/update', {
      loadurl : drupalSettings.path.baseUrl + 'photos/image/update/load',
      height : 140,
      type : 'textarea',
      submit : atext[0],
      cancel : atext[1],
      indicator : atext[2],
      tooltip : atext[3],
      loadtype: 'POST',
      loadtext   : Drupal.t('Loading...'),
      submitdata : {},
      callback : function(value, settings) {
        // Success.
        $(this).removeClass('photos_ajax_hover');
      }
    }, function(){
      // Cancel.
      $(this).removeClass('photos_ajax_hover');
      return false;
    });

  }
};
})(jQuery, Drupal, drupalSettings);
