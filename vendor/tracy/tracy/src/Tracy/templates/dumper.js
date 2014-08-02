/**
 * Dumper
 *
 * This file is part of the Tracy (http://tracy.nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

(function(){

	var $ = Tracy.Query.factory;

	var Dumper = Tracy.Dumper = {};

	Dumper.init = function() {
		$(document.body).bind('click', function(e) {
			var link;

			// enables <span data-tracy-href=""> & ctrl key
			for (link = e.target; link && (!link.getAttribute || !link.getAttribute('data-tracy-href')); link = link.parentNode) {}
			if (e.ctrlKey && link) {
				location.href = link.getAttribute('data-tracy-href');
				return false;
			}

			if (e.shiftKey || e.altKey || e.ctrlKey || e.metaKey) {
				return;
			}

			// enables <a class="tracy-toggle" href="#"> or <span data-ref="#"> toggling
			link = $(e.target).closest('.tracy-toggle');
			if (!link.length) {
				return;
			}
			var collapsed = link.hasClass('tracy-collapsed'),
				ref = link[0].getAttribute('data-ref') || link[0].getAttribute('href', 2),
				dest = ref && ref !== '#' ? $(ref) : link.next(''),
				panel = link.closest('.tracy-panel'),
				oldPosition = panel.position();

			link[collapsed ? 'removeClass' : 'addClass']('tracy-collapsed');
			dest[collapsed ? 'removeClass' : 'addClass']('tracy-collapsed');
			e.preventDefault();

			if (panel.length) {
				var newPosition = panel.position();
				panel.position({
					right: newPosition.right - newPosition.width + oldPosition.width,
					bottom: newPosition.bottom - newPosition.height + oldPosition.height
				});
			}
		});
	};

})();
