/*	
 * jQuery mmenu searchfield addon
 * @requires mmenu 4.0.0 or later
 *
 * mmenu.frebsite.nl
 *	
 * Copyright (c) Fred Heusschen
 * www.frebsite.nl
 *
 * Dual licensed under the MIT and GPL licenses.
 * http://en.wikipedia.org/wiki/MIT_License
 * http://en.wikipedia.org/wiki/GNU_General_Public_License
 */


(function( $ ) {

	var _PLUGIN_ = 'mmenu',
		_ADDON_  = 'searchfield';


	$[ _PLUGIN_ ].prototype[ '_addon_' + _ADDON_ ] = function()
	{
		var that = this,
			opts = this.opts[ _ADDON_ ];

		var _c = $[ _PLUGIN_ ]._c,
			_d = $[ _PLUGIN_ ]._d,
			_e = $[ _PLUGIN_ ]._e;

		_c.add( 'search hassearch noresults nosubresults counter' );
		_e.add( 'search reset change' );


		//	Extend options
		if ( typeof opts == 'boolean' )
		{
			opts = {
				add		: opts,
				search	: opts
			};
		}
		if ( typeof opts != 'object' )
		{
			opts = {};
		}
		opts = $.extend( true, {}, $[ _PLUGIN_ ].defaults[ _ADDON_ ], opts );


		//	Add the field
		if ( opts.add )
		{
			$( '<div class="' + _c.search + '" />' )
				.prependTo( this.$menu )
				.append( '<input placeholder="' + opts.placeholder + '" type="text" autocomplete="off" />' );

			if ( opts.noResults )
			{
				$('ul, ol', this.$menu)
					.first()
					.append( '<li class="' + _c.noresults + '">' + opts.noResults + '</li>' );
			}
		}

		if ( $('div.' + _c.search, this.$menu).length )
		{
			this.$menu.addClass( _c.hassearch );
		}

		//	Bind custom events
		if ( opts.search )
		{
			var $input = $('div.' + _c.search, this.$menu).find( 'input' );
			if ( $input.length )
			{
				var $panels = $('.' + _c.panel, this.$menu),
					$labels = $('.' + _c.list + '> li.' + _c.label, this.$menu),
					$items 	= $('.' + _c.list + '> li', this.$menu)
						.not( '.' + _c.subtitle )
						.not( '.' + _c.label )
						.not( '.' + _c.noresults );

				var _searchText = '> a';
				if ( !opts.showLinksOnly )
				{
					_searchText += ', > span';
				}
	
				$input
					.off( _e.keyup + ' ' + _e.change )
					.on( _e.keyup,
						function( e )
						{
							if ( !preventKeypressSearch( e.keyCode ) )
							{
								that.$menu.trigger( _e.search );
							}
						}
					)
					.on( _e.change,
						function( e )
						{
							that.$menu.trigger( _e.search );
						}
					);

				this.$menu
					.off( _e.reset + ' ' + _e.search )
					.on( _e.reset + ' ' + _e.search,
						function( e )
						{
							e.stopPropagation();
						}
					)
					.on( _e.reset,
						function( e )
						{
							that.$menu.trigger( _e.search, [ '' ] );
						}
					)
					.on( _e.search,
						function( e, query )
						{
							if ( typeof query == 'string' )
							{
								$input.val( query );
							}
							else
							{
								query = $input.val();
							}
							query = query.toLowerCase();

							//	Scroll to top
							$panels.scrollTop( 0 );

							//	Search through items
							$items
								.add( $labels )
								.addClass( _c.hidden );

							$items
								.each(
									function()
									{
										var $t = $(this);
										if ( $(_searchText, $t).text().toLowerCase().indexOf( query ) > -1 )
										{
											$t.add( $t.prevAll( '.' + _c.label ).first() ).removeClass( _c.hidden );
										}
									}
								);

							//	Update parent for submenus
							$( $panels.get().reverse() ).each(
								function()
								{
									var $t = $(this),
										$p = $t.data( _d.parent );
		
									if ( $p )
									{
										var $i = $t.add( $t.find( '> .' + _c.list ) ).find( '> li' )
											.not( '.' + _c.subtitle )
											.not( '.' + _c.label )
											.not( '.' + _c.hidden );

										if ( $i.length )
										{
											$p.removeClass( _c.hidden )
												.removeClass( _c.nosubresults )
												.prevAll( '.' + _c.label ).first().removeClass( _c.hidden );
										}
										else
										{
											if ( $t.hasClass( _c.current ) )
											{
												$p.trigger( _e.open );
											}
											$p.addClass( _c.nosubresults );
										}
									}
								}
							);

							//	Show/hide no results message
							that.$menu[ $items.not( '.' + _c.hidden ).length ? 'removeClass' : 'addClass' ]( _c.noresults );

							//	Update for other addons
							that.$menu.trigger( _e.update );
						}
					);
			}
		}
	};

	$[ _PLUGIN_ ].defaults[ _ADDON_ ] = {
		add				: false,
		search			: false,
		showLinksOnly	: true,
		placeholder		: 'Search',
		noResults		: 'No results found.'
	};


	//	Add to plugin
	$[ _PLUGIN_ ].addons = $[ _PLUGIN_ ].addons || [];
	$[ _PLUGIN_ ].addons.push( _ADDON_ );


	//	Functions
	function preventKeypressSearch( c )
	{
		switch( c )
		{
			case 9:		//	tab
			case 16:	//	shift
			case 17:	//	control
			case 18:	//	alt
			case 37:	//	left
			case 38:	//	top
			case 39:	//	right
			case 40:	//	bottom
				return true;
		}
		return false;
	}

})( jQuery );