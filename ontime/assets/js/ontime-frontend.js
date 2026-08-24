/**
 * OnTime — Frontend booking form JS (Stage 4).
 * Pure Vanilla JS, no jQuery. Step navigation + Jalali calendar grid + AJAX.
 */

( function () {
	'use strict';

	if ( typeof window.OnTime === 'undefined' ) { window.OnTime = {}; }

	var widget = null;
	var state = {
		step: 1,
		serviceId: 0,
		serviceName: '',
		servicePrice: '0',
		jYear: 0,
		jMonth: 0,
		jDay: 0,
		slotTs: 0,
		slotLabel: ''
	};

	var DATA = ( typeof window.OnTimeData !== 'undefined' ) ? window.OnTimeData : {
		ajaxUrl: '',
		nonce: '',
		i18n: {}
	};

	function $( sel, ctx ) { return ( ctx || widget ).querySelector( sel ); }
	function $all( sel, ctx ) { return Array.prototype.slice.call( ( ctx || widget ).querySelectorAll( sel ) ); }

	function t( key ) { return ( DATA.i18n && DATA.i18n[ key ] ) ? DATA.i18n[ key ] : key; }

	// Persian month names (mirror of PHP).
	var MONTHS = [ 'فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند' ];

	function init() {
		widget = document.querySelector( '.ontime-widget' );
		if ( ! widget ) { return; }
		state.serviceId = parseInt( widget.getAttribute( 'data-service' ) || '0', 10 );

		bindNav();
		goStep( 1 );
		if ( state.serviceId > 0 ) { loadServices(); } else { loadServices(); }
	}

	function bindNav() {
		var next = $( '#ontime-next' );
		var prev = $( '#ontime-prev' );
		if ( next ) { next.addEventListener( 'click', nextStep ); }
		if ( prev ) { prev.addEventListener( 'click', prevStep ); }

		var prevMonth = $( '#ontime-prev-month' );
		var nextMonth = $( '#ontime-next-month' );
		if ( prevMonth ) { prevMonth.addEventListener( 'click', function () { shiftMonth( -1 ); } ); }
		if ( nextMonth ) { nextMonth.addEventListener( 'click', function () { shiftMonth( 1 ); } ); }
	}

	// --- AJAX helper ---
	function ajax( action, payload, cb ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', DATA.nonce );
		Object.keys( payload ).forEach( function ( k ) {
			body.append( k, payload[ k ] );
		} );

		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', DATA.ajaxUrl, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8' );
		xhr.onload = function () {
			try {
				var res = JSON.parse( xhr.responseText );
				cb( res );
			} catch ( e ) {
				cb( { success: false, data: { message: t( 'error' ) } } );
			}
		};
		xhr.onerror = function () { cb( { success: false, data: { message: t( 'error' ) } } ); };
		xhr.send( body.toString() );
	}

	// --- Step 1: services ---
	function loadServices() {
		var list = $( '#ontime-services' );
		if ( ! list ) { return; }
		list.classList.add( 'ontime-loading' );
		ajax( 'ontime_get_services', {}, function ( res ) {
			list.classList.remove( 'ontime-loading' );
			if ( ! res || ! res.success ) {
				list.innerHTML = '<p class="ontime-result ontime-err">' + esc( ( res && res.data && res.data.message ) || t( 'error' ) ) + '</p>';
				return;
			}
			var services = ( res.data && res.data.services ) || [];
			if ( ! services.length ) {
				list.innerHTML = '<p>' + esc( t( 'noSlots' ) ) + '</p>';
				return;
			}
			list.innerHTML = '';
			services.forEach( function ( s ) {
				var card = document.createElement( 'div' );
				card.className = 'ontime-service-card';
				card.setAttribute( 'data-id', s.id );
				card.innerHTML = '<div class="ontime-svc-name">' + esc( s.name ) + '</div>'
					+ '<div class="ontime-svc-meta">' + esc( s.duration + ' دقیقه' ) + ' • ' + esc( s.price ) + ' تومان</div>';
				card.addEventListener( 'click', function () {
					$all( '.ontime-service-card' ).forEach( function ( c ) { c.classList.remove( 'selected' ); } );
					card.classList.add( 'selected' );
					state.serviceId = s.id;
					state.serviceName = s.name;
					state.servicePrice = s.price;
				} );
				list.appendChild( card );
			} );
			if ( state.serviceId > 0 ) {
				var pre = list.querySelector( '.ontime-service-card[data-id="' + state.serviceId + '"]' );
				if ( pre ) {
					pre.classList.add( 'selected' );
				}
			}
		} );
	}

	// --- Step 2: Jalali calendar grid ---
	function renderCalendar() {
		var now = new Date();
		// Initialize to current Jalali month if not set.
		if ( state.jYear === 0 ) {
			var approx = jalaliOf( now );
			state.jYear = approx.year;
			state.jMonth = approx.month;
		}
		var label = $( '#ontime-month-label' );
		if ( label ) { label.textContent = MONTHS[ state.jMonth - 1 ] + ' ' + toPersian( state.jYear ); }

		var grid = $( '#ontime-cal-grid' );
		if ( ! grid ) { return; }
		grid.innerHTML = '';

		var days = jDaysInMonth( state.jYear, state.jMonth );
		var firstWeekday = jFirstWeekday( state.jYear, state.jMonth ); // PHP w: 0=Sun..6=Sat

		// Empty leading cells.
		for ( var i = 0; i < firstWeekday; i++ ) {
			var e = document.createElement( 'div' );
			e.className = 'ontime-cal-cell empty';
			grid.appendChild( e );
		}

		for ( var d = 1; d <= days; d++ ) {
			var cell = document.createElement( 'div' );
			cell.className = 'ontime-cal-cell';
			cell.textContent = toPersian( d );
			( function ( day ) {
				cell.addEventListener( 'click', function () { selectDay( day, cell ); } );
			} )( d );
			grid.appendChild( cell );
		}

		// Mark selected.
		if ( state.jDay > 0 ) {
			var sel = grid.children[ firstWeekday + state.jDay - 1 ];
			if ( sel ) { sel.classList.add( 'selected' ); }
		}
	}

	function shiftMonth( delta ) {
		state.jMonth += delta;
		if ( state.jMonth > 12 ) { state.jMonth = 1; state.jYear++; }
		if ( state.jMonth < 1 ) { state.jMonth = 12; state.jYear--; }
		state.jDay = 0;
		renderCalendar();
	}

	function selectDay( day, cell ) {
		$all( '.ontime-cal-cell' ).forEach( function ( c ) { c.classList.remove( 'selected' ); } );
		cell.classList.add( 'selected' );
		state.jDay = day;
	}

	// --- Step 3: slots ---
	function loadSlots() {
		var box = $( '#ontime-slots' );
		if ( ! box ) { return; }
		box.classList.add( 'ontime-loading' );
		box.innerHTML = '';
		ajax( 'ontime_get_slots', {
			service_id: state.serviceId,
			j_year: state.jYear,
			j_month: state.jMonth,
			j_day: state.jDay
		}, function ( res ) {
			box.classList.remove( 'ontime-loading' );
			if ( ! res || ! res.success ) {
				box.innerHTML = '<p class="ontime-result ontime-err">' + esc( ( res && res.data && res.data.message ) || t( 'error' ) ) + '</p>';
				return;
			}
			var slots = ( res.data && res.data.slots ) || [];
			if ( ! slots.length ) {
				box.innerHTML = '<p>' + esc( t( 'noSlots' ) ) + '</p>';
				return;
			}
			box.innerHTML = '';
			slots.forEach( function ( s ) {
				var btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'ontime-slot';
				btn.textContent = s.label;
				btn.setAttribute( 'data-ts', s.ts );
				btn.addEventListener( 'click', function () {
					$all( '.ontime-slot' ).forEach( function ( b ) { b.classList.remove( 'selected' ); } );
					btn.classList.add( 'selected' );
					state.slotTs = s.ts;
					state.slotLabel = s.label;
				} );
				box.appendChild( btn );
			} );
		} );
	}

	// --- Step 5: summary + submit ---
	function renderSummary() {
		var s = $( '#ontime-summary' );
		if ( ! s ) { return; }
		s.innerHTML = '<strong>' + esc( state.serviceName ) + '</strong><br>'
			+ esc( t( 'loading' ).replace( '...', '' ) + ': ' ) + esc( state.slotLabel ) + '<br>'
			+ esc( 'قیمت: ' ) + esc( state.servicePrice ) + ' تومان';
	}

	function submitBooking( cb ) {
		var form = $( '#ontime-customer-form' );
		var name = form ? form.elements.customer_name.value : '';
		var phone = form ? form.elements.customer_phone.value : '';
		var email = form ? form.elements.customer_email.value : '';
		var notes = form ? form.elements.notes.value : '';

		// Client-side validation.
		if ( ! name ) { cb( { success: false, data: { message: t( 'nameRequired' ) } } ); return; }
		if ( ! phone ) { cb( { success: false, data: { message: t( 'phoneRequired' ) } } ); return; }
		if ( email && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email ) ) { cb( { success: false, data: { message: t( 'emailInvalid' ) } } ); return; }

		ajax( 'ontime_submit_booking', {
			service_id: state.serviceId,
			slot_ts: state.slotTs,
			customer_name: name,
			customer_phone: phone,
			customer_email: email,
			notes: notes
		}, cb );
	}

	// --- Step navigation ---
	function goStep( n ) {
		state.step = n;
		$all( '.ontime-step' ).forEach( function ( el ) { el.classList.add( 'ontime-hidden' ); } );
		var cur = $( '.ontime-step[data-step="' + n + '"]' );
		if ( cur ) { cur.classList.remove( 'ontime-hidden' ); }

		// Lazy-load per step.
		if ( 2 === n ) { renderCalendar(); }
		if ( 3 === n ) { loadSlots(); }
		if ( 5 === n ) { renderSummary(); }

		// Progress bar.
		var bar = $( '#ontime-progress-bar' );
		if ( bar ) { bar.style.width = ( n * 20 ) + '%'; }

		// Buttons.
		var prev = $( '#ontime-prev' );
		var next = $( '#ontime-next' );
		if ( prev ) { prev.style.visibility = ( n > 1 ) ? 'visible' : 'hidden'; }
		if ( next ) {
			if ( 5 === n ) {
				next.textContent = t( 'confirm' );
			} else {
				next.textContent = t( 'next' );
			}
		}
	}

	function nextStep() {
		// Validate current step before advancing.
		if ( 1 === state.step && state.serviceId < 1 ) { alert( t( 'selectService' ) ); return; }
		if ( 2 === state.step && state.jDay < 1 ) { alert( t( 'selectDate' ) ); return; }
		if ( 3 === state.step && state.slotTs < 1 ) { alert( t( 'selectSlot' ) ); return; }

		if ( state.step < 5 ) {
			goStep( state.step + 1 );
			return;
		}

		// Step 5: submit.
		var next = $( '#ontime-next' );
		if ( next ) { next.disabled = true; }
		submitBooking( function ( res ) {
			if ( next ) { next.disabled = false; }
			var out = $( '#ontime-result' );
			if ( ! out ) { return; }
			out.className = 'ontime-result';
			if ( res && res.success ) {
				out.classList.add( 'ontime-ok' );
				out.textContent = ( res.data && res.data.message ) || t( 'success' );
				if ( next ) { next.style.display = 'none'; }
			} else {
				out.classList.add( 'ontime-err' );
				out.textContent = ( res && res.data && res.data.message ) || t( 'error' );
			}
		} );
	}

	function prevStep() {
		if ( state.step > 1 ) { goStep( state.step - 1 ); }
	}

	/* --- Jalali helpers (mirror of PHP engine) --- */
	// Convert a JS Date to approximate Jalali (uses the same Pournader-Toossi math).
	function jalaliOf( d ) {
		var gY = d.getFullYear();
		var gM = d.getMonth() + 1;
		var gD = d.getDate();
		return gregToJalali( gY, gM, gD );
	}

	function gregToJalali( gY, gM, gD ) {
		var gDays = [ 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ];
		var jDays = [ 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29 ];
		var gy = gY - 1600, gm = gM - 1, gd = gD - 1;
		var gNo = 365 * gy + Math.floor( ( gy + 3 ) / 4 ) - Math.floor( ( gy + 99 ) / 100 ) + Math.floor( ( gy + 399 ) / 400 );
		for ( var i = 0; i < gm; i++ ) { gNo += gDays[ i ]; }
		if ( gm > 1 && ( ( gY % 4 === 0 && gY % 100 !== 0 ) || ( gY % 400 === 0 ) ) ) { gNo++; }
		gNo += gd;
		var jNo = gNo - 79;
		var jNp = Math.floor( jNo / 12053 ); jNo %= 12053;
		var jy = 979 + 33 * jNp + 4 * Math.floor( jNo / 1461 ); jNo %= 1461;
		if ( jNo >= 366 ) { jy += Math.floor( ( jNo - 1 ) / 365 ); jNo = ( jNo - 1 ) % 365; }
		var j = 0; for ( ; j < 11 && jNo >= jDays[ j ]; j++ ) { jNo -= jDays[ j ]; }
		return { year: jy, month: j + 1, day: jNo + 1 };
	}

	function jDaysInMonth( jY, jM ) {
		var jDays = [ 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29 ];
		if ( 12 === jM && isJLeap( jY ) ) { return 30; }
		return jDays[ jM - 1 ];
	}

	function isJLeap( jY ) {
		var breaks = [ -61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1221, 1354 ];
		var jp = jY - 979, jn = -1, jump = 0;
		for ( var i = 0; i < breaks.length; i++ ) {
			jm = breaks[ i ];
			jump = jm - jn;
			if ( jp < jm ) { break; }
			jn = jm;
		}
		var n = jp - jn;
		var leap = ( jump < n && n < jump / 2 ) ? 1 : 0;
		if ( ( jump - n ) < 6 ) { n = n - jump + Math.floor( ( jump + 4 ) / 33 ); leap = ( ( n % 33 - 1 ) % 4 < 1 ) ? 1 : 0; }
		return !!leap;
	}

	// PHP w (0=Sun..6=Sat) for the first day of a Jalali month.
	function jFirstWeekday( jY, jM ) {
		// Convert Jalali first-of-month to Gregorian, then to JS Date.
		var g = jalToGreg( jY, jM, 1 );
		var d = new Date( Date.UTC( g.year, g.month - 1, g.day ) );
		return d.getUTCDay();
	}

	function jalToGreg( jY, jM, jD ) {
		var jDays = [ 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29 ];
		var gDays = [ 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ];
		var jy = jY - 979, jm = jM - 1, jd = jD - 1;
		var jNo = 365 * jy + Math.floor( jy / 33 ) * 8 + Math.floor( ( ( jy % 33 ) + 3 ) / 4 );
		for ( var i = 0; i < jm; i++ ) { jNo += jDays[ i ]; }
		jNo += jd;
		var gNo = jNo + 79;
		var gy = 1600 + 400 * Math.floor( gNo / 146097 ); gNo %= 146097;
		var leap = true;
		if ( gNo >= 36525 ) { gNo--; gy += 100 * Math.floor( gNo / 36524 ); gNo %= 36524; if ( gNo >= 365 ) { gNo++; } else { leap = false; } }
		gy += 4 * Math.floor( gNo / 1461 ); gNo %= 1461;
		if ( gNo >= 366 ) { leap = false; gNo--; gy += Math.floor( gNo / 365 ); gNo = gNo % 365; }
		var salA = 0, gd2 = gDays.slice(); if ( leap ) { gd2[1] = 29; }
		while ( gNo >= gd2[ salA ] ) { gNo -= gd2[ salA ]; salA++; }
		return { year: gy, month: salA + 1, day: gNo + 1 };
	}

	function toPersian( n ) {
		var map = [ '۰','۱','۲','۳','۴','۵','۶','۷','۸','۹' ];
		return String( n ).replace( /[0-9]/g, function ( d ) { return map[ d ]; } );
	}

	function esc( s ) {
		var div = document.createElement( 'div' );
		div.textContent = s == null ? '' : String( s );
		return div.innerHTML;
	}

	// Boot.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
