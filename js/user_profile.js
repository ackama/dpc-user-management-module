(function ($) {

  $(document).ready(function () {
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


    let primary_checkboxes = $('#field-email-addresses-values').find('input[name$="[is_primary]"]');
    console.log(primary_checkboxes);

    $(primary_checkboxes).on('change', function () {
      let primary = $(this);
      primary_checkboxes.each(function() {
        if ($(this).attr('id') === primary.attr('id')) {
          return;
        }
        $(this).prop('checked', false);
      })
    })
  });
})(jQuery);