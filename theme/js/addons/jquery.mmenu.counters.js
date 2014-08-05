/*	
 * jQuery mmenu counters addon
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
		_ADDON_  = 'counters';


	$[ _PLUGIN_ ].prototype[ '_addon_' + _ADDON_ ] = function()
	{
		var that = this,
			opts = this.opts[ _ADDON_ ];

		var _c = $[ _PLUGIN_ ]._c,
			_d = $[ _PLUGIN_ ]._d,
			_e = $[ _PLUGIN_ ]._e;

		_c.add( 'counter noresults' );
		_e.add( 'updatecounters' );


		//	Extend options
		if ( typeof opts == 'boolean' )
		{
			opts = {
				add		: opts,
				update	: opts
			};
		}
		if ( typeof opts != 'object' )
		{
			opts = {};
		}
		opts = $.extend( true, {}, $[ _PLUGIN_ ].defaults[ _ADDON_ ], opts );


		//	DEPRECATED
		if ( opts.count )
		{
			$[ _PLUGIN_ ].deprecated( 'the option "count" for counters, the option "update"' );
			opts.update = opts.count;
		}
		//	/DEPRECATED


		//	Refactor counter class
		this.__refactorClass( $('em.' + this.conf.counterClass, this.$menu), 'counter' );

		var $panels = $('.' + _c.panel, this.$menu);

		//	Add the counters
		if ( opts.add )
		{
			$panels.each(
				function()
				{
					var $t = $(this),
						$p = $t.data( _d.parent );
	
					if ( $p )
					{
						var $c = $( '<em class="' + _c.counter + '" />' ),
							$a = $p.find( '> a.' + _c.subopen );

						if ( !$a.parent().find( 'em.' + _c.counter ).length )
						{
							$a.before( $c );
						}
					}
				}
			);
		}

		//	Bind custom events
		if ( opts.update )
		{
			var $counters = $('em.' + _c.counter, this.$menu);

			$counters
				.off( _e.updatecounters )
				.on( _e.updatecounters,
					function( e )
					{
						e.stopPropagation();
					}
				)
				.each(
					function()
					{
						var $counter = $(this),
							$sublist = $($counter.next().attr( 'href' ), that.$menu);
	
						if ( !$sublist.is( '.' + _c.list ) )
						{
							$sublist = $sublist.find( '> .' + _c.list );
						}
	
						if ( $sublist.length )
						{
							$counter
								.on( _e.updatecounters,
									function( e )
									{
										var $lis = $sublist.children()
											.not( '.' + _c.label )
											.not( '.' + _c.subtitle )
											.not( '.' + _c.hidden )
											.not( '.' + _c.noresults );

										$counter.html( $lis.length );
									}
								);
						}
					}
				)
				.trigger( _e.updatecounters );

			//	Update with menu-update
			this.$menu
				.on( _e.update,
					function( e )
					{
						$counters.trigger( _e.updatecounters );
					}
				);
		}
	};

	$[ _PLUGIN_ ].defaults[ _ADDON_ ] = {
		add		: false,
		update	: false
	};
	$[ _PLUGIN_ ].configuration.counterClass = 'Counter';


	//	Add to plugin
	$[ _PLUGIN_ ].addons = $[ _PLUGIN_ ].addons || [];
	$[ _PLUGIN_ ].addons.push( _ADDON_ );

})( jQuery );