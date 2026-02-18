(function ($) {
	'use strict';

	$(function () {

		/* ── Manual Refresh ── */

		$('#tsm-erh-refresh-btn').on('click', function () {
			var $btn    = $(this);
			var $status = $('#tsm-erh-refresh-status');
			var $icon   = $btn.find('.dashicons');

			$btn.prop('disabled', true);
			$icon.addClass('spinning');
			$status.text(tsmErh.strings.refreshing).removeClass('success error');

			$.ajax({
				url:  tsmErh.ajaxUrl,
				type: 'POST',
				data: {
					action: 'tsm_erh_refresh_rates',
					nonce:  tsmErh.nonce
				},
				success: function (response) {
					if (response.success) {
						$status.text(tsmErh.strings.success).addClass('success');
						if (response.data && response.data.last_updated) {
							$('#tsm-erh-last-updated').text(response.data.last_updated);
						}
						setTimeout(function () { location.reload(); }, 1200);
					} else {
						var msg = (response.data && response.data.message)
							? response.data.message
							: tsmErh.strings.error;
						$status.text(msg).addClass('error');
					}
				},
				error: function () {
					$status.text(tsmErh.strings.error).addClass('error');
				},
				complete: function () {
					$btn.prop('disabled', false);
					$icon.removeClass('spinning');
				}
			});
		});

		/* ── Select / Deselect All Currencies ── */

		$('#tsm-erh-select-all').on('click', function () {
			$('.tsm-erh-currency-checkbox input[type="checkbox"]').prop('checked', true);
		});

		$('#tsm-erh-deselect-all').on('click', function () {
			$('.tsm-erh-currency-checkbox input[type="checkbox"]').prop('checked', false);
		});
	});

})(jQuery);
