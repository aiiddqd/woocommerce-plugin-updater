<?php
namespace WooMS;

/**
 * based on https://rudrastyh.com/wordpress/self-hosted-plugin-update.html
 */
final class PluginUpdater
{
    public static $data = [
        'name' => 'WooMS XT',
        'slug' => 'wooms-extra',
        'plugin_path' => 'wooms-extra/wooms-extra.php',
        'product_url' => 'https://wpcraft.ru/product/wooms-xt/',
        'version' => '',
        'tested' => '3.5',
        'requires' => '3.3',
        'author' => 'wpcraft.ru',
        'author_profile' => 'https://wpcraft.ru',
        'download_link' => '',
        'trunk' => '',
        'last_updated' => '2017-08-17 02:10:00',
        'sections' => [
            'description' => 'description',
            'installation' => 'installation',
            'changelog' => 'changelog',
            'screenshots' => 'screenshots',
        ],
    ];

    public static function init(){
        add_action('init', function(){
            if(!isset($_GET['ddd'])){
                return;
            }

            echo '<pre>';
            self::request('ddd');

            exit;
        });


        add_filter('plugins_api', [__CLASS__, 'plugin_info'], 20, 3);
        add_filter('site_transient_update_plugins', [__CLASS__, 'push_update'] );

        add_filter('wooms_update_plugin_data', [__CLASS__, 'check_remote_version']);

        add_action( 'admin_init', array( __CLASS__, 'add_settings' ), 50 );

    }


    public static function check_remote_version($data)
    {
        if( ! $data_remote = get_transient( self::$data['slug'] . '_data_remote' )){
            $data_remote = self::get_urls_for_product( self::$data['product_url'] );
            set_transient(self::$data['slug'] . '_data_remote', $data_remote, DAY_IN_SECONDS);
        }

        if(empty($data_remote)){
            return $data;
        }

        $latest_version = '0';
        $latest_url = '';
        foreach ($data_remote as $verson => $url){
            if(version_compare($latest_version, $verson, '<')){
                $latest_version = $verson;
                $latest_url = $url;
            }

        }

        $data['version'] = $latest_version;
        $data['download_link'] = $latest_url;
        $data['trunk'] = $latest_url;
        return $data;
    }

    public static function push_update( $transient ){

        if ( empty($transient->checked ) ) {
            return $transient;
        }

        $data = apply_filters('wooms_update_plugin_data', self::$data);

        if(empty($data['version'])){
            return $transient;
        }

        if( version_compare(get_bloginfo('version'), $data['requires'], '<')  ){
            return $transient;
        }

        $current_plugin_data = get_plugin_data( WP_PLUGIN_DIR . "/wooms-extra/wooms-extra.php", false, false );
        if(empty($current_plugin_data['Version'])){
            return $transient;
        }

        if( version_compare($current_plugin_data['Version'], $data['version'], '>=')  ){
            return $transient;
        }

        $res = new \stdClass();
        $res->slug = $data['slug'];
        $res->plugin = $data['plugin_path'];
        $res->new_version = $data['version'];
        $res->tested = $data['tested'];
        $res->package = $data['download_link'];
        $transient->response[$res->plugin] = $res;

        return $transient;
    }

    /*
     * $res contains information for plugins with custom update server
     * $action 'plugin_information'
     * $args stdClass Object ( [slug] => woocommerce [is_ssl] => [fields] => Array ( [banners] => 1 [reviews] => 1 [downloaded] => [active_installs] => 1 ) [per_page] => 24 [locale] => en_US )
     */
    public static function plugin_info( $res, $action, $args )
    {
        // do nothing if this is not about getting plugin information
        if( $action !== 'plugin_information' )
            return false;

        // do nothing if it is not our plugin
        if( self::$data['slug'] !== $args->slug )
            return $res;

        $res = new \stdClass();
        $res->name = self::$data['name'];
        $res->slug = self::$data['slug'];
        $res->version = self::$data['version'];
        $res->tested = self::$data['tested'];
        $res->requires = self::$data['requires'];
        $res->author = self::$data['author'];
        $res->author_profile = self::$data['author_profile'];
        $res->download_link = self::$data['download_url'];
        $res->trunk = self::$data['download_url'];
        $res->last_updated = self::$data['last_updated'];
        $res->sections = array(
            'description' => self::$data['sections']['description'], // description tab
            'installation' => self::$data['sections']['installation'], // installation tab
            'changelog' => self::$data['sections']['changelog'], // changelog tab
            // you can add your custom sections (tabs) here
        );

        $res->banners = array(
            'low' => 'https://wpcraft.ru/wp-content/uploads/2019/09/sign-1209759_640.jpg',
            'high' => 'https://wpcraft.ru/wp-content/uploads/2019/09/sign-1209759_640.jpg'
        );
        return $res;

    }

    public static function get_urls_for_product($product_uri = ''){
        if(empty($product_uri)){
            return false;
        }

        if(!$data = get_transient('wooupdater_data')){
            $url = 'https://wpcraft.ru/wp-json/dload/v1/get-urls';
            $data = self::request( $url );
            set_transient('wooupdater_data', $data, 55);
        }

        if(empty($data['customer_available_downloads'])){
            return false;
        }

        $result_data = [];
        foreach ($data['customer_available_downloads'] as $item){
            if($item['product_url'] == $product_uri){
                $result_data[$item['download_name']] = $item['download_url'];
            }
        }

        if(empty($result_data)){
            return false;
        }

        return $result_data;
    }

    /**
     * api request wrapper
     */
    public static function request($url = '', $args = []){

        if(!$pass = @get_option('wooms_plugin_updater')['pass']){
            return false;
        }

        if(!$login = @get_option('wooms_plugin_updater')['login']){
            return false;
        }

        $args['headers']['Authorization'] = 'Basic ' . base64_encode( $login . ':' . $pass );

        $data = wp_remote_retrieve_body( wp_remote_request( $url, $args ) );
        $data = json_decode( $data, true );

        return $data;
    }


    public static function add_settings(){
        $section_key = 'woomss_section_updater';
        add_settings_section( $section_key, 'Updater', null, 'mss-settings' );

        $option_name = 'wooms_plugin_updater';
        register_setting( 'mss-settings', $option_name );
        add_settings_field(
            $id = $option_name . '_login',
            $title = 'Login updater',
            $callback = function($args){
                printf(
                    '<input type="text" name="%s" value="%s" size="33">',
                    $args['name'], $args['value']
                );
            },
            $page = 'mss-settings',
            $section = $section_key,
            $args = [
                'name' => $option_name . '[login]',
                'value' => @get_option($option_name)['login'],
            ]
        );

        add_settings_field(
            $id = $option_name . '_pass',
            $title = 'Pass updater',
            $callback = function($args){
                printf(
                    '<input type="password" name="%s" value="%s" size="33">',
                    $args['name'], $args['value']
                );
            },
            $page = 'mss-settings',
            $section = $section_key,
            $args = [
                'name' => $option_name . '[pass]',
                'value' => @get_option($option_name)['pass'],
            ]
        );

    }


}

PluginUpdater::init();
