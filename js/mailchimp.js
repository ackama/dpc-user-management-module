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
          button.html('Syncing has started');
          if (Array.isArray(result)) {
            result = $.map(result, function (item) {
              return `<li> ${item} </li>`;
            }).join('');
            result = 'The following will be processed: <ul>' + result + '</ul>';
          }

          $('#mc-sync-result').show().html(result);
        },
        error: function () {
          button.removeClass('is-disabled');
        }
      });
    });
  });
})(jQuery);