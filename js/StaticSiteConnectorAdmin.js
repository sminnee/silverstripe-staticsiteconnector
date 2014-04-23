(function($) {

	$.entwine('ss', function($) {
	
		// Send AJAX request to admin controller to delete all import data
		$('a.del-imports').entwine({
			onclick: function(e) {
				e.preventDefault();
				// send "delImports" POST var
				var url = $(this).attr('href');
				var data = {delImports:true};
				$.post(url, data);				
				// Reload main content area
				$('.cms-container').reloadCurrentPanel();
			}
		});
		
	});

}(jQuery));
