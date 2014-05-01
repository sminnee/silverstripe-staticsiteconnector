(function($) {

	$.entwine('ss', function($) {
	
		// Send AJAX request to admin controller to delete all import data
		$('a.del-imports').entwine({
			onclick: function(e) {
				e.preventDefault();
				$.ajax(jQuery.extend({
					headers: {"X-Pjax" : "CurrentForm"},
					url: $(this).attr('href'), 
					data: {delImports : true},
					type: 'POST',
					success: function(data, status, xhr) {
						$('.cms-container').reloadCurrentPanel();
					}
				}, {}));				
			}
		});
		
	});

}(jQuery));
