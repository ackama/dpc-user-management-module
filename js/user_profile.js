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
  });
})(jQuery);