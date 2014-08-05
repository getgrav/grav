/*	
 * jQuery mmenu labels addon
 * @requires mmenu 4.1.0 or later
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
		_ADDON_  = 'labels';


	$[ _PLUGIN_ ].prototype[ '_addon_' + _ADDON_ ] = function()
	{
		var that = this,
			opts = this.opts[ _ADDON_ ];

		var _c = $[ _PLUGIN_ ]._c,
			_d = $[ _PLUGIN_ ]._d,
			_e = $[ _PLUGIN_ ]._e;

		_c.add( 'collapsed' );

		_c.add( 'fixedlabels original clone' );
		_e.add( 'updatelabels position scroll' );
		if ( $[ _PLUGIN_ ].support.touch )
		{
			_e.scroll += ' ' + _e.mm( 'touchmove' );
		}


		//	Extend options
		if ( typeof opts == 'boolean' )
		{
			opts = {
				collapse: opts
			};
		}
		if ( typeof opts != 'object' )
		{
			opts = {};
		}
		opts = $.extend( true, {}, $[ _PLUGIN_ ].defaults[ _ADDON_ ], opts );


		//	Toggle collapsed labels
		if ( opts.collapse )
		{

			//	Refactor collapsed class
			this.__refactorClass( $('li.' + this.conf.collapsedClass, this.$menu), 'collapsed' );

			var $labels = $('.' + _c.label, this.$menu);

			$labels
				.each(
					function()
					{
						var $label = $(this),
							$expan = $label.nextUntil( '.' + _c.label, ( opts.collapse == 'all' ) ? null : '.' + _c.collapsed );

						if ( opts.collapse == 'all' )
						{
							$label.addClass( _c.opened );
							$expan.removeClass( _c.collapsed );
						}

						if ( $expan.length )
						{
							$label.wrapInner( '<span />' );

							$('<a href="#" class="' + _c.subopen + ' ' + _c.fullsubopen + '" />')
								.prependTo( $label )
								.on(
									_e.click,
									function( e )
									{
										e.preventDefault();
		
										$label.toggleClass( _c.opened );
										$expan[ $label.hasClass( _c.opened ) ? 'removeClass' : 'addClass' ]( _c.collapsed );
									}
								);
						}
					}
				);
		}

		//	Fixed labels
		else if ( opts.fixed )
		{
			if ( this.direction != 'horizontal' )
			{
				return;
			}

			this.$menu.addClass( _c.fixedlabels );

			var $panels = $('.' + _c.panel, this.$menu),
				$labels = $('.' + _c.label, this.$menu);

			$panels.add( $labels )
				.off( _e.updatelabels + ' ' + _e.position + ' ' +  _e.scroll )
				.on( _e.updatelabels + ' ' + _e.position + ' ' +  _e.scroll,
					function( e )
					{
						e.stopPropagation();
					}
				);

			var offset = getPanelsOffset();

			$panels.each(
				function()
				{
					var $panel 	= $(this),
						$labels = $panel.find( '.' + _c.label );

					if ( $labels.length )
					{
						var scrollTop = $panel.scrollTop();

						$labels.each(
							function()
							{
								var $label	= $(this);

								//	Add extra markup
								$label
									.wrapInner( '<div />' )
									.wrapInner( '<div />' );

								var $inner = $label.find( '> div' ),
									$next	= $();

								var top, bottom, height;

								//	Update appearences
								$label
									.on( _e.updatelabels,
										function( e )
										{
											scrollTop = $panel.scrollTop();

											if ( !$label.hasClass( _c.hidden ) )
											{
												$next	= $label.nextAll( '.' + _c.label ).not( '.' + _c.hidden ).first();
												top 	= $label.offset().top + scrollTop;
												bottom	= $next.length ? $next.offset().top + scrollTop : false;
												height	= $inner.height();

												$label.trigger( _e.position );
											}
										}
									);
								
								//	Set position
								$label
									.on( _e.position,
										function( e )
										{
											var _top = 0;
											if ( bottom && scrollTop + offset > bottom - height )
											{
												_top = bottom - top - height;
											}
											else if ( scrollTop + offset > top )
											{
												_top = scrollTop - top + offset;
											}
											$inner.css( 'top', _top );
										}
									);
							}
						);

						//	Bind update and scrolling events
						$panel
							.on( _e.updatelabels,
								function( e )
								{
									scrollTop = $panel.scrollTop();
									offset = getPanelsOffset();
									$labels.trigger( _e.position );
								}
							)
							.on( _e.scroll,
								function( e )
								{
									$labels.trigger( _e.updatelabels );
								}
							);
					}
				}
			);

			//	Update with menu-update
			this.$menu
				.on( _e.update,
					function( e )
					{
						$panels
							.trigger( _e.updatelabels );
					}
				)
				.on( _e.opening,
					function( e )
					{
						$panels
							.trigger( _e.updatelabels )
							.trigger( _e.scroll );
					}
				);
		}
		
		function getPanelsOffset()
		{
			var hassearch	= _c.hassearch && that.$menu.hasClass( _c.hassearch ),
				hasheader	= _c.hasheader && that.$menu.hasClass( _c.hasheader );

			return hassearch
				? hasheader
					? 100
					: 50
				: hasheader
					? 60
					: 0;
		}
	};

	$[ _PLUGIN_ ].defaults[ _ADDON_ ] = {
		fixed		: false,
		collapse	: false
	};
	$[ _PLUGIN_ ].configuration.collapsedClass = 'Collapsed';


	//	Add to plugin
	$[ _PLUGIN_ ].addons = $[ _PLUGIN_ ].addons || [];
	$[ _PLUGIN_ ].addons.push( _ADDON_ );


})( jQuery );