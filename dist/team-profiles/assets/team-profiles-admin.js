(function ($) {
	$(function () {
		var $list = $('#team-profiles-sortable');

		if (!$list.length) {
			return;
		}

		var $status = $('#team-profiles-order-status');

		$list.sortable({
			handle: '.team-profiles-sortable__handle',
			placeholder: 'team-profiles-sortable__placeholder-row',
			forcePlaceholderSize: true,
			update: function () {
				var order = $list
					.children('.team-profiles-sortable__item')
					.map(function () {
						return $(this).data('id');
					})
					.get();

				saveOrder(order);
			},
		});

		function setStatus(message, type) {
			if (!$status.length) {
				return;
			}

			$status
				.removeClass('notice notice-success notice-error')
				.addClass('notice ' + type)
				.text(message);
		}

		function saveOrder(order) {
			setStatus(TeamProfilesOrder.savingText, 'notice-success');

			$.post(ajaxurl, {
				action: 'team_profiles_save_order',
				nonce: TeamProfilesOrder.nonce,
				order: order,
			})
				.done(function (response) {
					if (response && response.success) {
						setStatus(TeamProfilesOrder.savedText, 'notice-success');
					} else {
						setStatus(TeamProfilesOrder.errorText, 'notice-error');
					}
				})
				.fail(function () {
					setStatus(TeamProfilesOrder.errorText, 'notice-error');
				});
		}
	});
})(jQuery);
