/**
* @file
* Provides site redirect functionality.
*/

(function ($, Drupal) {
  Drupal.behaviors.human_interval = {
    attach: function (context, settings) {
      let base_url = drupalSettings.base_url;
      let site_id = drupalSettings.site_id;
      // Edit a form.
      if (site_id !== null) {
        $('.field--name-redirect-source span.field-prefix').text(base_url + 'site-' + site_id + '/');
      }
      // Select change event.
      $("#edit-redirect-site").change(function(){
        $('.field--name-redirect-source span.field-prefix').text(base_url + 'site-' + this.value + '/');
      });
    }
  }
})(jQuery, Drupal);
