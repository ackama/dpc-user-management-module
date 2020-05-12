(function ($) {

  $(document).ready(function () {
    $('#dpc_resync_mc').click(function (event) {
      let button = $(this);
      if (button.hasClass('is-disabled')) {
        return;
      }

      event.preventDefault();
      button.addClass('is-disabled');

      $.ajax({
        url: '/dpc_mailchimp/sync',
        method: 'GET',
        beforeSend: function () {
          button.html('Processing...');
        },
        success: function (result) {
          console.log(result);
          button.html('Verification sent!');
        },
        error: function () {
          button.removeClass('is-disabled');
        }
      });
    });
  });
})(jQuery);