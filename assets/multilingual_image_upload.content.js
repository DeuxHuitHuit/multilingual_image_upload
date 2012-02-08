(function ($, Symphony, window, undefined) {

	function init() {
		$('.field-multilingual_image_upload').each(function () {
			var field = new MultilingualField($(this));
		});

		if ($('div.file:has(a):has(em)').length === 0) {
			// Upload fields
			$('<em>' + Symphony.Language.get('Remove File') + '</em>').appendTo('div.file:has(a)').click(function (event) {
				var div = $(this).parent(),
				name = div.find('input').attr('name');
				// Prevent clicktrough
				event.preventDefault();
				// Add new empty file input
				div.empty().append('<input name="' + name + '" type="file">');
			});
		}
	}
	
	// wait for DOM to be ready
	$(function () {
		var base = Symphony.WEBSITE + '/extensions/multilingual_image_upload/assets/';
		if (typeof this.MultilingualField !== 'function') {
			$.getScript(base + 'multilingual_image_upload.multilingual-field.js', function () {
				init();
			});
		}
		else {
			init();
		}
	});
}(this.jQuery, this.Symphony, this));