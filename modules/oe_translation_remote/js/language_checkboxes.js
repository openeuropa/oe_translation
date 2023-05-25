/**
 * @file
 * Check/uncheck all the language checkboxes.
 */

(function ($, Drupal, once) {
  "use strict";

  Drupal.behaviors.languageCheckboxes = {
    attach: function (context) {
      $(once('languageCheckboxes', '.js-language-checkboxes-wrapper', context)).each(function() {
        var $check_all = $('.js-checkbox-all');

        /**
         * Checks all the language boxes.
         */
        function checkAllLanguageBoxes() {
          $('.js-checkbox-language').each(function (e) {
            var $this = $(this);
            if ($this.attr('disabled')) {
              return;
            }

            $this.prop('checked', true);
          })
        }

        /**
         * Unchecks all the language boxes.
         */
        function uncheckAllLanguageBoxes() {
          $('.js-checkbox-language').each(function (e) {
            var $this = $(this);
            if ($this.attr('disabled')) {
              return;
            }

            $this.prop('checked', false);
          })
        }

        $check_all.change(function (event) {
          if ($(this).is(":checked")) {
            checkAllLanguageBoxes();
            $check_all.parent().find('label strong').text('Select none');
          }
          else {
            uncheckAllLanguageBoxes();
            $check_all.parent().find('label strong').text('Select all');
          }
        })

      })
    }
  }
})(jQuery, Drupal, once);
