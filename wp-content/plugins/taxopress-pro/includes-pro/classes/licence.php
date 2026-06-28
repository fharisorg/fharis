<?php

use PublishPress\EDD_License\Core\Container as EDDContainer;
use PublishPress\EDD_License\Core\Services as EDDServices;
use PublishPress\EDD_License\Core\ServicesConfig as EDDServicesConfig;

class TaxoPress_License
{

    const MENU_SLUG = 'st_options';

    // class instance
    static $instance;

    /**
     * @var Container
     */
    private $edd_container;

    /**
     * Constructor
     *
     * @return void
     * @author Olatechpro
     */
    public function __construct()
    {
        // Admin licence options
        add_filter('taxopress_admin_options', [$this, 'taxopress_options']);
        // Update licence 
        add_action('admin_init', [$this, 'process_licence_save']);

        $this->init_edd_connector();
    }

    /** Singleton instance */
    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function init_edd_connector()
    {
        $config = new EDDServicesConfig();
        $config->setApiUrl(TAXOPRESS_EDD_STORE_URL);
        $config->setLicenseKey($this->get_license_key());
        $config->setLicenseStatus($this->get_license_status());
        $config->setPluginVersion(TAXOPRESS_PRO_VERSION);
        $config->setEddItemId(TAXOPRESS_PRO_EDD_ITEM_ID);
        $config->setPluginAuthor(TAXOPRESS_PLUGIN_AUTHOR);
        $config->setPluginFile(TAXOPRESS_PLUGIN_FILE);

        $this->edd_container = new EDDContainer();
        $this->edd_container->register(new EDDServices($config));

        // Instantiate the update manager
        $this->edd_container['update_manager'];
    }
    
    public function taxopress_options($options) {

        $license = $this->get_license_key();
        $status  = $this->get_license_status();

        $licence_fields = [];
        $licence_fields[] = array(
            'taxopress_licence_key_input',
            esc_html__('License key', 'taxopress-pro'),
            'licence_field',
            $license,
            '<div class="taxopress_licence_key_status '. esc_attr($status) .'"><span class="taxopress_licence_key_label">'. esc_html__('Status', 'taxopress-pro').': </span>'. ucwords($status) .'</div>
            <p class="taxopress_settings_field_description">'. esc_html__('Your license key provides access to updates and support.', 'taxopress-pro') .'</p>',
            ''
        );

        if (false !== $license && !empty($license)) { 
            $additional_texts = '';

            if ($status !== false && $status == 'active') {
                $additional_texts .= '<input type="submit" class="button-secondary" name="edd_license_deactivate" value="'. esc_attr__('Deactivate License', 'simpletags') .'"/>';
            } else {
                $additional_texts .= '<input type="submit" class="button-secondary" name="edd_license_activate" value="'. esc_attr__('Activate License', 'simpletags') .'"/>';
            }

            $licence_fields[] = array(
                'taxopress_licence_key_input',
                esc_html__('Activate License', 'taxopress-pro'),
                'licence_activate',
                $license,
                $additional_texts,
                ''
            );
        }

        $before = array_slice($options, 0, array_search('metabox', array_keys($options)) + 1);
        $after = array_slice($options, array_search('metabox', array_keys($options)) + 1);

        $newElement = array('licence' => $licence_fields);

        $options = $before + $newElement + $after;

        return $options;
    }

    private function get_license_key()
    {
        return get_option('taxopress_license_key');
    }

    private function get_license_status()
    {
        $status = get_option('taxopress_license_status');

        return ($status !== false && $status == 'valid') ? 'active' : 'inactive';
    }

    public function process_licence_save()
    {
        if ((!isset($_POST['edd_license_activate']) && !isset($_POST['edd_license_deactivate']) && !isset($_POST['updateoptions'])) || !current_user_can('admin_simple_tags')) {
            return;
        }

        if (empty($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'updateresetoptions-simpletags')) {
            return;
        }

        $license = $this->get_license_key();
        $licence_key_save = isset($_POST['taxopress_licence_key_input']) ? sanitize_text_field($_POST['taxopress_licence_key_input']) : '';

        if (isset($_POST['edd_license_activate'])) {
            update_option('taxopress_license_key', $licence_key_save);
            //activate
            $status = $this->activate_licence_key($licence_key_save);
            update_option('taxopress_license_status', $status);
        } elseif (isset($_POST['edd_license_deactivate'])) {
            update_option('taxopress_license_key', $licence_key_save);
            //activate
            $status = $this->deactivate_licence_key($licence_key_save);
            update_option('taxopress_license_status', $status);
        }

        if ($license !== $licence_key_save) {
            update_option('taxopress_license_key', $licence_key_save);
            //activate
            $status = $this->activate_licence_key($licence_key_save);
            update_option('taxopress_license_status', $status);
        }
    }

    public function activate_licence_key($licence_key)
    {
        $licence_key = trim($licence_key);

        if (!empty($licence_key)) {
            $license_manager = $this->edd_container['license_manager'];

            return $license_manager->validate_license_key($licence_key, TAXOPRESS_PRO_EDD_ITEM_ID);
        }
    }

    public function deactivate_licence_key($licence_key)
    {
        $licence_key = trim($licence_key);

        if (!empty($licence_key)) {
            $license_manager = $this->edd_container['license_manager'];

            return $license_manager->deactivate_license_key($licence_key, TAXOPRESS_PRO_EDD_ITEM_ID);
        }
    }
}