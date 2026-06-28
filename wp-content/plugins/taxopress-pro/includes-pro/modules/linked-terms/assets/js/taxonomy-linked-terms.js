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

    // Enter should not submit form
    $(document).on("keydown", ".taxopress-linked-terms-input", function (event) {
        if (event.keyCode == 13) {
          event.preventDefault();
          //enter should not submit form
            event.preventDefault();
            return false;
        }
    });

    // Enter should not submit form
    $(document).on("change", ".taxopress-linked-terms-input", function (event) {
      //manual changed not allowed but autocomplete value
      $(this).val('');
    });

    // Remove linked terms
    $(document).on(
      "click",
      ".taxopress-term-linked-terms.wrapper .remove-linked_term",
      function () {
        $(this).closest("li").remove();
      }
    );

    if ($(".taxopress-term-linked-terms.wrapper").length > 0) {
      // Make linked term sortable
      sortedSynonymsList($(".taxopress-term-linked-terms.wrapper"));

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
          $(".taxopress-term-linked-terms.wrapper").html('');
        }
      });
    }

    // Linked term auto complete init
    st_init_linked_terms_autocomplete('.linked-term-autocomplete-input', ajaxurl + '?action=simpletags_autocomplete&stags_action=helper_js_collection&taxonomy=linked_term_taxonomies&exclude_term=' + linkedTermsRequestAction.term_id, 0);


    function sortedSynonymsList(selector) {
      selector.sortable();
    }

    function st_init_linked_terms_autocomplete (p_target, p_url, p_min_chars) {
      // Dynamic width ?
      var p_width = Number($('' + p_target).width());
      if (p_width === 0) {
        p_width = 200
      }
      // Init jQuery UI autocomplete
      $(p_target).bind('keydown', function (event) {
        // don't navigate away from the field on tab when selecting an item
        if (event.keyCode === $.ui.keyCode.TAB &&
          $(this).data('ui-autocomplete').menu.active) {
          event.preventDefault()
        }
      }).autocomplete({
        minLength: p_min_chars,
        source: function (request, response) {
          $.getJSON(p_url, {
            term: st_linked_terms_extract_last(request.term)
          }, response)
        },
        focus: function () {
          // prevent value inserted on focus
          return false
        },
        select: function (event, ui) {
          // clear input
          this.value = '';
          // add selected term
          var selected_id = ui.item.id;
          var selected_name = ui.item.name;
          var selected_taxonomy = ui.item.taxonomy;

          if ($('.taxopress-term-li.' + selected_taxonomy + '-' + selected_id).length == 0) {
            var linked_term_list = $(".taxopress-term-linked-terms.wrapper");
            var new_linked_term = "";
            new_linked_term += '<li class="taxopress-term-li ' + selected_taxonomy + '-' + selected_id +'">';
            new_linked_term +=
              '<span class="display-text">' + selected_name + ' (' + selected_taxonomy + ')</span>';
            new_linked_term +=
              '<span class="remove-linked_term"><span class="dashicons dashicons-no-alt"></span></span>';
            new_linked_term +=
              '<input type="hidden" class="term-linked-terms id" name="taxopress_linked_term_id[]" value="' +
              selected_id +
              '">';
            new_linked_term +=
              '<input type="hidden" class="term-linked-terms name" name="taxopress_linked_term_name[]" value="' +
              selected_name +
              '">';
            new_linked_term +=
              '<input type="hidden" class="term-linked-terms taxonomy" name="taxopress_linked_term_taxonomy[]" value="' +
              selected_taxonomy +
              '">';
            new_linked_term += "</li>";
            linked_term_list.append(new_linked_term);
            sortedSynonymsList(linked_term_list);
          }

          return false
        }
      })
    }
    function st_linked_terms_split (val) {
      return val.split(/,\s*/)
    }

    function st_linked_terms_extract_last (term) {
      return st_linked_terms_split(term).pop()
    }

  });
})(jQuery);
