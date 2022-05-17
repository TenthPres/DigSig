<?php
/**
 * @package DigSig
 */
namespace tp\DigSig;

if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * The Settings class - most settings are available through the default getter.
 *
 * @property-read string version The plugin version.  Used for tracking updates.
 *
 */
class DigSig_Settings
{

    /**
     * The singleton of DigSig_Settings.
     */
    private static ?DigSig_Settings $_instance = null;

    /**
     * The main plugin object.
     */
    public ?DigSig $parent = null;

    /**
     * Available settings for plugin.
     */
    protected array $settings = [];

    public const UNDEFINED_PLACEHOLDER = INF;

    /**
     * Constructor function.
     *
     * @param DigSig $parent Parent object.
     */
    public function __construct(DigSig $parent)
    {
        $this->parent = $parent;

        // Initialise settings.
        add_action('init', [$this, 'initSettings'], 11);

        // Register plugin settings.
        add_action('admin_init', [$this, 'registerSettings']);

        // Add settings page to menu.
        add_action('admin_menu', [$this, 'add_menu_item']);

        // Add settings link to plugins page.
        add_filter(
            'plugin_action_links_' . plugin_basename($this->parent->file),
            [
                $this,
                'add_settings_link',
            ]
        );

        // Configure placement of plugin settings page. See readme for implementation.
        add_filter(DigSig::SETTINGS_PREFIX . 'menu_settings', [$this, 'configureSettings']);
    }

    /**
     * Main DigSig_Settings Instance
     *
     * Ensures only one instance of DigSig_Settings is loaded or can be loaded.
     *
     * @param ?DigSig $parent Object instance.
     *
     * @return DigSig_Settings instance
     * @since 1.0.0
     * @static
     * @see DigSig()
     */
    public static function instance(?DigSig $parent = null): DigSig_Settings
    {
        if (is_null($parent)) {
            $parent = DigSig::instance();
        }

        if (is_null(self::$_instance)) {
            self::$_instance = new self($parent);
        }

        return self::$_instance;
    }

    /**
     * Initialise settings
     *
     * @return void
     */
    public function initSettings(): void
    {
        $this->settings = $this->settingsFields();
    }

    /**
     * Build settings fields
     *
     * @param bool|string $includeDetail Set to true to get options from TouchPoint, likely including the API calls. Set
     *                      to the key of a specific page to only load options for that page.
     *
     * @return array Fields to be displayed on settings page
     */
    private function settingsFields($includeDetail = false): array
    {
        if (count($this->settings) > 0 && $includeDetail === false) {
            // Settings are already loaded, and they have adequate detail for the task at hand.
            return $this->settings;
        }

        /*	$settings['general'] = [
                'title'       => __( 'Standard', DigSig::TEXT_DOMAIN ),
                'description' => __( 'These are fairly standard form input fields.', DigSig::TEXT_DOMAIN ),
                'fields'      => [
                    [
                        'id'          => 'text_field',
                        'label'       => __( 'Some Text', DigSig::TEXT_DOMAIN ),
                        'description' => __( 'This is a standard text field.', DigSig::TEXT_DOMAIN ),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => __( 'Placeholder text', DigSig::TEXT_DOMAIN ),
                    ],
                    [
                        'id'          => 'password_field',
                        'label'       => __( 'A Password', DigSig::TEXT_DOMAIN ),
                        'description' => __( 'This is a standard password field.', DigSig::TEXT_DOMAIN ),
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => __( 'Placeholder text', DigSig::TEXT_DOMAIN ),
                    ],
                    [
                        'id'          => 'secret_text_field',
                        'label'       => __( 'Some Secret Text', DigSig::TEXT_DOMAIN ),
                        'description' => __( 'This is a secret text field - any data saved here will not be displayed after the page has reloaded, but it will be saved.', DigSig::TEXT_DOMAIN ),
                        'type'        => 'text_secret',
                        'default'     => '',
                        'placeholder' => __( 'Placeholder text', DigSig::TEXT_DOMAIN ),
                    ],
                    [
                        'id'          => 'text_block',
                        'label'       => __( 'A Text Block', DigSig::TEXT_DOMAIN ),
                        'description' => __( 'This is a standard text area.', DigSig::TEXT_DOMAIN ),
                        'type'        => 'textarea',
                        'default'     => '',
                        'placeholder' => __( 'Placeholder text for this textarea', DigSig::TEXT_DOMAIN ),
                    ],
                    [
                        'id'          => 'single_checkbox',
                        'label'       => __( 'An Option', DigSig::TEXT_DOMAIN ),
                        'description' => __( 'A standard checkbox - if you save this option as checked then it will store the option as \'on\', otherwise it will be an empty string.', DigSig::TEXT_DOMAIN ),
                        'type'        => 'checkbox',
                        'default'     => '',
                    ],
                    [
                        'id'          => 'select_box',
                        'label'       => __( 'A Select Box', DigSig::TEXT_DOMAIN ),
                        'description' => __( 'A standard select box.', DigSig::TEXT_DOMAIN ),
                        'type'        => 'select',
                        'options'     => [
                            'drupal'    => 'Drupal',
                            'joomla'    => 'Joomla',
                            'wordpress' => 'WordPress',
                        ],
                        'default'     => 'wordpress',
                    ],
                    [
                        'id'          => 'radio_buttons',
                        'label'       => __( 'Some Options', DigSig::TEXT_DOMAIN ),
                        'description' => __( 'A standard set of radio buttons.', DigSig::TEXT_DOMAIN ),
                        'type'        => 'radio',
                        'options'     => [
                            'superman' => 'Superman',
                            'batman'   => 'Batman',
                            'ironman'  => 'Iron Man',
                        ],
                        'default'     => 'batman',
                    ],
                    [
                        'id'          => 'multiple_checkboxes',
                        'label'       => __( 'Some Items', DigSig::TEXT_DOMAIN ),
                        'description' => __( 'You can select multiple items and they will be stored as an array.', DigSig::TEXT_DOMAIN ),
                        'type'        => 'checkbox_multi',
                        'options'     => [
                            'square'    => 'Square',
                            'circle'    => 'Circle',
                            'rectangle' => 'Rectangle',
                            'triangle'  => 'Triangle',
                        ],
                        'default'     => [ 'circle', 'triangle' ],
                    ],
                ],
            ];

            $settings['extra'] = array(
                'title'       => __( 'Extra', DigSig::TEXT_DOMAIN ),
                'description' => __( "These are some extra input fields that maybe aren't as common as the others.", DigSig::TEXT_DOMAIN ),
                'fields'      => array(
                    array(
                        'id'          => 'number_field',
                        'label'       => __( 'A Number', DigSig::TEXT_DOMAIN ),
                        'description' => __( 'This is a standard number field - if this field contains anything other than numbers then the form will not be submitted.', DigSig::TEXT_DOMAIN ),
                        'type'        => 'number',
                        'default'     => '',
                        'placeholder' => __( '42', DigSig::TEXT_DOMAIN ),
                    ),
                    array(
                        'id'          => 'colour_picker',
                        'label'       => __( 'Pick a colour', DigSig::TEXT_DOMAIN ),
                        'description' => __( 'This uses WordPress\' built-in colour picker - the option is stored as the colour\'s hex code.', DigSig::TEXT_DOMAIN ),
                        'type'        => 'color',
                        'default'     => '#21759B',
                    ),
                    array(
                        'id'          => 'an_image',
                        'label'       => __( 'An Image', DigSig::TEXT_DOMAIN ),
                        'description' => __( 'This will upload an image to your media library and store the attachment ID in the option field. Once you have uploaded an image the thumbnail will display above these buttons.', DigSig::TEXT_DOMAIN ),
                        'type'        => 'image',
                        'default'     => '',
                        'placeholder' => '',
                    ),
                    array(
                        'id'          => 'multi_select_box',
                        'label'       => __( 'A Multi-Select Box', DigSig::TEXT_DOMAIN ),
                        'description' => __( 'A standard multi-select box - the saved data is stored as an array.', DigSig::TEXT_DOMAIN ),
                        'type'        => 'select_multi',
                        'options'     => array(
                            'linux'   => 'Linux',
                            'mac'     => 'Mac',
                            'windows' => 'Windows',
                        ),
                        'default'     => array( 'linux' ),
                    ),
                ),
            ); */

        $this->settings = apply_filters($this->parent::TOKEN . '_settings_fields', $this->settings);

        return $this->settings;
    }

    /**
     * Returns a placeholder for a password setting field that doesn't expose the password itself.
     *
     * @param string $settingName
     *
     * @return string
     * @noinspection PhpSameParameterValueInspection
     */
    private function passwordPlaceholder(string $settingName): string
    {
        $pass = $this->getWithoutDefault($settingName);
        if ($pass === '' || $pass === self::UNDEFINED_PLACEHOLDER) {
            return '';
        }
        return __('password saved', DigSig::TEXT_DOMAIN);
    }

    /**
     * Add settings page to admin menu
     *
     * @return void
     */
    public function add_menu_item()
    {
        $args = $this->menu_settings();

        // Do nothing if wrong location key is set.
        if (is_array($args) && isset($args['location']) && function_exists('add_' . $args['location'] . '_page')) {
            switch ($args['location']) {
                case 'options':
                case 'submenu':
                    add_submenu_page(
                        $args['parent_slug'],
                        $args['page_title'],
                        $args['menu_title'],
                        $args['capability'],
                        $args['menu_slug'],
                        $args['function']
                    );
                    break;
                case 'menu':
                    add_menu_page(
                        $args['page_title'],
                        $args['menu_title'],
                        $args['capability'],
                        $args['menu_slug'],
                        $args['function'],
                        $args['icon_url'],
                        $args['position']
                    );
                    break;
                default:
                    return;
            }
            // add_action('admin_print_styles-' . $page, [$this, 'settings_assets']);  TODO SOMEDAY MAYBE if needing to upload media through interface, uncomment this.
        }
    }

    /**
     * Prepare default settings page arguments
     *
     * @return mixed|void
     */
    private function menu_settings()
    {
        return apply_filters(
            DigSig::SETTINGS_PREFIX . 'menu_settings',
            [
                'location'    => 'options', // Possible settings: options, menu, submenu.
                'parent_slug' => 'options-general.php',
                'page_title'  => __('Dig(ital) Sig(nage)', DigSig::TEXT_DOMAIN),
                'menu_title'  => __('DigSig', DigSig::TEXT_DOMAIN),
                'capability'  => 'manage_options',
                'menu_slug'   => $this->parent::TOKEN . '_Settings',
                'function'    => [$this, 'settingsPage'],
                'icon_url'    => '',
                'position'    => null,
            ]
        );
    }

    /**
     * Container for settings page arguments
     *
     * @param ?array $settings Settings array.
     *
     * @return array
     */
    public function configureSettings(?array $settings = []): array
    {
        return $settings;
    }

    /**
     * Add settings link to plugin list table
     *
     * @param array $links Existing links.
     *
     * @return array        Modified links.
     */
    public function add_settings_link(array $links): array
    {
        $settings_link = '<a href="options-general.php?page=' . $this->parent::TOKEN . '_Settings">' . __(
                'Settings',
                DigSig::TEXT_DOMAIN
            ) . '</a>';
        $links[]       = $settings_link;

        return $links;
    }

    /**
     * @param string $what The field to get a value for
     *
     * @return string|false  The value, if set.  False if not set.
     */
    public function __get(string $what)
    {
        return $this->get($what);
    }

    /**
     * @param string $what
     *
     * @return false|string|array
     */
    public function get(string $what)
    {
        $v = $this->getWithoutDefault($what);

        if ($v === self::UNDEFINED_PLACEHOLDER) {
            $v = $this->getDefaultValueForSetting($what);
        }
        if ($v === self::UNDEFINED_PLACEHOLDER) {
            $v = false;
        }

        return $v;
    }

    /**
     * @param string $what The field to get a value for
     * @param mixed  $default Default value to use.  Defaults to UNDEFINED_PLACEHOLDER
     *
     * @return mixed  The value, if set.  False if not set.
     */
    protected function getWithoutDefault(string $what, $default = self::UNDEFINED_PLACEHOLDER)
    {
        $opt = get_option(DigSig::SETTINGS_PREFIX . $what, $default); // TODO MULTI

        if ($opt === '')
            return self::UNDEFINED_PLACEHOLDER;

        return $opt;
    }

    /**
     * @param string $what
     * @param mixed  $value
     * @param bool   $autoload
     *
     * @return false|mixed
     */
    public function set(string $what, $value, bool $autoload = false): bool
    {
        return update_option(DigSig::SETTINGS_PREFIX . $what, $value, $autoload); // TODO MULTI
    }


    /**
     * Migrate settings from version to version.  This may be called even when a migration isn't necessary.
     */
    public function migrate(): void
    {
        // Update version string
        $this->set('version', DigSig::VERSION);
    }

    /**
     * Register plugin settings
     *
     * @return void
     */
    public function registerSettings(): void
    {
        $currentSection = false;
        if (isset($_POST['tab']) && $_POST['tab']) {
            $currentSection = $_POST['tab'];
        } elseif (isset($_GET['tab']) && $_GET['tab']) {
            $currentSection = $_GET['tab'];
        }

        $this->settings = $this->settingsFields($currentSection);
        foreach ($this->settings as $section => $data) {
            // Check posted/selected tab.
            if ($currentSection && $currentSection !== $section) {
                continue;
            }

            // Add section to page.
            add_settings_section(
                $section,
                $data['title'],
                [$this, 'settings_section'],
                $this->parent::TOKEN . '_Settings'
            );

            foreach ($data['fields'] as $field) {
                // Validation callback for field.
                $args = [];
                if (isset($field['callback'])) {
                    $args['sanitize_callback'] = $field['callback'];
                }

                // Register field.  Don't save a value for instruction types.
                if ($field['type'] == 'instructions') {
                    $args['sanitize_callback'] = fn($new) => null;
                }

                $option_name = DigSig::SETTINGS_PREFIX . $field['id'];
                register_setting($this->parent::TOKEN . '_Settings', $option_name, $args);

                // Add field to page.
                add_settings_field(
                    $field['id'],
                    $field['label'],
                    [$this->parent->admin(), 'displayField'],
                    $this->parent::TOKEN . '_Settings',
                    $section,
                    [
                        'field'  => $field,
                        'prefix' => DigSig::SETTINGS_PREFIX,
                    ]
                );
            }

            if ( ! $currentSection) {
                break;
            }
        }
    }

    /**
     * Gets the default value for a setting field, if one exists.  Otherwise, the UNDEFINED_PLACEHOLDER is returned.
     *
     * @param string $id
     *
     * @return mixed
     */
    protected function getDefaultValueForSetting(string $id)
    {
        if (substr($id, 0, 7) === "enable") {  // Prevents settings content from needing to be generated for these settings.
            return '';
        }
        foreach ($this->settingsFields() as $category) {
            foreach ($category['fields'] as $field) {
                if ($field['id'] === $id) {
                    if (array_key_exists('default', $field)) {
                        return $field['default'];
                    }
                    return self::UNDEFINED_PLACEHOLDER;
                }
            }
        }
        return self::UNDEFINED_PLACEHOLDER;
    }

    /**
     * Settings section.
     *
     * @param array $section Array of section ids.
     *
     * @return void
     */
    public function settings_section(array $section): void
    {
        $html = '<p> ' . $this->settings[$section['id']]['description'] . '</p>' . "\n";
        echo $html;
    }

    /**
     * Load settings page content.
     *
     * @return void
     */
    public function settingsPage(): void
    {
        // Build page HTML.
        $html = '<div class="wrap" id="' . $this->parent::TOKEN . '_Settings">' . "\n";
        $html .= '<h2>' . __('Dig(ital) Sig(nage) Settings', DigSig::TEXT_DOMAIN) . '</h2>' . "\n";

        $tab = '';

        if (isset($_GET['tab']) && $_GET['tab']) {
            $tab .= $_GET['tab'];
        }

        // Show page tabs.
        if (count($this->settings) > 1) {
            $html .= '<h2 class="nav-tab-wrapper">' . "\n";

            $c = 0;
            foreach ($this->settings as $section => $data) {
                // Set tab class.
                $class = 'nav-tab';
                if ( ! isset($_GET['tab']) && 0 === $c) {
                    $class .= ' nav-tab-active';
                } elseif (isset($_GET['tab']) && $section == $_GET['tab']) {
                    $class .= ' nav-tab-active';
                }

                // Set tab link.
                $tab_link = add_query_arg(array('tab' => $section));
                if (isset($_GET['settings-updated'])) {
                    $tab_link = remove_query_arg('settings-updated', $tab_link);
                }

                // Output tab.
                $html .= '<a href="' . $tab_link . '" class="' . esc_attr($class) . '">' . esc_html(
                        $data['title']
                    ) . '</a>' . "\n";

                ++$c;
            }

            $html .= '</h2>' . "\n";
        }

        /** @noinspection HtmlUnknownTarget */
        $html .= '<form method="post" action="options.php" enctype="multipart/form-data">' . "\n";

        // Get settings fields.
        ob_start();
        settings_fields($this->parent::TOKEN . '_Settings');
        do_settings_sections($this->parent::TOKEN . '_Settings');
        $html .= ob_get_clean();

        $html .= '<p class="submit">' . "\n";
        $html .= '<input type="hidden" name="tab" value="' . esc_attr($tab) . '" />' . "\n";
        $html .= '<input name="Submit" type="submit" class="button-primary" value="' . esc_attr(
                __('Save Settings', DigSig::TEXT_DOMAIN)
            ) . '" />' . "\n";
        $html .= '</p>' . "\n";
        $html .= '</form>' . "\n";
        $html .= '</div>' . "\n";

        echo $html;
    }

    /**
     * Validator for Secret settings, like API keys.  If a new value is not provided, the old value is kept intact.
     *
     * @param string $new
     * @param string $field
     *
     * @return string
     */
    protected function validation_secret(string $new, string $field): string
    {
        if ($new === '') { // If there is no value, submit the already-saved one.
            return $this->$field;
        }

        return $new;
    }

    /**
     * Slug validator.  Also, (more importantly) tells WP that it needs to flush rewrite rules.
     *
     * @param mixed  $new The new value.
     * @param string $field The name of the setting.  Used to determine if the setting is actually changed.
     *
     * @return string
     */
    protected function validation_slug($new, string $field): string
    {
        if ($new != $this->$field) { // only validate the field if it's changing.
            $new = $this->validation_lowercase($new);
            $new = preg_replace("[^a-z/]", '', $new);

            // since any slug change is probably going to need this...
            $this->parent->queueFlushRewriteRules();
        }
        return $new;
    }

    /**
     * Force a value to lowercase; used as a validator
     *
     * @param string $data  Mixed case string
     *
     * @return string lower-case string
     */
    public function validation_lowercase(string $data): string
    {
        return strtolower($data);
    }

    /**
     * If a setting is changed that impacts the scripts, update the scripts.
     *
     * @param mixed $new the new value, which could be anything
     * @param string $field The name of the field that's getting updated
     *
     * @return mixed lower-case string
     */
    protected function validation_updateScriptsIfChanged($new, string $field)
    {
        if ($new !== $this->$field) {
            DigSig::queueUpdateDeployedScripts();
        }
        return $new;
    }

    /**
     * Cloning is forbidden.
     *
     * @since 1.0.0
     */
    public function __clone()
    {
        _doing_it_wrong(
            __FUNCTION__,
            esc_html(__('Cloning DigSig Settings is forbidden.')),
            esc_attr($this->parent::VERSION)
        );
    }

    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 1.0.0
     */
    public function __wakeup()
    {
        _doing_it_wrong(
            __FUNCTION__,
            esc_html(__('Unserializing instances of DigSig Settings is forbidden.')),
            esc_attr($this->parent::VERSION)
        );
    }

}