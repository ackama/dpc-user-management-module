(function ($) {

  $(document).ready(function () {
    /**
     * Verification email sending
     */
    $('.dpc_resend_verification').click(function (event) {
      if ($(this).hasClass('disabled')) {
        return;
      }

      event.preventDefault();
      $(this).addClass('disabled');

      let userId = $(this).attr('data-user-id'),
        email = $(this).attr('data-value'),
        button = $(this);
      $.ajax({
        url: `/send-verification/${userId}/?email=${email}`,
        method: 'GET',
        beforeSend: function () {
          button.html('Sending...');
        },
        success: function (result) {
          button.html('Verification sent!');
        },
        error: function () {
          $(this).removeClass('disabled');
        }
      });
    });

    /**
     * Primary email setting
     */
    let primary_checkboxes = $('#field-email-addresses-values').find('input[name$="[is_primary]"]');

    $(document).on('DOMNodeInserted', function (e) {
      let id = $(e.target).attr('id') ?? '';
      if (id.startsWith('field-email-addresses-add-more-wrapper')) {
        primary_checkboxes = $(e.target).find('input[name$="[is_primary]"]');
      }
    });

    $(document).on('change', primary_checkboxes, function (e) {
      console.log('CHANGE-- ', e.target);
      if ($(e.target).hasClass('password-field') || $(e.target).hasClass('form-text') ) {
        return;
      }

      let primary = $(e.target);
      primary_checkboxes.each(function () {
        if ($(this).attr('id') === primary.attr('id')) {
          $(this).prop('checked', true);
          return;
        }
        $(this).prop('checked', false);
      });
    });
  });
})(jQuery);