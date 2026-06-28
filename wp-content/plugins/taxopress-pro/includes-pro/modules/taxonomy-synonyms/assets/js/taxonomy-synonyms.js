(function ($) {
  "use strict";

  /**
   * All of the code for your admin-facing JavaScript source
   * should reside in this file.
   *
   * Note: It has been assumed you will write jQuery code here, so the
   * $ function reference has been prepared for usage within the scope
   * of this function.
   *
   * This enables you to define handlers, for when the DOM is ready:
   *
   * $(function() {
   *
   * });
   *
   * When the window is loaded:
   *
   * $( window ).load(function() {
   *
   * });
   *
   * ...and/or other possibilities.
   *
   * Ideally, it is not considered best practise to attach more than a
   * single DOM-ready or window-load handler for a particular page.
   * Although scripts in the WordPress core, Plugins and Themes may be
   * practising this, we should strive to set a better example in our own work.
   */

  $(document).ready(function () {
    $(document).on("keydown", ".taxopress-synonyms-input", function (event) {
      if (event.keyCode == 13) {
        event.preventDefault();
        var input_value = $(this).val();

        if (!isEmptyOrSpaces(input_value)) {
          $(".taxopress-synonyms-input").val("");
          var previous_values = [];
          $("input.term-synonyms").each(function () {
            previous_values.push($(this).val().toLowerCase());
          });
          if (!previous_values.includes(input_value.toLowerCase())) {
            var synonym_list = $(".taxopress-term-synonyms.wrapper");
            var new_synonym = "";
            new_synonym += "<li>";
            new_synonym +=
              '<span class="display-text">' + input_value + "</span>";
            new_synonym +=
              '<span class="remove-synonym"><span class="dashicons dashicons-no-alt"></span></span>';
            new_synonym +=
              '<input type="hidden" class="term-synonyms" name="taxopress_term_synonyms[]" value="' +
              input_value +
              '">';
            new_synonym += "</li>";
            synonym_list.append(new_synonym);
            sortedSynonymsList(synonym_list);
          }
        }

        return false;
      }
    });

    $(document).on(
      "click",
      ".taxopress-term-synonyms.wrapper .remove-synonym",
      function () {
        $(this).closest("li").remove();
      }
    );

    if ($(".taxopress-term-synonyms.wrapper").length > 0) {
      sortedSynonymsList($(".taxopress-term-synonyms.wrapper"));
    }

    function sortedSynonymsList(selector) {
      selector.sortable();
    }

    function isEmptyOrSpaces(str) {
      return str == "" || str === null || str.match(/^ *$/) !== null;
    }

    if ($(".taxopress-term-synonyms.wrapper").length > 0) {
      // Reset Fields on Add New Term 
      let numberOfTags = 0;
      let newNumberOfTags = 0;
    
      // when there are some terms are already created
      if( ! $( '#the-list' ).children( 'tr' ).first().hasClass( 'no-items' ) ) {
        numberOfTags = $( '#the-list' ).children( 'tr' ).length;
      }
    
      // after a term has been added via AJAX	
      $(document).ajaxComplete( function( event, xhr, settings ){
    
        newNumberOfTags = $( '#the-list' ).children('tr').length;
        if( parseInt( newNumberOfTags ) > parseInt( numberOfTags ) ) {
          // refresh the actual number of tags variable
          numberOfTags = newNumberOfTags;
      
          // empty custom fields right here
          $(".taxopress-term-synonyms.wrapper").html('');
        }
      });

      //process a request to prevent synonyms duplicate when editing terms.
      $('body.term-php form#edittag').submit(function (event) {

        var term_synonyms = [];
        var term_id = $('input[name="tag_ID"]').val();
        var edit_form = $(this);

        $("input.term-synonyms").each(function () {
          if ($(this).val() !== '') {
            term_synonyms.push($(this).val().toLowerCase());
          }
        });

        if (term_synonyms.length === 0) {
          edit_form.unbind('submit').submit();
          return true;
        }

        event.preventDefault();

        $('.taxopress-response-notice').remove();

        //prepare ajax data
        var data = {
            action: "duplicate_synonyms_validation",
            term_id: term_id,
            term_synonyms: term_synonyms,
            nonce: synonymsRequestAction.nonce,
        };

        if ($('.taxopress-loading-spinner').length === 0) {
            $('.edit-tag-actions input[type="submit"]').after('<div class="taxopress-loading-spinner spinner is-active" style="float: none;"></div>');
        }

        $('.taxopress-loading-spinner').addClass('is-active');

        $.post(ajaxurl, data, function (response) {
            if (response.status === 'error') {
                $('.edit-tag-actions').after('<div class="taxopress-response-notice notice notice-error" style="margin-top: 10px;"><p> ' + response.content + ' </p></div>');
                $('.taxopress-loading-spinner').removeClass('is-active');
            } else {
              edit_form.unbind('submit').submit();
            }
        });

      });
    }

  });
})(jQuery);
