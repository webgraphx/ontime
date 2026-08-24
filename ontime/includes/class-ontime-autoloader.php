<?php
/**
 * OnTime PSR-4 Autoloader
 * 
 * Custom autoloader for OnTime plugin when Composer is not available
 * 
 * @package OnTime
 * @subpackage Core
 * @since 1.0.0
 */

namespace OnTime;

/**
 * PSR-4 compliant autoloader for OnTime plugin
 */
class Autoloader
{
    /**
     * Base directory for OnTime namespace
     * @var string
     */
    private $base_dir;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->base_dir = ONTIME_PLUGIN_DIR;
        spl_autoload_register([$this, 'autoload']);
    }

    /**
     * Autoload classes
     * 
     * @param string $class Class name to load
     */
    public function autoload($class)
    {
        // Only handle OnTime namespace
        if (strpos($class, 'OnTime\\') !== 0) {
            return;
        }

        // Remove namespace prefix
        $relative_class = substr($class, 7); // Remove 'OnTime\\'
        
        // Replace namespace separators with directory separators
        $relative_path = str_replace('\\', DIRECTORY_SEPARATOR, $relative_class) . '.php';

        // Build full path
        $file = $this->base_dir . $relative_path;

        // If the file exists, require it
        if (file_exists($file)) {
            require_once $file;
        }
    }
}
