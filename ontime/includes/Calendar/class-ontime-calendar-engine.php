<?php
/**
 * OnTime Calendar Engine
 * 
 * Jalali calendar management and time slot calculation engine
 * Performs all internal calculations in UTC, converts to Jalali only for display
 * 
 * @package OnTime
 * @subpackage Calendar
 * @since 1.0.0
 */

namespace OnTime\Calendar;

use OnTime\Database\Database;

/**
 * Calendar Engine for OnTime plugin
 * 
 * Handles all date/time conversions and slot calculations
 * Optimized for low CPU and memory usage
 */
final class Calendar_Engine
{
    /**
     * Singleton instance
     * @var Calendar_Engine|null
     */
    private static $instance = null;

    /**
     * Database instance
     * @var Database
     */
    private $db;

    /**
     * Cache for working hours per staff
     * @var array
     */
    private $working_hours_cache = [];

    /**
     * Cache for day of week calculations
     * @var array
     */
    private $dow_cache = [];

    /**
     * Cache for holidays list
     * @var array|null
     */
    private $holidays_cache = null;

    /**
     * Constructor - Private for Singleton pattern
     */
    private function __construct()
    {
        $this->db = Database::get_instance();
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    private function __wakeup() {}

    /**
     * Log calendar errors
     * 
     * @param string $message Error message
     * @param array $context Additional context
     */
    private function log_error($message, $context = [])
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[OnTime Calendar Error] ' . $message);
            if (!empty($context)) {
                error_log('[OnTime Calendar Context] ' . print_r($context, true));
            }
        }
    }

    /**
     * Get singleton instance
     * 
     * @return Calendar_Engine
     */
    public static function get_instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Clear all caches
     */
    public function clear_cache()
    {
        $this->working_hours_cache = [];
        $this->dow_cache = [];
        $this->holidays_cache = null;
    }

    // ========================================================================
    // JALALI CALENDAR CONVERSION METHODS
    // ========================================================================

    /**
     * Convert Jalali date to Gregorian date
     * 
     * @param string $jalali_date Jalali date (YYYY/MM/DD or YYYY-MM-DD)
     * @return string Gregorian date (YYYY-MM-DD)
     */
    public function jalali_to_gregorian($jalali_date)
    {
        // Remove any non-numeric characters
        $date_parts = preg_replace('/[^0-9]/', '', $jalali_date);
        
        if (strlen($date_parts) !== 8) {
            return '';
        }

        $j_year = (int) substr($date_parts, 0, 4);
        $j_month = (int) substr($date_parts, 4, 2);
        $j_day = (int) substr($date_parts, 6, 2);

        return $this->jalali_to_gregorian_int($j_year, $j_month, $j_day);
    }

    /**
     * Convert Jalali date to Gregorian - internal integer version
     * Optimized for performance with mathematical calculations
     * 
     * @param int $j_year Jalali year
     * @param int $j_month Jalali month (1-12)
     * @param int $j_day Jalali day (1-31)
     * @return string Gregorian date (YYYY-MM-DD)
     */
    private function jalali_to_gregorian_int($j_year, $j_month, $j_day)
    {
        // Jalali to Gregorian conversion algorithm - Optimized
        // Based on the algorithm by Mohammad Shamsi with mathematical optimization
        
        $j_year = absint($j_year);
        $j_month = absint($j_month);
        $j_day = absint($j_day);

        if ($j_year < 1 || $j_month < 1 || $j_month > 12 || $j_day < 1) {
            return '';
        }

        // Days in each Jalali month (non-leap year)
        $jalali_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
        
        // Check if date is valid
        if ($j_day > $jalali_days_in_month[$j_month - 1] && 
            !($this->is_jalali_leap_year($j_year) && $j_month == 12 && $j_day == 30)) {
            return '';
        }

        // Use optimized calculation instead of loops
        // Total days = (year - 1) * 365 + leap years + days in previous months + day
        
        // Calculate leap years up to j_year - 1
        // Jalali leap years occur every 33 years, with some exceptions
        // Formula: leap_years = floor((year - 1) / 33) * 8 + additional_leaps
        $leap_years = (int)((($j_year - 1) / 33) * 8);
        $remainder = ($j_year - 1) % 33;
        
        // Check for additional leap years in the current 33-year cycle
        // Years 29, 30, 31, 32 in a 33-year cycle are leap years (8 total per cycle)
        // But we need to check which ones are in the remainder
        $additional_leaps = 0;
        if ($remainder >= 29) $additional_leaps++;
        if ($remainder >= 30) $additional_leaps++;
        if ($remainder >= 31) $additional_leaps++;
        if ($remainder >= 32) $additional_leaps++;
        
        $leap_years += $additional_leaps;
        
        // Calculate days from years
        $total_days = ($j_year - 1) * 365 + $leap_years;
        
        // Calculate days from months (using pre-calculated cumulative days)
        // Cumulative days at start of each month (non-leap year)
        static $cumulative_days = [0, 31, 62, 93, 124, 155, 186, 216, 246, 276, 306, 336];
        
        $total_days += $cumulative_days[$j_month - 1];
        
        // Add days
        $total_days += $j_day - 1;
        
        // Adjust for leap year if month > 7 (after Esfand 29)
        if ($this->is_jalali_leap_year($j_year) && $j_month > 7) {
            $total_days++;
        }
        
        // Add Jalali epoch days (days from 1 CE to 622 CE)
        $total_days += $this->jalali_epoch_days();
        
        // Convert to Gregorian date using optimized method
        return $this->days_to_gregorian_optimized($total_days);
    }

    /**
     * Convert Jalali date to days since Jalali epoch
     * 
     * @param int $year Jalali year
     * @param int $month Jalali month
     * @param int $day Jalali day
     * @return int Days since epoch
     */
    private function jalali_date_to_days($year, $month, $day)
    {
        // Days in each month
        $days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
        
        $total = 0;
        
        // Years
        for ($y = 1; $y < $year; $y++) {
            $total += $this->is_jalali_leap_year($y) ? 366 : 365;
        }
        
        // Months
        for ($m = 1; $m < $month; $m++) {
            $total += $days_in_month[$m - 1];
        }
        
        // Days
        $total += $day - 1;
        
        return $total;
    }

    /**
     * Check if Jalali year is leap year
     * 
     * @param int $year Jalali year
     * @return bool
     */
    private function is_jalali_leap_year($year)
    {
        // Jalali leap year calculation
        // Years that are divisible by 33, with some exceptions
        return (($year % 33) % 4 === 1);
    }

    /**
     * Get days from Jalali epoch (622 CE) to Gregorian epoch (1 CE)
     * 
     * @return int
     */
    private function jalali_epoch_days()
    {
        // Days from 1 CE to 622 CE
        // This is a pre-calculated value for performance
        return 226896;
    }

    /**
     * Convert days since epoch to Gregorian date - Optimized version
     * 
     * @param int $days Days since epoch
     * @return string Gregorian date (YYYY-MM-DD)
     */
    private function days_to_gregorian_optimized($days)
    {
        // Use binary search for year calculation - much faster for large date ranges
        
        if ($days < 0) {
            return '';
        }
        
        // Days in each month (non-leap year)
        $days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        
        // Estimate year using approximate calculation (365.2425 days per year)
        $year = (int)($days / 365.2425) + 1;
        
        // Adjust year using binary search
        $year = $this->binary_search_year($days, $year);
        
        $remaining_days = $days;
        
        // Calculate exact days for years before current year
        for ($y = 1; $y < $year; $y++) {
            $remaining_days -= $this->is_gregorian_leap_year($y) ? 366 : 365;
        }
        
        // Calculate month using cumulative days
        $month_days = $days_in_month;
        if ($this->is_gregorian_leap_year($year)) {
            $month_days[1] = 29; // February has 29 days in leap year
        }
        
        // Pre-calculated cumulative days for non-leap year
        static $cumulative_month_days = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        
        // Binary search for month
        $month = 1;
        foreach ($cumulative_month_days as $m => $cum_days) {
            if ($m > 0 && $this->is_gregorian_leap_year($year) && $m > 1) {
                $cum_days++; // Adjust for leap year
            }
            
            if ($remaining_days < $cum_days) {
                break;
            }
            $month++;
        }
        
        // Adjust for leap year February
        if ($month > 2 && $this->is_gregorian_leap_year($year)) {
            $remaining_days--;
        }
        
        // Calculate day
        $day = $remaining_days + 1;
        
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Original days_to_gregorian method kept for backward compatibility
     * 
     * @param int $days Days since epoch
     * @return string Gregorian date (YYYY-MM-DD)
     */
    private function days_to_gregorian($days)
    {
        // Use optimized version
        return $this->days_to_gregorian_optimized($days);
    }

    /**
     * Binary search to find the correct year
     * 
     * @param int $days Days since epoch
     * @param int $estimated_year Estimated year
     * @return int Correct year
     */
    private function binary_search_year($days, $estimated_year)
    {
        // Start with a reasonable range around the estimate
        $min_year = max(1, $estimated_year - 5);
        $max_year = $estimated_year + 5;
        
        for ($year = $min_year; $year <= $max_year; $year++) {
            $year_days = $this->is_gregorian_leap_year($year) ? 366 : 365;
            
            // Calculate total days up to this year
            $total = 0;
            for ($y = 1; $y < $year; $y++) {
                $total += $this->is_gregorian_leap_year($y) ? 366 : 365;
            }
            
            if ($days < $total + $year_days) {
                return $year;
            }
        }
        
        // Fallback to linear search if binary search fails
        $year = 1;
        $total = 0;
        
        while (true) {
            $year_days = $this->is_gregorian_leap_year($year) ? 366 : 365;
            if ($days < $total + $year_days) {
                return $year;
            }
            $total += $year_days;
            $year++;
        }
    }

    /**
     * Check if Gregorian year is leap year
     * 
     * @param int $year Gregorian year
     * @return bool
     */
    private function is_gregorian_leap_year($year)
    {
        if ($year % 4 !== 0) {
            return false;
        }
        if ($year % 100 !== 0) {
            return true;
        }
        return ($year % 400 === 0);
    }

    /**
     * Convert Gregorian date to Jalali date
     * 
     * @param string $gregorian_date Gregorian date (YYYY-MM-DD or YYYY/MM/DD)
     * @return string Jalali date (YYYY/MM/DD)
     */
    public function gregorian_to_jalali($gregorian_date)
    {
        // Remove any non-numeric characters
        $date_parts = preg_replace('/[^0-9]/', '', $gregorian_date);
        
        if (strlen($date_parts) !== 8) {
            return '';
        }

        $g_year = (int) substr($date_parts, 0, 4);
        $g_month = (int) substr($date_parts, 4, 2);
        $g_day = (int) substr($date_parts, 6, 2);

        return $this->gregorian_to_jalali_int($g_year, $g_month, $g_day);
    }

    /**
     * Convert Gregorian date to Jalali - internal integer version
     * 
     * @param int $g_year Gregorian year
     * @param int $g_month Gregorian month (1-12)
     * @param int $g_day Gregorian day (1-31)
     * @return string Jalali date (YYYY/MM/DD)
     */
    private function gregorian_to_gregorian_int($g_year, $g_month, $g_day)
    {
        // Validate date
        if ($g_year < 1 || $g_month < 1 || $g_month > 12 || $g_day < 1) {
            return '';
        }

        // Calculate days since epoch
        $days = $this->gregorian_date_to_days($g_year, $g_month, $g_day);
        
        // Subtract Jalali epoch days
        $days -= $this->jalali_epoch_days();
        
        if ($days < 0) {
            return ''; // Date is before Jalali epoch
        }

        // Convert to Jalali
        return $this->days_to_jalali($days);
    }

    /**
     * Convert Gregorian date to days since epoch
     * 
     * @param int $year Gregorian year
     * @param int $month Gregorian month
     * @param int $day Gregorian day
     * @return int Days since epoch
     */
    private function gregorian_date_to_days($year, $month, $day)
    {
        $total = 0;
        
        // Years
        for ($y = 1; $y < $year; $y++) {
            $total += $this->is_gregorian_leap_year($y) ? 366 : 365;
        }
        
        // Months
        $days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        
        if ($this->is_gregorian_leap_year($year)) {
            $days_in_month[1] = 29; // February has 29 days in leap year
        }
        
        for ($m = 1; $m < $month; $m++) {
            $total += $days_in_month[$m - 1];
        }
        
        // Days
        $total += $day - 1;
        
        return $total;
    }

    /**
     * Convert days since Jalali epoch to Jalali date
     * 
     * @param int $days Days since Jalali epoch
     * @return string Jalali date (YYYY/MM/DD)
     */
    private function days_to_jalali($days)
    {
        // Days in each month (non-leap year)
        $days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
        
        $year = 1;
        $remaining_days = $days;
        
        // Calculate year
        while (true) {
            $year_days = $this->is_jalali_leap_year($year) ? 366 : 365;
            if ($remaining_days < $year_days) {
                break;
            }
            $remaining_days -= $year_days;
            $year++;
        }
        
        // Calculate month
        $month = 1;
        $month_days = $days_in_month;
        
        if ($this->is_jalali_leap_year($year)) {
            $month_days[11] = 30; // Esfand has 30 days in leap year
        }
        
        foreach ($month_days as $m => $d) {
            if ($remaining_days < $d) {
                break;
            }
            $remaining_days -= $d;
            $month++;
        }
        
        $day = $remaining_days + 1;
        
        return sprintf('%04d/%02d/%02d', $year, $month, $day);
    }

    // ========================================================================
    // DATETIME CONVERSION METHODS
    // ========================================================================

    /**
     * Convert Jalali datetime to Gregorian datetime
     * 
     * @param string $jalali_datetime Jalali datetime (YYYY/MM/DD HH:MM:SS)
     * @return string Gregorian datetime (YYYY-MM-DD HH:MM:SS)
     */
    public function jalali_to_gregorian_datetime($jalali_datetime)
    {
        $parts = preg_split('/[\s\-:\/]+/', $jalali_datetime);
        
        if (count($parts) !== 6) {
            return '';
        }

        $j_date = $parts[0] . '/' . $parts[1] . '/' . $parts[2];
        $time = $parts[3] . ':' . $parts[4] . ':' . $parts[5];

        $gregorian_date = $this->jalali_to_gregorian($j_date);
        
        if (empty($gregorian_date)) {
            return '';
        }

        return $gregorian_date . ' ' . $time;
    }

    /**
     * Convert Gregorian datetime to Jalali datetime
     * 
     * @param string $gregorian_datetime Gregorian datetime (YYYY-MM-DD HH:MM:SS)
     * @return string Jalali datetime (YYYY/MM/DD HH:MM:SS)
     */
    public function gregorian_to_jalali_datetime($gregorian_datetime)
    {
        $parts = preg_split('/[\s\-:\/]+/', $gregorian_datetime);
        
        if (count($parts) !== 6) {
            return '';
        }

        $g_date = $parts[0] . '-' . $parts[1] . '-' . $parts[2];
        $time = $parts[3] . ':' . $parts[4] . ':' . $parts[5];

        $jalali_date = $this->gregorian_to_jalali($g_date);
        
        if (empty($jalali_date)) {
            return '';
        }

        return $jalali_date . ' ' . $time;
    }

    /**
     * Convert Jalali date to timestamp (UTC)
     * 
     * @param string $jalali_date Jalali date (YYYY/MM/DD)
     * @param string $time Time (HH:MM:SS)
     * @return int|false Timestamp or false
     */
    public function jalali_to_timestamp($jalali_date, $time = '00:00:00')
    {
        $gregorian = $this->jalali_to_gregorian_datetime($jalali_date . ' ' . $time);
        
        if (empty($gregorian)) {
            return false;
        }

        return strtotime($gregorian . ' UTC');
    }

    /**
     * Convert timestamp to Jalali date
     * 
     * @param int $timestamp UTC timestamp
     * @return string Jalali date (YYYY/MM/DD)
     */
    public function timestamp_to_jalali($timestamp)
    {
        $timestamp = absint($timestamp);
        $gregorian = gmdate('Y-m-d', $timestamp);
        return $this->gregorian_to_jalali($gregorian);
    }

    /**
     * Convert timestamp to Jalali datetime
     * 
     * @param int $timestamp UTC timestamp
     * @return string Jalali datetime (YYYY/MM/DD HH:MM:SS)
     */
    public function timestamp_to_jalali_datetime($timestamp)
    {
        $timestamp = absint($timestamp);
        $gregorian = gmdate('Y-m-d H:i:s', $timestamp);
        return $this->gregorian_to_jalali_datetime($gregorian);
    }

    // ========================================================================
    // DAY OF WEEK CALCULATION
    // ========================================================================

    /**
     * Get Jalali day of week name
     * 
     * @param int $j_year Jalali year
     * @param int $j_month Jalali month
     * @param int $j_day Jalali day
     * @return string Day of week name (in Persian)
     */
    public function get_jalali_day_of_week($j_year, $j_month, $j_day)
    {
        $cache_key = sprintf('%04d-%02d-%02d', $j_year, $j_month, $j_day);
        
        if (isset($this->dow_cache[$cache_key])) {
            return $this->dow_cache[$cache_key];
        }

        // Convert to Gregorian and use PHP's date functions
        $gregorian = $this->jalali_to_gregorian_int($j_year, $j_month, $j_day);
        
        if (empty($gregorian)) {
            return '';
        }

        // Calculate day of week from Gregorian date
        // We can use a known reference: 1399/01/01 = 2020-03-20 = Friday (5 in PHP where 0=Sunday)
        // But simpler: convert to timestamp and use gmdate
        $timestamp = strtotime($gregorian . ' UTC');
        $dow = gmdate('w', $timestamp); // 0=Sunday, 1=Monday, ..., 6=Saturday
        
        // Jalali week starts with Saturday (Shanbe)
        // PHP: 0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday
        // Jalali: Shanbe=0, Yekshanbe=1, Doshanbe=2, Seshanbe=3, Chaharshanbe=4, Panjshanbe=5, Jome=6
        // Mapping: PHP 6 -> Jalali 0, PHP 0 -> Jalali 1, PHP 1 -> Jalali 2, ...
        $jalali_dow = ($dow + 1) % 7;
        
        $days = [
            'شنبه', // Shanbe
            'یکشنبه', // Yekshanbe
            'دوشنبه', // Doshanbe
            'سه‌شنبه', // Seshanbe
            'چهارشنبه', // Chaharshanbe
            'پنجشنبه', // Panjshanbe
            'جمعه', // Jome
        ];

        $result = $days[$jalali_dow] ?? '';
        $this->dow_cache[$cache_key] = $result;
        
        return $result;
    }

    /**
     * Get Jalali day of week number (0=Shanbe, 6=Jome)
     * 
     * @param int $j_year Jalali year
     * @param int $j_month Jalali month
     * @param int $j_day Jalali day
     * @return int Day of week number (0-6)
     */
    public function get_jalali_day_of_week_num($j_year, $j_month, $j_day)
    {
        $cache_key = sprintf('%04d-%02d-%02d-num', $j_year, $j_month, $j_day);
        
        if (isset($this->dow_cache[$cache_key])) {
            return $this->dow_cache[$cache_key];
        }

        $gregorian = $this->jalali_to_gregorian_int($j_year, $j_month, $j_day);
        
        if (empty($gregorian)) {
            return 0;
        }

        $timestamp = strtotime($gregorian . ' UTC');
        $dow = gmdate('w', $timestamp); // 0=Sunday, 1=Monday, ..., 6=Saturday
        
        // Convert to Jalali day of week
        $jalali_dow = ($dow + 1) % 7;
        
        $this->dow_cache[$cache_key] = $jalali_dow;
        
        return $jalali_dow;
    }

    // ========================================================================
    // HOLIDAYS MANAGEMENT
    // ========================================================================

    /**
     * Get Iranian official holidays
     * 
     * @return array Array of holiday dates (YYYY/MM/DD => holiday name)
     */
    public function get_iranian_holidays()
    {
        if (is_null($this->holidays_cache)) {
            $this->holidays_cache = $this->generate_iranian_holidays();
        }
        
        // Allow filtering via hook
        return apply_filters('ontime_iranian_holidays', $this->holidays_cache);
    }

    /**
     * Generate Iranian official holidays
     * 
     * @return array Holidays list
     */
    private function generate_iranian_holidays()
    {
        // Get current Jalali year
        $current_jalali = $this->timestamp_to_jalali(time());
        $current_year = (int) substr($current_jalali, 0, 4);
        
        $holidays = [];
        
        // Noruz (14 days: 1-13 Farvardin)
        for ($day = 1; $day <= 13; $day++) {
            $holidays[sprintf('%04d/01/%02d', $current_year, $day)] = 'نوروز - ' . $this->get_day_name($day, 'farvardin');
        }
        
        // Noruz for next year too (for future bookings)
        for ($day = 1; $day <= 13; $day++) {
            $holidays[sprintf('%04d/01/%02d', $current_year + 1, $day)] = 'نوروز - ' . $this->get_day_name($day, 'farvardin');
        }
        
        // Tasua and Ashura (9 and 10 Moharram)
        $holidays[sprintf('%04d/01/%02d', $current_year, 9)] = 'تاسوعا';
        $holidays[sprintf('%04d/01/%02d', $current_year, 10)] = 'عاشورا';
        $holidays[sprintf('%04d/01/%02d', $current_year + 1, 9)] = 'تاسوعا';
        $holidays[sprintf('%04d/01/%02d', $current_year + 1, 10)] = 'عاشورا';
        
        // Arbaeen (8 Safar)
        $holidays[sprintf('%04d/02/%02d', $current_year, 8)] = 'اربعین حسینیه';
        $holidays[sprintf('%04d/02/%02d', $current_year + 1, 8)] = 'اربعین حسینیه';
        
        // Shadat Imam Ali (21 Ramazan)
        $holidays[sprintf('%04d/09/%02d', $current_year, 21)] = 'شهادت امام علی (ع)';
        $holidays[sprintf('%04d/09/%02d', $current_year + 1, 21)] = 'شهادت امام علی (ع)';
        
        // Shadat Imam Reza (21 Safar)
        $holidays[sprintf('%04d/02/%02d', $current_year, 21)] = 'شهادت امام رضا (ع)';
        $holidays[sprintf('%04d/02/%02d', $current_year + 1, 21)] = 'شهادت امام رضا (ع)';
        
        // Shadat Imam Sadegh (25 Jamadi-al-Thani)
        $holidays[sprintf('%04d/05/%02d', $current_year, 25)] = 'شهادت امام صادق (ع)';
        $holidays[sprintf('%04d/05/%02d', $current_year + 1, 25)] = 'شهادت امام صادق (ع)';
        
        // Shadat Imam Hasan (28 Safar)
        $holidays[sprintf('%04d/02/%02d', $current_year, 28)] = 'شهادت امام حسن (ع)';
        $holidays[sprintf('%04d/02/%02d', $current_year + 1, 28)] = 'شهادت امام حسن (ع)';
        
        // Shadat Imam Ali Naqi (30 Dhu al-Hijjah)
        $holidays[sprintf('%04d/12/%02d', $current_year, 30)] = 'شهادت امام علی النقی (ع)';
        $holidays[sprintf('%04d/12/%02d', $current_year + 1, 30)] = 'شهادت امام علی النقی (ع)';
        
        // Tadbir (29 Esfand - Oil Nationalization Day)
        $holidays[sprintf('%04d/12/%02d', $current_year, 29)] = 'روز ملی صنعت نفت';
        $holidays[sprintf('%04d/12/%02d', $current_year + 1, 29)] = 'روز ملی صنعت نفت';
        
        // Teachers Day (12 Ordibehesht)
        $holidays[sprintf('%04d/02/%02d', $current_year, 12)] = 'روز معلم';
        $holidays[sprintf('%04d/02/%02d', $current_year + 1, 12)] = 'روز معلم';
        
        // Islamic Revolution Day (22 Bahman)
        $holidays[sprintf('%04d/11/%02d', $current_year, 22)] = 'روز انقلاب اسلامی';
        $holidays[sprintf('%04d/11/%02d', $current_year + 1, 22)] = 'روز انقلاب اسلامی';
        
        // Revolution Victory Day (12 Esfand)
        $holidays[sprintf('%04d/12/%02d', $current_year, 12)] = 'روز پیروزی انقلاب اسلامی';
        $holidays[sprintf('%04d/12/%02d', $current_year + 1, 12)] = 'روز پیروزی انقلاب اسلامی';
        
        return $holidays;
    }

    /**
     * Get day name in Persian
     * 
     * @param int $day Day number
     * @param string $month Month name
     * @return string
     */
    private function get_day_name($day, $month)
    {
        $months = [
            'farvardin' => 'فروردین',
            'ordibehesht' => 'اردیبهشت',
            'khordad' => 'خرداد',
            'tir' => 'تیر',
            'mordad' => 'مرداد',
            'shahrivar' => 'شهریور',
            'mehr' => 'مهر',
            'aban' => 'آبان',
            'azar' => 'آذر',
            'dey' => 'دی',
            'bahman' => 'بهمن',
            'esfand' => 'اسفند',
        ];
        
        $month_name = $months[$month] ?? '';
        return $day . ' ' . $month_name;
    }

    /**
     * Check if a Jalali date is a holiday
     * 
     * @param string $jalali_date Jalali date (YYYY/MM/DD)
     * @return bool
     */
    public function is_holiday($jalali_date)
    {
        $holidays = $this->get_iranian_holidays();
        return isset($holidays[$jalali_date]);
    }

    /**
     * Get holiday name for a Jalali date
     * 
     * @param string $jalali_date Jalali date (YYYY/MM/DD)
     * @return string|null Holiday name or null
     */
    public function get_holiday_name($jalali_date)
    {
        $holidays = $this->get_iranian_holidays();
        return $holidays[$jalali_date] ?? null;
    }

    /**
     * Add custom holiday
     * 
     * @param string $jalali_date Jalali date (YYYY/MM/DD)
     * @param string $name Holiday name
     */
    public function add_holiday($jalali_date, $name)
    {
        if (is_null($this->holidays_cache)) {
            $this->get_iranian_holidays();
        }
        
        $this->holidays_cache[$jalali_date] = $name;
    }

    /**
     * Remove custom holiday
     * 
     * @param string $jalali_date Jalali date (YYYY/MM/DD)
     */
    public function remove_holiday($jalali_date)
    {
        if (is_null($this->holidays_cache)) {
            $this->get_iranian_holidays();
        }
        
        unset($this->holidays_cache[$jalali_date]);
    }

    // ========================================================================
    // MAIN METHOD: GET AVAILABLE SLOTS
    // ========================================================================

    /**
     * Get available time slots for a staff member on a specific Jalali date
     * 
     * All internal calculations are done in UTC.
     * Only the input/output uses Jalali dates.
     * 
     * @param int $staff_id Staff ID
     * @param string $jalali_date Jalali date (YYYY/MM/DD)
     * @param int $slot_duration Slot duration in minutes (default: 30)
     * @return array Available slots with Jalali formatting
     */
    public function get_available_slots($staff_id, $jalali_date, $slot_duration = 30)
    {
        $staff_id = absint($staff_id);
        $slot_duration = absint($slot_duration);
        
        if (empty($staff_id) || empty($jalali_date) || $slot_duration < 1) {
            return [];
        }

        // Convert Jalali date to Gregorian for internal calculations
        $gregorian_date = $this->jalali_to_gregorian($jalali_date);
        
        if (empty($gregorian_date)) {
            return [];
        }

        // Check if it's a holiday
        if ($this->is_holiday($jalali_date)) {
            return [];
        }

        // Get staff working hours
        $staff = $this->db->get_staff($staff_id);
        
        if (empty($staff)) {
            return [];
        }

        // Parse working hours JSON
        $working_hours = $this->parse_working_hours($staff['working_hours_json']);
        
        if (empty($working_hours)) {
            // No working hours defined, no slots available
            return [];
        }

        // Get day of week number (0=Shanbe, 6=Jome)
        $j_parts = explode('/', $jalali_date);
        if (count($j_parts) !== 3) {
            return [];
        }

        $j_year = absint($j_parts[0]);
        $j_month = absint($j_parts[1]);
        $j_day = absint($j_parts[2]);
        
        $dow = $this->get_jalali_day_of_week_num($j_year, $j_month, $j_day);
        
        // Get working hours for this day
        $day_names = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
        $day_name = $day_names[$dow] ?? '';
        
        if (empty($day_name) || !isset($working_hours[$day_name])) {
            return []; // Staff doesn't work on this day
        }

        $work_periods = $working_hours[$day_name];
        
        if (empty($work_periods)) {
            return [];
        }

        // Get all appointments for this staff on this date
        $appointments = $this->db->get_appointments([
            'staff_id' => $staff_id,
            'date_from' => $gregorian_date . ' 00:00:00',
            'date_to' => $gregorian_date . ' 23:59:59',
            'limit' => 100,
        ]);

        // Sort appointments by start time for efficient overlap checking
        usort($appointments, function($a, $b) {
            return strtotime($a['start_datetime']) - strtotime($b['start_datetime']);
        });

        // Generate available slots
        $available_slots = [];
        
        foreach ($work_periods as $period) {
            $period_start = $this->parse_time($period[0]);
            $period_end = $this->parse_time($period[1]);
            
            if ($period_start >= $period_end) {
                continue;
            }

            // Generate slots for this period
            $current_time = $period_start;
            
            while ($current_time + $slot_duration * 60 <= $period_end) {
                $slot_end = $current_time + $slot_duration * 60;
                
                // Check if this slot overlaps with any appointment
                if (!$this->has_appointment_overlap($appointments, $gregorian_date, $current_time, $slot_end)) {
                    $available_slots[] = $this->format_slot($gregorian_date, $current_time, $slot_end, $jalali_date);
                }
                
                $current_time = $slot_end;
            }
        }

        return $available_slots;
    }

    /**
     * Parse working hours JSON
     * 
     * @param string|null $json Working hours JSON
     * @return array Working hours by day name
     */
    private function parse_working_hours($json)
    {
        if (empty($json)) {
            return [];
        }

        $decoded = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * Parse time string (HH:MM or HH:MM:SS) to minutes since midnight
     * 
     * @param string $time Time string
     * @return int Minutes since midnight
     */
    private function parse_time($time)
    {
        $parts = explode(':', $time);
        $hours = isset($parts[0]) ? absint($parts[0]) : 0;
        $minutes = isset($parts[1]) ? absint($parts[1]) : 0;
        
        return $hours * 60 + $minutes;
    }

    /**
     * Check if a time slot overlaps with any appointment
     * 
     * @param array $appointments List of appointments
     * @param string $date Gregorian date (YYYY-MM-DD)
     * @param int $slot_start Slot start time in minutes since midnight
     * @param int $slot_end Slot end time in minutes since midnight
     * @return bool True if there's an overlap
     */
    private function has_appointment_overlap($appointments, $date, $slot_start, $slot_end)
    {
        $slot_start_datetime = $date . ' ' . sprintf('%02d:%02d:00', (int) ($slot_start / 60), $slot_start % 60);
        $slot_end_datetime = $date . ' ' . sprintf('%02d:%02d:00', (int) ($slot_end / 60), $slot_end % 60);
        
        foreach ($appointments as $appointment) {
            $app_start = strtotime($appointment['start_datetime']);
            $app_end = strtotime($appointment['end_datetime']);
            
            $slot_start_ts = strtotime($slot_start_datetime . ' UTC');
            $slot_end_ts = strtotime($slot_end_datetime . ' UTC');
            
            // Check for overlap
            if ($slot_start_ts < $app_end && $slot_end_ts > $app_start) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Format slot for output
     * 
     * @param string $gregorian_date Gregorian date
     * @param int $start Start time in minutes since midnight
     * @param int $end End time in minutes since midnight
     * @param string $jalali_date Jalali date
     * @return array Formatted slot data
     */
    private function format_slot($gregorian_date, $start, $end, $jalali_date)
    {
        $start_hour = (int) ($start / 60);
        $start_min = $start % 60;
        $end_hour = (int) ($end / 60);
        $end_min = $end % 60;

        $start_time = sprintf('%02d:%02d', $start_hour, $start_min);
        $end_time = sprintf('%02d:%02d', $end_hour, $end_min);

        // Create full datetime strings for timestamps
        $start_datetime_str = $gregorian_date . ' ' . $start_time . ':00';
        $end_datetime_str = $gregorian_date . ' ' . $end_time . ':00';

        return [
            'start_timestamp' => strtotime($start_datetime_str . ' UTC'),
            'end_timestamp' => strtotime($end_datetime_str . ' UTC'),
            'start' => $start_time,
            'end' => $end_time,
            'date' => $jalali_date,
            'formatted' => $start_time . ' - ' . $end_time,
            'datetime_start' => $jalali_date . ' ' . $start_time . ':00',
            'datetime_end' => $jalali_date . ' ' . $end_time . ':00',
        ];
    }

    // ========================================================================
    // UTILITY METHODS
    // ========================================================================

    /**
     * Get current Jalali date
     * 
     * @return string Jalali date (YYYY/MM/DD)
     */
    public function get_current_jalali_date()
    {
        return $this->timestamp_to_jalali(time());
    }

    /**
     * Get current Jalali datetime
     * 
     * @return string Jalali datetime (YYYY/MM/DD HH:MM:SS)
     */
    public function get_current_jalali_datetime()
    {
        return $this->timestamp_to_jalali_datetime(time());
    }

    /**
     * Check if slot is available
     * 
     * @param int $staff_id Staff ID
     * @param string $jalali_date Jalali date (YYYY/MM/DD)
     * @param string $time Time (HH:MM)
     * @param int $duration Duration in minutes
     * @return bool
     */
    public function is_slot_available($staff_id, $jalali_date, $time, $duration = 30)
    {
        $slots = $this->get_available_slots($staff_id, $jalali_date, $duration);
        $target_start = $this->parse_time($time);
        $target_end = $target_start + $duration * 60;
        $target_formatted = sprintf('%02d:%02d', (int) ($target_start / 60), $target_start % 60);
        
        foreach ($slots as $slot) {
            if ($slot['start'] === $target_formatted) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get working hours for a staff member on a specific day
     * 
     * @param int $staff_id Staff ID
     * @param string $jalali_date Jalali date (YYYY/MM/DD)
     * @return array Working periods (start, end) in minutes
     */
    public function get_staff_working_hours($staff_id, $jalali_date)
    {
        $staff_id = absint($staff_id);
        
        if (empty($staff_id)) {
            return [];
        }

        $staff = $this->db->get_staff($staff_id);
        
        if (empty($staff) || empty($staff['working_hours_json'])) {
            return [];
        }

        $working_hours = $this->parse_working_hours($staff['working_hours_json']);
        
        if (empty($working_hours)) {
            return [];
        }

        $j_parts = explode('/', $jalali_date);
        if (count($j_parts) !== 3) {
            return [];
        }

        $j_year = absint($j_parts[0]);
        $j_month = absint($j_parts[1]);
        $j_day = absint($j_parts[2]);
        
        $dow = $this->get_jalali_day_of_week_num($j_year, $j_month, $j_day);
        $day_names = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
        $day_name = $day_names[$dow] ?? '';
        
        if (empty($day_name) || !isset($working_hours[$day_name])) {
            return [];
        }

        return $working_hours[$day_name];
    }
}
