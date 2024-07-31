/**
 * @file
 * Check/uncheck all the language checkboxes.
 */

(function ($, Drupal, once) {
  "use strict";

  Drupal.behaviors.languageCheckboxes = {
    attach: function (context) {
      $(once('languageCheckboxes', '.js-language-checkboxes-wrapper', context)).each(function() {
        let $check_all = $('.js-checkbox-all');
        let $check_all_eu = $('.js-checkbox-eu-all');
        let $check_all_non_eu = $('.js-checkbox-non_eu-all');

        /**
         * Checks all the EU language boxes.
         */
        function checkAllEuLanguageBoxes() {
          $('.js-checkbox-eu-language').each(function (e) {
            let $this = $(this);
            if ($this.attr('disabled')) {
              return;
            }

            $this.prop('checked', true);
          });
        }

        /**
         * Unchecks all the EU language boxes.
         */
        function uncheckAllEuLanguageBoxes() {
          $('.js-checkbox-eu-language').each(function (e) {
            let $this = $(this);
            if ($this.attr('disabled')) {
              return;
            }

            $this.prop('checked', false);
          });
        }

        /**
         * Checks all the EU language boxes.
         */
        function checkAllNonEuLanguageBoxes() {
          $('.js-checkbox-non_eu-language').each(function (e) {
            let $this = $(this);
            if ($this.attr('disabled')) {
              return;
            }

            $this.prop('checked', true);
          });
        }

        /**
         * Unchecks all the EU language boxes.
         */
        function uncheckAllNonEuLanguageBoxes() {
          $('.js-checkbox-non_eu-language').each(function (e) {
            let $this = $(this);
            if ($this.attr('disabled')) {
              return;
            }

            $this.prop('checked', false);
          });
        }

        $check_all.change(function (event) {
          if ($(this).is(":checked")) {
            checkAllEuLanguageBoxes();
            checkAllNonEuLanguageBoxes();
            $check_all_eu.prop('checked', true);
            $check_all_non_eu.prop('checked', true);
            $check_all.parent().find('label strong').text('Select none');
          }
          else {
            uncheckAllEuLanguageBoxes();
            uncheckAllNonEuLanguageBoxes();
            $check_all_eu.prop('checked', false);
            $check_all_non_eu.prop('checked', false);
            $check_all.parent().find('label strong').text('Select all');
          }
        });

        $check_all_eu.change(function (event) {
          if ($(this).is(":checked")) {
            checkAllEuLanguageBoxes();
          }
          else {
            uncheckAllEuLanguageBoxes();
          }
        });

        $check_all_non_eu.change(function (event) {
          if ($(this).is(":checked")) {
            checkAllNonEuLanguageBoxes();
          }
          else {
            uncheckAllNonEuLanguageBoxes();
          }
        });

      })
    }
  }
})(jQuery, Drupal, once);
