<?php
/**
 * PHPUnit Test Bootstrap for myadmin-vps-module
 *
 * Sets up the minimal environment needed for isolated unit testing
 * without requiring the full MyAdmin application stack.
 */

// Define the PRORATE_BILLING constant used in Plugin::$settings
if (!defined('PRORATE_BILLING')) {
    define('PRORATE_BILLING', 1);
}

// Define MYSQL_ASSOC if not already defined
if (!defined('MYSQL_ASSOC')) {
    define('MYSQL_ASSOC', 1);
}

// Autoload via Composer
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../../vendor/autoload.php',
];

$autoloaded = false;
foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        $autoloaded = true;
        break;
    }
}

if (!$autoloaded) {
    // Fallback: register a basic PSR-4 autoloader for the source namespace
    spl_autoload_register(function (string $class): void {
        $prefix = 'Detain\\MyAdminVps\\';
        if (strpos($class, $prefix) === 0) {
            $relativeClass = substr($class, strlen($prefix));
            $file = __DIR__ . '/../src/' . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    });
}

// Stub global functions that the source code calls but are provided
// by the broader MyAdmin framework at runtime.

if (!function_exists('myadmin_log')) {
    /**
     * @param string $section
     * @param string $level
     * @param string $message
     * @param string $line
     * @param string $file
     * @param string $module
     * @param int    $id
     */
    function myadmin_log($section, $level, $message, $line = '', $file = '', $module = '', $id = 0)
    {
        // No-op for testing
    }
}

if (!function_exists('function_requirements')) {
    function function_requirements($func)
    {
        // No-op for testing
    }
}

if (!function_exists('add_output')) {
    function add_output($html)
    {
        // No-op for testing
    }
}

if (!function_exists('get_module_settings')) {
    function get_module_settings($module)
    {
        return [
            'PREFIX' => 'vps',
            'TABLE' => 'vps',
            'TBLNAME' => 'VPS',
            'TITLE_FIELD' => 'vps_hostname',
            'TITLE_FIELD2' => 'vps_ip',
        ];
    }
}

if (!function_exists('get_module_db')) {
    function get_module_db($module)
    {
        return new class {
            public $Record = [];
            public function query($sql = '', $line = '', $file = '')
            {
            }
            public function next_record($type = null)
            {
                return false;
            }
            public function num_rows()
            {
                return 0;
            }
        };
    }
}

if (!function_exists('run_event')) {
    function run_event($event, $default = false, $module = '')
    {
        return $default;
    }
}

if (!function_exists('get_service_cost')) {
    function get_service_cost($serviceInfo, $module)
    {
        return ['frequency' => 1];
    }
}

if (!function_exists('get_frequency_discount')) {
    function get_frequency_discount($frequency)
    {
        return 1.0;
    }
}

if (!function_exists('get_coupon_cost')) {
    function get_coupon_cost($cost, $coupon)
    {
        return $cost;
    }
}

if (!function_exists('get_custid')) {
    function get_custid($account_id, $module)
    {
        return $account_id;
    }
}

if (!function_exists('validIp')) {
    function validIp($ip)
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
}

if (!function_exists('reverse_dns')) {
    function reverse_dns($ip, $host, $action)
    {
        // No-op for testing
    }
}

if (!function_exists('create_ticket')) {
    function create_ticket($custid, $body, $subject)
    {
        // No-op for testing
    }
}

if (!function_exists('convertCurrency')) {
    function convertCurrency($amount, $from, $to)
    {
        return new class ($amount) {
            private $amt;
            public function __construct($amt)
            {
                $this->amt = $amt;
            }
            public function getAmount()
            {
                $amt = $this->amt;
                return new class ($amt) {
                    private $amt;
                    public function __construct($amt)
                    {
                        $this->amt = $amt;
                    }
                    public function toFloat()
                    {
                        return (float) $this->amt;
                    }
                };
            }
        };
    }
}

if (!function_exists('get_orm_class_from_table')) {
    function get_orm_class_from_table($table)
    {
        return ucfirst($table);
    }
}

if (!function_exists('admin_email_vps_pending_setup')) {
    function admin_email_vps_pending_setup($id)
    {
        // No-op for testing
    }
}

// Stub for api_register used in Plugin::apiRegister
if (!function_exists('api_register')) {
    function api_register($name, $params, $returns, $desc, $auth = false, $admin = false)
    {
        // No-op for testing — captured by tests when needed
    }
}

if (!function_exists('api_register_array')) {
    function api_register_array($name, $fields)
    {
        // No-op for testing — captured by tests when needed
    }
}

// Stub for validate_buy_vps used by the API functions in src/api.php
if (!function_exists('validate_buy_vps')) {
    function validate_buy_vps($custid, $os, $slices, $platform, $controlpanel, $period, $location, $version, $hostname, $coupon, $rootpass)
    {
        return [
            'continue' => true,
            'errors' => [],
            'coupon_code' => 0,
            'service_cost' => 6.00,
            'slice_cost' => 6.00,
            'service_type' => 2,
            'repeat_slice_cost' => 6.00,
            'original_slice_cost' => 6.00,
            'original_cost' => 6.00,
            'repeat_service_cost' => 6.00,
            'monthly_service_cost' => 6.00,
            'custid' => $custid,
            'os' => $os,
            'slices' => $slices,
            'platform' => $platform,
            'controlpanel' => $controlpanel,
            'period' => $period,
            'location' => $location,
            'version' => $version,
            'hostname' => $hostname,
            'coupon' => $coupon,
            'rootpass' => $rootpass,
        ];
    }
}

// Stub for place_buy_vps used by the API functions in src/api.php
if (!function_exists('place_buy_vps')) {
    function place_buy_vps(...$args)
    {
        return [
            'total_cost' => 6.00,
            'real_iids' => [12345],
            'serviceid' => 100,
        ];
    }
}

// Set up minimal $GLOBALS['tf'] for API function tests
if (!isset($GLOBALS['tf'])) {
    $GLOBALS['tf'] = new class {
        public $session;
        public $ima = 'client';
        public $history;
        public $accounts;

        public function __construct()
        {
            $this->session = new class {
                public $account_id = 1;
            };
            $this->history = new class {
                public function add(...$args)
                {
                }
            };
            $this->accounts = new class {
                public function cross_reference($id)
                {
                    return $id;
                }
            };
        }
    };
}
