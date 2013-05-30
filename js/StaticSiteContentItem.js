(function($) {
	$('.readonly-click-toggle span').entwine({

		RawVal: null,
		ShowingRaw: true,

		onclick: function() {
			if(!$(this).getRawVal()) {
				$(this).setRawVal($(this).text());
			}

			if($(this).getShowingRaw()) {
				$(this).html($(this).getRawVal());
				$(this).setShowingRaw(false);
			} else {
				$(this).text($(this).getRawVal());
				$(this).setShowingRaw(true);
			}

		}
	});

}(jQuery));