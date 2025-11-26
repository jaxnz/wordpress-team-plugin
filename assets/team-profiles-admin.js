(function ($) {
	$(function () {
		var $list = $('#team-profiles-sortable');
		var $status = $('#team-profiles-order-status');
		var $form = $('.team-profiles-form');
		var $memberId = $('#team-profiles-member-id');
		var $name = $('#team-profiles-name');
		var $qualification = $('#team-profiles-qualification');
		var $blurb = $('#team-profiles-blurb');
		var $photoId = $('#team-profiles-photo-id');
		var $photoPreview = $('#team-profiles-photo-preview');
		var $btnNew = $('#team-profiles-new');
		var $btnSelectPhoto = $('#team-profiles-photo-select');
		var $btnRemovePhoto = $('#team-profiles-photo-remove');
		var cfg =
			window.TeamProfilesOrder ||
			{
				nonce: '',
				savingText: 'Saving...',
				savedText: 'Saved.',
				errorText: 'Error saving order.',
				photoFrameTitle: 'Select photo',
				photoFrameButton: 'Use photo',
			};
		var mediaFrame = null;
		var wpMedia = window.wp && window.wp.media ? window.wp.media : null;

		if ($list.length) {
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
		}

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
			setStatus(cfg.savingText, 'notice-success');

			$.post(ajaxurl, {
				action: 'team_profiles_save_order',
				nonce: cfg.nonce,
				order: order,
			})
				.done(function (response) {
					if (response && response.success) {
						setStatus(cfg.savedText, 'notice-success');
					} else {
						setStatus(cfg.errorText, 'notice-error');
					}
				})
				.fail(function () {
					setStatus(cfg.errorText, 'notice-error');
				});
		}

		function resetForm() {
			$memberId.val('');
			$name.val('');
			$qualification.val('');
			$blurb.val('');
			$photoId.val('');
			updatePreview('');
			$('#team-profiles-submit').text('Save Member');
		}

		function updatePreview(src) {
			if (src) {
				$photoPreview
					.removeClass('team-profiles-photo__placeholder')
					.css('background-image', 'url(' + src + ')');
			} else {
				$photoPreview
					.addClass('team-profiles-photo__placeholder')
					.css('background-image', 'none');
			}
		}

		function loadMember($item) {
			$memberId.val($item.data('id'));
			$name.val($item.data('name'));
			$qualification.val($item.data('qualification'));
			$blurb.val($item.data('blurb'));
			$photoId.val($item.data('photo-id'));
			updatePreview($item.data('photo-src'));
			$('#team-profiles-submit').text('Update Member');
			window.scrollTo({ top: $form.offset().top - 20, behavior: 'smooth' });
		}

		$list.on('click', '.team-profiles-edit', function () {
			var $item = $(this).closest('.team-profiles-sortable__item');
			loadMember($item);
		});

		$btnNew.on('click', function () {
			resetForm();
		});

		$btnSelectPhoto.on('click', function (e) {
			e.preventDefault();

			if (!wpMedia) {
				return;
			}

			if (mediaFrame) {
				mediaFrame.open();
				return;
			}

			mediaFrame = wpMedia({
				title: cfg.photoFrameTitle,
				button: { text: cfg.photoFrameButton },
				multiple: false,
			});

			mediaFrame.on('select', function () {
				var attachment = mediaFrame.state().get('selection').first().toJSON();
				$photoId.val(attachment.id);
				updatePreview(attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url);
			});

			mediaFrame.open();
		});

		$btnRemovePhoto.on('click', function (e) {
			e.preventDefault();
			$photoId.val('');
			updatePreview('');
		});

		// Initialize preview placeholder on load.
		updatePreview($photoPreview.data('src'));
	});
})(jQuery);
