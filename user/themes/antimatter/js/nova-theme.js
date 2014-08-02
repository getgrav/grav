// ON SCROLL EVENTS
jQuery(document).scroll(function() {
	// Has scrolled class on header
	var value = $(this).scrollTop();
	if ( value > 75 )
		$("#header").addClass("scrolled");
	else
		$("#header").removeClass("scrolled");
});


jQuery(document).ready(function($){

	//Smooth scroll to top
	$('#toTop').click(function(){
		$("html, body").animate({ scrollTop: 0 }, 500);
		return false;
	});
	// Responsive Menu

});


