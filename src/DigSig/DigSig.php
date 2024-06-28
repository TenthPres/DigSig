<?php
/**
 * @package DigSig
 */
namespace tp\DigSig;

use WP;
use WP_Http;

if ( ! defined('ABSPATH')) {
    exit;
}


/**
 * Main plugin class.
 */
class DigSig
{

    /**
     * Version number
     */
    public const VERSION = "1.0.1";

    public const DEBUG = false;

    /**
     * The Token
     */
    public const TOKEN = "DigSig";

    /**
     * Text domain for translation files
     */
    public const TEXT_DOMAIN = "DigSig";

    /**
     * API Endpoint prefix, and specific endpoints.  All must be lower-case.
     */
    public const API_ENDPOINT = "digsig";

    /**
     * Prefix to use for all settings.
     */
    public const SETTINGS_PREFIX = "digsig_";

    /**
     * Caching
     */
    public const CACHE_PUBLIC = 0;
    public const CACHE_PRIVATE = 10;
    public const CACHE_NONE = 20;
    private static int $cacheLevel = self::CACHE_PUBLIC;

    /**
     * The singleton.
     */
    private static ?DigSig $_instance = null;

    /**
     * Settings object
     */
    public ?DigSig_Settings $settings = null;

    /**
     * The main plugin file.
     */
    public string $file;

    /**
     * The main plugin directory.
     */
    public static string $dir;

    /**
     * The plugin assets directory.
     */
    public string $assets_dir;

    /**
     * The plugin assets URL, with trailing slash.
     */
    public string $assets_url;

    /**
     * Suffix for JavaScripts.
     */
    public string $script_suffix;

    /**
     * @var ?bool True after the RSVP feature is loaded.
     */
    protected ?bool $rsvp = null;

    /**
     * @var ?bool The Auth object for the Authentication tool, if feature is enabled.
     */
    protected ?bool $auth = null;

    /**
     * @var ?bool True after the Involvements feature is loaded.
     */
    protected ?bool $involvements = null;

    /**
     * @var ?bool True after the Global feature is loaded.
     */
    protected ?bool $global = null;


    /**
     * @var ?bool True after the People feature is loaded.
     */
    protected ?bool $people = null;

    /**
     * @var ?WP_Http Object for API requests.
     */
    private ?WP_Http $httpClient = null;

    /** @var string Used to denote requests made in special circumstances, such as through the TouchPoint-WP API */
    protected static string $context = "";

    /**
     * Indicates that the current request is being processed through the API.
     *
     * @return bool
     */
    public static function isApi(): bool
    {
        return self::$context === "api";
    }

    /**
     * Constructor function.
     *
     * @param string $file
     */
    protected function __construct(string $file = '')
    {
        // Load plugin environment variables.
        $this->file       = $file;
        self::$dir        = dirname($this->file);
        $this->assets_dir = trailingslashit(self::$dir) . 'assets';
        $this->assets_url = esc_url(trailingslashit(plugins_url('/assets/', $this->file)));

        $this->script_suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        register_activation_hook($this->file, [$this, 'activation']);
        register_deactivation_hook($this->file, [$this, 'deactivation']);
        register_uninstall_hook($this->file, [self::class, 'uninstall']);

        add_filter('do_parse_request', [$this, 'parseRequest'], 10, 3);

        // Handle localisation.
        $this->loadPluginTextdomain();
        add_action('init', [$this, 'loadLocalisation'], 0);
    }


    public static function setCaching(int $level): void
    {
        self::$cacheLevel = max(self::$cacheLevel, $level);
    }


    /**
     * Spit out headers that prevent caching.  Useful for API calls.
     */
    public static function doCacheHeaders(int $cacheLevel = null): void
    {
        if ($cacheLevel !== null) {
            self::setCaching($cacheLevel);
        }

        switch (self::$cacheLevel) {
            case self::CACHE_NONE:
                header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                header("Cache-Control: post-check=0, pre-check=0", false);
                header("Pragma: no-cache");
                break;
            case self::CACHE_PRIVATE:
                header("Cache-Control: max-age=300, must-revalidate, private");
                break;
        }
    }

    /**
     * @param bool      $continue   Whether to parse the request
     * @param WP        $wp         Current WordPress environment instance
     * @param array|string $extraVars Passed query variables
     *
     * @return bool Whether other request parsing functions should be allowed to function.
     *
     * @noinspection PhpMissingParamTypeInspection
     * @noinspection PhpUnusedParameterInspection WordPress API
     */
    public function parseRequest($continue, $wp, $extraVars): bool
    {
        if ($continue) {
            $reqUri = parse_url(trim($_SERVER['REQUEST_URI'], '/'));
            $reqUri['path'] = $reqUri['path'] ?? "";

            // Remove trailing slash if it exists (and, it probably does)
            if (substr($reqUri['path'], -1) === '/')
                $reqUri['path'] = substr($reqUri['path'], 0, -1);

            // Explode by slashes
            $reqUri['path'] = explode("/", $reqUri['path'] ?? "");

            // Skip requests that categorically don't match
            if (count($reqUri['path']) < 1 || strtolower($reqUri['path'][0]) !== self::API_ENDPOINT) {
                return $continue;
            }

            // Parse parameters
            parse_str($reqUri['query'] ?? '', $queryParams);
            $reqUri['query'] = $queryParams;
            unset($queryParams);

            self::$context = "api";

            // Default for template
            if (count($reqUri['path']) === 1) {
                header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                header("Cache-Control: post-check=0, pre-check=0", false);
                header("Pragma: no-cache");

                $template = file_get_contents(__DIR__ . "/template.html");
                $template = str_replace("{{static}}", plugins_url("digsig/static/"), $template);
                $template = str_replace("{{version}}", DigSig::VERSION, $template);
                echo $template;
                exit;
            }

            if (count($reqUri['path']) === 2 && strtolower($reqUri['path']['1']) === 'data') {
                self::doApiRequest();
            }
        }

        self::$context = "";
        return $continue;
    }



    protected function doApiRequest(): void
    {


        $eventsList = [];

        if (is_plugin_active('touchpoint-wp/touchpoint-wp.php')) {
            $q = new \WP_Query();

            $existingMq = $q->get('meta_query');

            if (!class_exists('\tp\TouchPointWP\Meeting')) {
                return;
            }

            $now = current_datetime();
            $later = $now->modify('+93 days');

            $mq = [
                [
                    [
                        'key' => \tp\TouchPointWP\Meeting::MEETING_END_META_KEY,
                        'value' => $now->format('U'),
                        'compare' => ">="
                    ],
                    [ // This condition is to allow for the possibility of events without end times.
                        [
                            'key' => \tp\TouchPointWP\Meeting::MEETING_END_META_KEY,
                            'compare' => '=',
                            'value' => 0
                        ],
                        [
                            'key' => \tp\TouchPointWP\Meeting::MEETING_START_META_KEY,
                            'value' => $now->format('U'),
                            'compare' => ">"
                        ],
                        'relation' => 'AND'
                    ],
                    'relation' => 'OR'
                ],
                [
                    'key' => \tp\TouchPointWP\Meeting::MEETING_META_KEY,
                    'value' => 0,
                    'compare' => ">"
                ],
                [
                    'key' => \tp\TouchPointWP\Meeting::MEETING_START_META_KEY,
                    'value' => $later->format('U'),
                    'compare' => "<"
                ],
                'relation' => 'AND'
            ];

            if (!empty($existingMq)) {
                $mq = [
                    'relation' => 'AND',
                    $existingMq,
                    $mq,
                ];
            }

            $q->set('meta_query', $mq);

            $q->set('meta_key', \tp\TouchPointWP\Meeting::MEETING_START_META_KEY);
            $q->set('orderby', 'meta_value');
            $q->set('order', 'ASC');
            $q->set('posts_per_page', 200);

            $q->set('post_type', \tp\TouchPointWP\Involvement_PostTypeSettings::getPostTypes());

            $iids = [];

            foreach ($q->get_posts() as $eQ) {
                /** @var WP_Post $eQ */
                global $post;
                $post = $eQ;

                try {
                    $e = \tp\TouchPointWP\Meeting::fromPost($eQ);
                } catch (\tp\TouchPointWP\TouchPointWP_Exception $e) {
                    continue;
                }

                $iid = $e->involvement()->getTouchPointId();
                if (in_array($iid, $iids)) {
                    continue;
                }
                $iids[] = $iid;

                $eO = [];

                $eO['title'] = $eQ->post_title;

                $e = \tp\TouchPointWP\Meeting::fromPost($eQ);

                $eO['allDay'] = $e->isAllDay();

                $eO['shortUrl'] = ""; // TODO

                $eO['start_date'] = $e->startDt->format('Y-m-d H:i:s');
                $eO['end_date'] = $e->endDt ? $e->endDt->format('Y-m-d H:i:s') : $eO['start_date'];

                $eO['ministry'] = null; // TODO

                $eO['location'] = $e->locationName();
                $eO['venue'] = ['venue' => $eO['location']];

                $eO['categories'] = ['name' => null]; // TODO

                $image = get_the_post_thumbnail_url($eQ, 'full');
                $eO['image'] = (!!$image ? ['url' => $image] : false);

                $eO['imageUrl'] = $image;

                $eO['url'] = $e->permalink();

                $eO['slide'] = null; // TODO

                $eO['IID'] = $iid;

                $eventsList[] = $eO;
            }

            header('Content-Type: application/json');
            echo json_encode(['events' => $eventsList]);
            exit;

        } elseif (function_exists('tribe_get_events')) {
            $d0 = current_datetime();
            $d1 = $d0->modify('+93 days');
            header("Location: /wp-json/tribe/events/v1/events?featured=true&start_date=" .
                $d0->format('Y-m-d') . "&end_date=" . $d1->format('Y-m-d') .
                "&hide_subsequent_recurrences=1&per_page=200");
            exit;
        }
    }

    /**
     * Load plugin textdomain
     */
    public function loadPluginTextdomain()
    {
        $locale = apply_filters('plugin_locale', get_locale(), self::TEXT_DOMAIN);

        load_textdomain(
            self::TEXT_DOMAIN,
            WP_LANG_DIR . '/' . self::TEXT_DOMAIN . '/' . self::TEXT_DOMAIN . '-' . $locale . '.mo'
        );
        load_plugin_textdomain(self::TEXT_DOMAIN, false, dirname(plugin_basename($this->file)) . '/lang/');
    }

    /**
     * Compare the version numbers to determine if a migration is needed.
     */
    public function checkMigrations(): void
    {
        if ($this->settings->version !== self::VERSION) {
            $this->settings->migrate();
        }
    }

    /**
     * Load the settings, connect the references, and check that there aren't pending migrations.
     *
     * @param $file
     *
     * @return DigSig
     */
    public static function load($file): DigSig
    {
        $instance = self::instance($file);

        if (is_null($instance->settings)) {
            $instance->settings = DigSig_Settings::instance($instance);
            $instance->checkMigrations();
        }

        add_action('init', [self::class, 'init']);

        return $instance;
    }

    public static function init(): void
    {
        self::instance()->registerTaxonomies();
    }

    public function registerTaxonomies(): void
    {
    }

    /**
     * Main DigSig Instance
     *
     * Ensures only one instance of DigSig is loaded or can be loaded.
     *
     * @return DigSig instance
     * @see DigSig()
     */
    public static function instance($file = ''): DigSig
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self($file);
        }

        return self::$_instance;
    }

    /**
     * Load plugin localisation
     */
    public function loadLocalisation()
    {
        load_plugin_textdomain(self::TEXT_DOMAIN, false, dirname(plugin_basename($this->file)) . '/lang/');
    }

    /**
     * Don't clone.
     */
    public function __clone()
    {
        _doing_it_wrong(
            __FUNCTION__,
            esc_html(__('Cloning DigSig is questionable.  Don\'t do it.')),
            esc_attr(self::VERSION)
        );
    }

    /**
     * don't deserialize.
     */
    public function __wakeup()
    {
        _doing_it_wrong(
            __FUNCTION__,
            esc_html(__('Deserializing DigSig is questionable.  Don\'t do it.')),
            esc_attr(self::VERSION)
        );
    }

    /**
     * Activation. Runs on activation.
     */
    public function activation()
    {
        $this->createTables();

        $this->settings->migrate();
    }

    /**
     * Deactivation. Runs on deactivation.
     */
    public function deactivation()
    {
        $this->_log_version_number();
        flush_rewrite_rules();
    }

    /**
     * Uninstallation. Runs on uninstallation.
     */
    public static function uninstall()
    {
        self::dropTables();
    }


    /**
     * Create or update database tables
     */
    protected function createTables(): void
    {
    }

    /**
     * Drop database tables at uninstallation.
     */
    protected static function dropTables(): void
    {
    }

    /**
     * Log the plugin version number.
     */
    private function _log_version_number()
    {
        update_option(self::TOKEN . '_version', self::VERSION, false);
    }

    /**
     * Indicates that Tribe Calendar Pro is enabled.
     *
     * @return bool
     */
    public static function useTribeCalendarPro(): bool
    {
        if ( ! function_exists( 'is_plugin_active' ) ){
            require_once(ABSPATH . '/wp-admin/includes/plugin.php' );
        }

        return is_plugin_active( 'events-calendar-pro/events-calendar-pro.php');
    }

    /**
     * Indicates that Tribe Calendar is enabled.
     *
     * @return bool
     */
    public static function useTribeCalendar(): bool
    {
        return self::useTribeCalendarPro() || is_plugin_active( 'the-events-calendar/the-events-calendar.php');
    }
}