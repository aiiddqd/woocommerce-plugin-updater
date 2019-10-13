<?php 
namespace uptimizt;

/**
 * example: /wp-json/dload/v1/get-urls
 */
final class FileAccessForUser
{
    public static function init(){
        add_action( 'rest_api_init', function () {
            register_rest_route( 'dload/v1', '/get-urls', array(
                'methods' => 'GET',
                'callback' => [__CLASS__, 'rest_get_urls'],
            ));
        });
    }

    public static function rest_get_urls(\WP_REST_Request $request){

        $data = [];
        if(!$data['user_id'] = get_current_user_id()){
            return new \WP_Error( 'no_data', 'no data', array('status' => 404) );
        }

        $data['customer_available_downloads'] = wc_get_customer_available_downloads($data['user_id']);
         
        if (empty($data['customer_available_downloads'])) {
            return new \WP_Error( 'no_data', 'no data', array('status' => 404) );
        }

        $response = new \WP_REST_Response($data);
        $response->set_status(200);

        return $response;
    }
}
FileAccessForUser::init();
