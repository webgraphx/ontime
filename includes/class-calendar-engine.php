<?php
/**
 * Jalali Calendar Engine.
 *
 * All internal time calculations run in UTC. Conversion to the Jalali
 * (Persian) calendar happens only at the presentation layer, preventing
 * timezone drift and keeping free-slot math deterministic.
 *
 * @package OnTime
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides UTC-safe Jalali conversion, free-slot generation (30-minute
 * intervals) and Persian public-holiday exclusion.
 */
class OnTime_Calendar_Engine {

	/** Default slot length in minutes. */
	const SLOT_MINUTES = 30;

	/** English weekday names keyed by ISO-8601 day number (1=Mon..7=Sun). */
	const WEEKDAY_KEYS = array(
		1 => 'monday',
		2 => 'tuesday',
		3 => 'wednesday',
		4 => 'thursday',
		5 => 'friday',
		6 => 'saturday',
		7 => 'sunday',
	);

	/**
	 * Return the WP timezone object. Falls back to UTC when the site
	 * timezone is unset so arithmetic stays deterministic.
	 *
	 * @return DateTimeZone
	 */
	private function wp_timezone() {
		$tz_string = get_option( 'timezone_string' );
		if ( empty( $tz_string ) ) {
			$offset = (float) get_option( 'gmt_offset', 0 );
			$hours   = (int) $offset;
			$minutes = ( $offset - $hours ) * 60;
			$tz_string = sprintf( 'Etc/GMT%+d:%02d', -$hours, abs( (int) $minutes ) );
		}
		return new DateTimeZone( $tz_string );
	}

	/**
	 * Convert a UTC-stored datetime into Jalali date/time string for display.
	 *
	 * @param string $utc_datetime MySQL datetime (assumed UTC).
	 * @param string $format       Jalali format string (e.g. 'Y-m-d H:i').
	 * @return string
	 */
	public function to_jalali_display( $utc_datetime, $format = 'Y-m-d H:i' ) {
		if ( empty( $utc_datetime ) || '1970-01-01 00:00:00' === $utc_datetime ) {
			return '';
		}
		try {
			$utc = new DateTime( $utc_datetime, new DateTimeZone( 'UTC' ) );
			$utc->setTimezone( $this->wp_timezone() );
			return $this->format_jalali( $utc, $format );
		} catch ( Exception $e ) {
			return '';
		}
	}

	/**
	 * Format a DateTime object into a Jalali date string.
	 * Uses the pure-PHP `Intl` extension when available; otherwise falls
	 * back to a built-in algorithmic converter.
	 *
	 * @param DateTime $dt     DateTime in display timezone.
	 * @param string   $format Pattern (Y-m-d H:i supported tokens).
	 * @return string
	 */
	private function format_jalali( DateTime $dt, $format ) {
		if ( class_exists( 'IntlDateFormatter' ) ) {
			$formatter = new IntlDateFormatter(
				'fa_IR@calendar=persian',
				IntlDateFormatter::FULL,
				IntlDateFormatter::FULL,
				$dt->getTimezone(),
				IntlDateFormatter::GREGORIAN,
				$format
			);
			$result = $formatter->format( $dt );
			if ( false !== $result ) {
				return $this->normalize_persian_digits( (string) $result );
			}
		}

		$j = $this->gregorian_to_jalali( (int) $dt->format( 'Y' ), (int) $dt->format( 'n' ), (int) $dt->format( 'j' ) );
		$y = str_pad( (string) $j[0], 4, '0', STR_PAD_LEFT );
		$m = str_pad( (string) $j[1], 2, '0', STR_PAD_LEFT );
		$d = str_pad( (string) $j[2], 2, '0', STR_PAD_LEFT );
		$out = $format;
		$out = str_replace( 'Y', $y, $out );
		$out = str_replace( 'm', $m, $out );
		$out = str_replace( 'd', $d, $out );
		$out = str_replace( 'H', $dt->format( 'H' ), $out );
		$out = str_replace( 'i', $dt->format( 'i' ), $out );
		$out = str_replace( 's', $dt->format( 's' ), $out );
		return $this->normalize_persian_digits( $out );
	}

	/**
	 * Replace latin digits with Persian digits in a string.
	 *
	 * @param string $text Input string.
	 * @return string
	 */
	private function normalize_persian_digits( $text ) {
		$persian = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$latin   = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		return str_replace( $latin, $persian, $text );
	}

	/**
	 * Convert a Gregorian date (Y, m, d) to a Jalali date.
	 * Returns array( year, month, day ).
	 *
	 * @param int $gy Gregorian year.
	 * @param int $gm Gregorian month (1-12).
	 * @param int $gd Gregorian day (1-31).
	 * @return array{0:int,1:int,2:int}
	 */
	public function gregorian_to_jalali( $gy, $gm, $gd ) {
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days = 355666 + ( 365 * $gy ) + (int) ( ( $gy + 3 ) / 4 ) - (int) ( ( $gy + 99 ) / 100 ) + (int) ( ( $gy + 399 ) / 400 ) + $gd + $g_d_m[ $gm - 1 ];
		$jalali_year = -1595 + ( 33 * (int) ( $days / 12053 ) );
		$days %= 12053;
		$jalali_year += 4 * (int) ( $days / 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$jalali_year += (int) ( ( $days - 1 ) / 365 );
			$days = ( $days - 1 ) % 365;
		}
		$jm = ( $days < 186 ) ? 1 + (int) ( $days / 31 ) : 7 + (int) ( ( $days - 186 ) / 30 );
		$jd = 1 + ( ( $days < 186 ) ? ( $days % 31 ) : ( ( $days - 186 ) % 30 ) );
		return array( $jalali_year, $jm, $jd );
	}

	/**
	 * Convert a Jalali date (Y, m, d) to a Gregorian date.
	 *
	 * @param int $jy Jalali year.
	 * @param int $jm Jalali month (1-12).
	 * @param int $jd Jalali day (1-31).
	 * @return array{0:int,1:int,2:int}
	 */
	public function jalali_to_gregorian( $jy, $jm, $jd ) {
		$jy = $jy - 979;
		$days = ( 365 * $jy ) + ( (int) ( $jy / 33 ) * 8 ) + (int) ( ( ( $jy % 33 ) + 3 ) / 4 ) + $jd + ( ( $jm < 7 ) ? ( ( $jm - 1 ) * 31 ) : ( ( ( $jm - 7 ) * 30 ) + 186 ) );
		$gy = 400 * (int) ( $days / 146097 );
		$days %= 146097;
		if ( $days > 36524 ) {
			$days--;
			$gy += 100 * (int) ( $days / 36524 );
			$days %= 36524;
			if ( $days >= 365 ) {
				$days++;
			}
		}
		$gy += 4 * (int) ( $days / 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$days--;
			$gy += (int) ( $days / 365 );
			$days = ( $days % 365 );
		}
		$gd = $days + 1;
		$sal_a = array( 0, 31, ( ( ( $gy % 4 === 0 ) && ( $gy % 100 !== 0 ) ) || ( $gy % 400 === 0 ) ) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		for ( $gm = 0; $gm < 12 && $gd > $sal_a[ $gm ]; $gm++ ) {
			$gd -= $sal_a[ $gm ];
		}
		return array( $gy, $gm + 1, $gd );
	}

	/**
	 * Parse a Jalali date string (Y-m-d) into a UTC day boundary pair.
	 *
	 * @param string $jalali_date Y-m-d in Jalali.
	 * @return array{0:string,1:string} [start_utc, end_utc] MySQL datetime strings.
	 */
	public function jalali_day_to_utc_range( $jalali_date ) {
		$parts = array_map( 'absint', explode( '-', $jalali_date ) );
		if ( count( $parts ) < 3 ) {
			return array( '', '' );
		}
		list( $jy, $jm, $jd ) = $parts;
		$g = $this->jalali_to_gregorian( $jy, $jm, $jd );

		try {
			$start = new DateTime( sprintf( '%04d-%02d-%02d 00:00:00', $g[0], $g[1], $g[2] ), $this->wp_timezone() );
			$end = clone $start;
			$end->modify( '+1 day' );
			$start->setTimezone( new DateTimeZone( 'UTC' ) );
			$end->setTimezone( new DateTimeZone( 'UTC' ) );
			return array( $start->format( 'Y-m-d H:i:s' ), $end->format( 'Y-m-d H:i:s' ) );
		} catch ( Exception $e ) {
			return array( '', '' );
		}
	}

	/**
	 * Persian public holidays (filterable). Days are given as Jalali
	 * month-day strings ("01-01" = Nowruz).
	 *
	 * @return string[] Array of "MM-DD" Jalali holidays.
	 */
	private function public_holidays() {
		$holidays = array(
			'01-01',
			'01-02',
			'01-03',
			'01-04',
			'01-12',
			'01-13',
			'03-14',
			'03-15',
			'11-22',
			'12-29',
		);
		/** Filter the list of Jalali public holidays (month-day strings). */
		return apply_filters( 'ontime_public_holidays', $holidays );
	}

	/**
	 * Check whether a Jalali date string is a public holiday.
	 *
	 * @param string $jalali_date Y-m-d Jalali date.
	 * @return bool
	 */
	public function is_holiday( $jalali_date ) {
		$parts = array_map( 'absint', explode( '-', $jalali_date ) );
		if ( count( $parts ) < 3 ) {
			return false;
		}
		$mm_dd = sprintf( '%02d-%02d', $parts[1], $parts[2] );
		return in_array( $mm_dd, $this->public_holidays(), true );
	}

	/**
	 * Get the working hours config for a given staff member.
	 *
	 * @param array $staff Staff row.
	 * @return array Decoded working hours keyed by english weekday name.
	 */
	private function working_hours( $staff ) {
		$raw = isset( $staff['working_hours_json'] ) ? $staff['working_hours_json'] : '';
		$decoded = $raw ? json_decode( $raw, true ) : array();
		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}
		return $decoded;
	}

	/**
	 * Calculate available 30-minute slots for a staff member on a given
	 * Jalali date. Internal math in UTC; returned slot times are Jalali
	 * display labels paired with the UTC value used for persistence.
	 *
	 * @param int    $staff_id    Staff id.
	 * @param string $jalali_date Jalali Y-m-d date.
	 * @return array{value:string,label:string}[] Slot list.
	 */
	public function get_available_slots( $staff_id, $jalali_date ) {
		if ( $this->is_holiday( $jalali_date ) ) {
			return array();
		}

		$db = new OnTime_Database();
		$staff = $db->get_staff_member( $staff_id );
		if ( ! $staff ) {
			return array();
		}

		$hours = $this->working_hours( $staff );
		$parts = array_map( 'absint', explode( '-', $jalali_date ) );
		if ( count( $parts ) < 3 ) {
			return array();
		}
		list( $jy, $jm, $jd ) = $parts;
		$g = $this->jalali_to_gregorian( $jy, $jm, $jd );

		try {
			$tz = $this->wp_timezone();
			$day_local = new DateTime( sprintf( '%04d-%02d-%02d 00:00:00', $g[0], $g[1], $g[2] ), $tz );
			$day_local_end = clone $day_local;
			$day_local_end->modify( '+1 day' );
			$day_utc = clone $day_local;
			$day_utc->setTimezone( new DateTimeZone( 'UTC' ) );
			$day_utc_end = clone $day_local_end;
			$day_utc_end->setTimezone( new DateTimeZone( 'UTC' ) );
		} catch ( Exception $e ) {
			return array();
		}

		$iso_day = (int) $day_local->format( 'N' );
		$day_key = isset( self::WEEKDAY_KEYS[ $iso_day ] ) ? self::WEEKDAY_KEYS[ $iso_day ] : '';
		if ( '' === $day_key || empty( $hours[ $day_key ] ) ) {
			return array();
		}

		$existing = $db->get_appointments_in_range(
			$staff_id,
			$day_utc->format( 'Y-m-d H:i:s' ),
			$day_utc_end->format( 'Y-m-d H:i:s' )
		);
		$busy = array();
		foreach ( $existing as $row ) {
			$busy[] = array(
				new DateTime( $row['start_datetime'], new DateTimeZone( 'UTC' ) ),
				new DateTime( $row['end_datetime'], new DateTimeZone( 'UTC' ) ),
			);
		}

		$slots = array();
		$now_utc = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		$step    = new DateInterval( 'PT' . self::SLOT_MINUTES . 'M' );

		foreach ( $hours[ $day_key ] as $range ) {
			$from = isset( $range[0] ) ? $range[0] : '';
			$to   = isset( $range[1] ) ? $range[1] : '';
			if ( '' === $from || '' === $to ) {
				continue;
			}
			list( $fh, $fm ) = array_pad( array_map( 'absint', explode( ':', $from ) ), 2, 0 );
			list( $th, $tm ) = array_pad( array_map( 'absint', explode( ':', $to ) ), 2, 0 );

			try {
				$window_start = new DateTime( sprintf( '%04d-%02d-%02d %02d:%02d:00', $g[0], $g[1], $g[2], $fh, $fm ), $tz );
				$window_end   = new DateTime( sprintf( '%04d-%02d-%02d %02d:%02d:00', $g[0], $g[1], $g[2], $th, $tm ), $tz );
			} catch ( Exception $e ) {
				continue;
			}

			$cursor = clone $window_start;
			while ( $cursor < $window_end ) {
				$slot_end = clone $cursor;
				$slot_end->add( $step );
				if ( $slot_end > $window_end ) {
					break;
				}
				$cursor_utc = clone $cursor;
				$cursor_utc->setTimezone( new DateTimeZone( 'UTC' ) );
				$slot_end_utc = clone $slot_end;
				$slot_end_utc->setTimezone( new DateTimeZone( 'UTC' ) );
				if ( $cursor_utc >= $now_utc && ! $this->overlaps( $cursor_utc, $slot_end_utc, $busy ) ) {
					$slots[] = array(
						'value' => $cursor_utc->format( 'Y-m-d H:i:s' ),
						'label' => $this->format_jalali( $cursor, 'H:i' ),
					);
				}
				$cursor->add( $step );
			}
		}

		return $slots;
	}

	/**
	 * Determine whether a candidate slot overlaps any busy window.
	 *
	 * @param DateTime         $start Slot start in UTC.
	 * @param DateTime         $end   Slot end in UTC.
	 * @param array            $busy  Existing appointments.
	 * @return bool
	 */
	private function overlaps( DateTime $start, DateTime $end, array $busy ) {
		foreach ( $busy as $window ) {
			if ( $start < $window[1] && $end > $window[0] ) {
				return true;
			}
		}
		return false;
	}
}
