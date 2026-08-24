<?php
/**
 * Jalali calendar engine (Singleton).
 *
 * Handles UTC-safe timestamp storage, Gregorian-to-Jalali conversion,
 * free-slot generation (30-minute intervals) and Persian holiday exclusion.
 *
 * All comparisons are performed in UTC; Jalali conversion happens only at
 * the presentation layer. The converter is a self-contained, dependency-free
 * implementation (no PHP Intl extension required) based on the well-known
 * algorithm by Roozbeh Pournader and Mohammad Toossi.
 *
 * Weekday numbering follows the PHP \`w\` convention (0=Sun ... 6=Sat),
 * so the \`weekend_days\` setting (default "5" = Friday) is consistent
 * across the settings table, the calendar engine and the admin UI.
 *
 * @package OnTime
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OnTime_Calendar_Engine {

	/** @since 0.1.0 @var OnTime_Calendar_Engine|null */
	private static $instance = null;

	/**
	 * Persian month names (1-12).
	 * @since 0.3.0
	 * @var array
	 */
	private $j_months = array(
		1  => 'فروردین',
		2  => 'اردیبهشت',
		3  => 'خرداد',
		4  => 'تیر',
		5  => 'مرداد',
		6  => 'شهریور',
		7  => 'مهر',
		8  => 'آبان',
		9  => 'آذر',
		10 => 'دی',
		11 => 'بهمن',
		12 => 'اسفند',
	);

	/**
	 * Persian weekday names indexed by PHP \`w\` (0=Sun ... 6=Sat).
	 * @since 0.3.0
	 * @var array
	 */
	private $j_weekdays = array(
		0 => 'یک‌شنبه',
		1 => 'دوشنبه',
		2 => 'سه‌شنبه',
		3 => 'چهارشنبه',
		4 => 'پنج‌شنبه',
		5 => 'جمعه',
		6 => 'شنبه',
	);

	/**
	 * Persian official holidays (approximate MVP list for 1404).
	 * Stored as "month-day" pairs. Extendable via settings in later stages.
	 * @since 0.3.0
	 * @var array
	 */
	private $j_holidays = array(
		'1-1',  '1-2',  '1-3',  '1-4',  '1-12', '1-13',
		'3-14', '3-15',
		'11-22',
		'12-29',
	);

	/**
	 * Persian digits map.
	 * @since 0.3.0
	 * @var array
	 */
	private $persian_digits = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );

	/**
	 * Singleton accessor.
	 * @since 0.1.0
	 * @return OnTime_Calendar_Engine
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Convert a Gregorian date (Y, m, d) to a Jalali date (Y, m, d).
	 *
	 * Pure PHP port of the Pournader-Toossi algorithm.
	 *
	 * @since 0.3.0
	 *
	 * @param int $g_y Gregorian year.
	 * @param int $g_m Gregorian month.
	 * @param int $g_d Gregorian day.
	 * @return array { @type int $year @type int $month @type int $day }
	 */
	public function gregorian_to_jalali( $g_y, $g_m, $g_d ) {
		$g_days = array( 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		$j_days = array( 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29 );

		$gy = (int) $g_y - 1600;
		$gm = (int) $g_m - 1;
		$gd = (int) $g_d - 1;

		$g_day_no = 365 * $gy + (int) ( ( $gy + 3 ) / 4 ) - (int) ( ( $gy + 99 ) / 100 ) + (int) ( ( $gy + 399 ) / 400 );
		for ( $i = 0; $i < $gm; ++ $i ) {
			$g_day_no += $g_days[ $i ];
		}
		if ( $gm > 1 && ( ( $g_y % 4 === 0 && $g_y % 100 !== 0 ) || ( $g_y % 400 === 0 ) ) ) {
			++ $g_day_no;
		}
		$g_day_no += $gd;

		$j_day_no = $g_day_no - 79;
		$j_np = (int) ( $j_day_no / 12053 );
		$j_day_no %= 12053;

		$jy = 979 + 33 * $j_np + 4 * (int) ( $j_day_no / 1461 );
		$j_day_no %= 1461;

		if ( $j_day_no >= 366 ) {
			$jy += (int) ( ( $j_day_no - 1 ) / 365 );
			$j_day_no = ( $j_day_no - 1 ) % 365;
		}

		for ( $i = 0; $i < 11 && $j_day_no >= $j_days[ $i ]; ++ $i ) {
			$j_day_no -= $j_days[ $i ];
		}

		return array(
			'year'  => (int) $jy,
			'month' => (int) ( $i + 1 ),
			'day'   => (int) ( $j_day_no + 1 ),
		);
	}

	/**
	 * Convert a Jalali date (Y, m, d) to a Gregorian date (Y, m, d).
	 *
	 * @since 0.3.0
	 *
	 * @param int $j_y Jalali year.
	 * @param int $j_m Jalali month.
	 * @param int $j_d Jalali day.
	 * @return array { @type int $year @type int $month @type int $day }
	 */
	public function jalali_to_gregorian( $j_y, $j_m, $j_d ) {
		$j_days = array( 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29 );
		$g_days = array( 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );

		$jy = (int) $j_y - 979;
		$jm = (int) $j_m - 1;
		$jd = (int) $j_d - 1;

		$j_day_no = 365 * $jy + (int) ( $jy / 33 ) * 8 + (int) ( ( ( $jy % 33 ) + 3 ) / 4 );
		for ( $i = 0; $i < $jm; ++ $i ) {
			$j_day_no += $j_days[ $i ];
		}
		$j_day_no += $jd;

		$g_day_no = $j_day_no + 79;

		$gy = 1600 + 400 * (int) ( $g_day_no / 146097 );
		$g_day_no %= 146097;

		$leap = true;
		if ( $g_day_no >= 36525 ) {
			-- $g_day_no;
			$gy += 100 * (int) ( $g_day_no / 36524 );
			$g_day_no %= 36524;
			if ( $g_day_no >= 365 ) {
				++ $g_day_no;
			} else {
				$leap = false;
			}
		}

		$gy += 4 * (int) ( $g_day_no / 1461 );
		$g_day_no %= 1461;

		if ( $g_day_no >= 366 ) {
			$leap = false;
			$g_day_no -= 1;
			$gy += (int) ( $g_day_no / 365 );
			$g_day_no = $g_day_no % 365;
		}

		$sal_a = 0;
		$g_days_local = $g_days;
		if ( $leap ) {
			$g_days_local[1] = 29;
		}
		while ( $g_day_no >= $g_days_local[ $sal_a ] ) {
			$g_day_no -= $g_days_local[ $sal_a ];
			++ $sal_a;
		}

		return array(
			'year'  => (int) $gy,
			'month' => (int) ( $sal_a + 1 ),
			'day'   => (int) ( $g_day_no + 1 ),
		);
	}

	/**
	 * Convert a UTC Unix timestamp to a Jalali date array (local-time aware).
	 *
	 * The timestamp is converted from UTC to the configured display timezone
	 * (default Asia/Tehran) before deriving the Jalali date.
	 *
	 * @since 0.3.0
	 *
	 * @param int $timestamp UTC Unix timestamp.
	 * @return array {
	 *     @type int $year
	 *     @type int $month
	 *     @type int $day
	 *     @type int $hour
	 *     @type int $minute
	 *     @type int $weekday PHP \`w\` (0=Sun ... 6=Sat; Friday=5).
	 * }
	 */
	public function to_jalali( $timestamp ) {
		$tz     = $this->get_display_timezone();
		$dt_utc = new DateTime( '@' . (int) $timestamp, new DateTimeZone( 'UTC' ) );
		$dt_utc->setTimezone( $tz );

		$g_y = (int) $dt_utc->format( 'Y' );
		$g_m = (int) $dt_utc->format( 'n' );
		$g_d = (int) $dt_utc->format( 'j' );

		$j = $this->gregorian_to_jalali( $g_y, $g_m, $g_d );

		$j['hour']    = (int) $dt_utc->format( 'G' );
		$j['minute']  = (int) $dt_utc->format( 'i' );
		$j['weekday'] = (int) $dt_utc->format( 'w' ); // PHP w: 0=Sun ... 6=Sat, Friday=5.

		return $j;
	}

	/**
	 * Convert a Jalali date/time to a UTC Unix timestamp.
	 *
	 * @since 0.3.0
	 *
	 * @param int $j_year  Jalali year.
	 * @param int $j_month Jalali month.
	 * @param int $j_day   Jalali day.
	 * @param int $hour    Hour (0-23) in display timezone. Default 0.
	 * @param int $minute  Minute (0-59) in display timezone. Default 0.
	 * @return int UTC Unix timestamp.
	 */
	public function jalali_to_timestamp( $j_year, $j_month, $j_day, $hour = 0, $minute = 0 ) {
		$g = $this->jalali_to_gregorian( $j_year, $j_month, $j_day );
		$tz = $this->get_display_timezone();
		$dt = new DateTime( sprintf( '%04d-%02d-%02d %02d:%02d:00', $g['year'], $g['month'], $g['day'], $hour, $minute ), $tz );
		$dt->setTimezone( new DateTimeZone( 'UTC' ) );
		return (int) $dt->getTimestamp();
	}

	/**
	 * Generate free slots for a given Jalali day.
	 *
	 * Reads the service duration and working-hours settings, generates
	 * candidate slots within the working window, and excludes slots that
	 * overlap existing active appointments, fall on a weekend day (Friday by
	 * default), or fall on a Persian official holiday.
	 *
	 * @since 0.3.0
	 *
	 * @param int $service_id Service ID.
	 * @param int $j_year     Jalali year.
	 * @param int $j_month    Jalali month.
	 * @param int $j_day      Jalali day.
	 * @return array Array of UTC Unix timestamps for free slots.
	 */
	public function get_free_slots( $service_id, $j_year, $j_month, $j_day ) {
		global $wpdb;

		$settings = OnTime_Database::instance()->get_all_settings();
		$slot_len = (int) ( isset( $settings['slot_length'] ) ? $settings['slot_length'] : 30 );
		$work_s   = isset( $settings['work_start'] ) ? $settings['work_start'] : '09:00';
		$work_e   = isset( $settings['work_end'] ) ? $settings['work_end'] : '18:00';
		$buffer   = (int) ( isset( $settings['buffer_minutes'] ) ? $settings['buffer_minutes'] : 0 );
		$min_lead = (int) ( isset( $settings['min_lead_hours'] ) ? $settings['min_lead_hours'] : 2 );
		$weekend  = isset( $settings['weekend_days'] ) ? explode( ',', $settings['weekend_days'] ) : array( '5' );

		// Day boundaries in display timezone.
		$day_start_ts = $this->jalali_to_timestamp( $j_year, $j_month, $j_day, 0, 0 );
		$day_j        = $this->to_jalali( $day_start_ts );

		// Weekend check (PHP w; Friday = 5).
		if ( in_array( (string) $day_j['weekday'], $weekend, true ) ) {
			return array();
		}

		// Holiday check.
		if ( $this->is_holiday( $j_month, $j_day ) ) {
			return array();
		}

		// Working window start/end as UTC timestamps.
		list( $sh, $sm ) = array_map( 'intval', explode( ':', $work_s ) );
		list( $eh, $em ) = array_map( 'intval', explode( ':', $work_e ) );

		$slot_start = $this->jalali_to_timestamp( $j_year, $j_month, $j_day, $sh, $sm );
		$window_end = $this->jalali_to_timestamp( $j_year, $j_month, $j_day, $eh, $em );

		// Minimum lead time: don't offer slots that start too soon.
		$min_ts = time() + ( $min_lead * HOUR_IN_SECONDS );

		// Fetch existing appointments for this service on this day.
		$table_appointments = OnTime_Database::instance()->get_table( 'appointments' );
		$day_end_ts          = $window_end + DAY_IN_SECONDS;

		$booked = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT start_time, end_time FROM {$table_appointments}
				WHERE service_id = %d AND status IN ('pending','confirmed')
				AND start_time >= %s AND start_time < %s",
				$service_id,
				gmdate( 'Y-m-d H:i:s', $slot_start ),
				gmdate( 'Y-m-d H:i:s', $day_end_ts )
			),
			ARRAY_A
		);

		$booked_ranges = array();
		if ( is_array( $booked ) ) {
			foreach ( $booked as $b ) {
				$booked_ranges[] = array(
					'start' => strtotime( $b['start_time'] . ' UTC' ),
					'end'   => strtotime( $b['end_time'] . ' UTC' ),
				);
			}
		}

		$slots = array();
		while ( $slot_start + ( $slot_len * MINUTE_IN_SECONDS ) <= $window_end ) {
			$slot_end = $slot_start + ( $slot_len * MINUTE_IN_SECONDS );

			if ( $slot_start < $min_ts ) {
				$slot_start += ( $slot_len + $buffer ) * MINUTE_IN_SECONDS;
				continue;
			}

			$overlap = false;
			foreach ( $booked_ranges as $r ) {
				if ( $slot_start < $r['end'] && $slot_end > $r['start'] ) {
					$overlap = true;
					break;
				}
			}

			if ( ! $overlap ) {
				$slots[] = $slot_start;
			}

			$slot_start += ( $slot_len + $buffer ) * MINUTE_IN_SECONDS;
		}

		return $slots;
	}

	/**
	 * Check whether a Jalali month/day is an official Persian holiday.
	 *
	 * @since 0.3.0
	 *
	 * @param int $j_month Jalali month.
	 * @param int $j_day   Jalali day.
	 * @return bool
	 */
	public function is_holiday( $j_month, $j_day ) {
		$key = (int) $j_month . '-' . (int) $j_day;
		return in_array( $key, $this->j_holidays, true );
	}

	/**
	 * Format a UTC timestamp as a Jalali date/time string with Persian digits.
	 *
	 * Supported tokens: j (day), n (month number), F (month name), Y (year),
	 * H (hour 24h), i (minute), w (weekday number 0-6), l (weekday name).
	 *
	 * @since 0.3.0
	 *
	 * @param int    $timestamp UTC Unix timestamp.
	 * @param string $format    Format string.
	 * @return string
	 */
	public function format_jalali( $timestamp, $format = 'j F Y، H:i' ) {
		$j = $this->to_jalali( $timestamp );

		$out = $format;
		$out = str_replace( 'Y', (string) $j['year'], $out );
		$out = str_replace( 'n', (string) $j['month'], $out );
		$out = str_replace( 'F', $this->j_months[ $j['month'] ], $out );
		$out = str_replace( 'j', (string) $j['day'], $out );
		$out = str_replace( 'l', $this->j_weekdays[ $j['weekday'] ], $out );
		$out = str_replace( 'w', (string) $j['weekday'], $out );
		$out = str_replace( 'H', str_pad( (string) $j['hour'], 2, '0', STR_PAD_LEFT ), $out );
		$out = str_replace( 'i', str_pad( (string) $j['minute'], 2, '0', STR_PAD_LEFT ), $out );

		return $this->to_persian_digits( $out );
	}

	/**
	 * Convert Latin digits in a string to Persian digits.
	 *
	 * @since 0.3.0
	 *
	 * @param string $text Input text.
	 * @return string
	 */
	public function to_persian_digits( $text ) {
		$settings = OnTime_Database::instance()->get_all_settings();
		if ( ! empty( $settings['persian_digits'] ) && (int) $settings['persian_digits'] === 0 ) {
			return $text;
		}
		return str_replace( array( '0','1','2','3','4','5','6','7','8','9' ), $this->persian_digits, $text );
	}

	/**
	 * Get the display timezone from settings (default Asia/Tehran).
	 *
	 * @since 0.3.0
	 * @return DateTimeZone
	 */
	private function get_display_timezone() {
		$settings = OnTime_Database::instance()->get_all_settings();
		$tz_name  = isset( $settings['timezone'] ) ? $settings['timezone'] : 'Asia/Tehran';
		try {
			return new DateTimeZone( $tz_name );
		} catch ( Exception $e ) {
			return new DateTimeZone( 'Asia/Tehran' );
		}
	}
}
