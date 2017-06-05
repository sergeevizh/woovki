<?php
/*
Plugin Name: WooVKI
Version: 0.3
Plugin URI: ${TM_PLUGIN_BASE}
Description: VKontakte (ВКонтакте) - импорт товаров на сайт WooCommerce
Author: ${TM_NAME}
Author URI: ${TM_HOMEPAGE}
*/

if( ! wp_next_scheduled( 'woovki_cron_download_image_featured' ) ) {
  wp_schedule_event( time(), 'wp_wc_updater_cron_interval', 'woovki_cron_download_image_featured' );
}

require_once 'inc/class-settings.php';
require_once 'inc/class-images.php';
require_once 'inc/class-import-categories.php';

class WooVKI {

  private $url;
  private $ver = '5.64';

  function __construct() {
    add_action('admin_menu', function(){
        add_management_page(
            $page_title = 'WooVKI',
            $menu_title = 'WooVKI',
            $capability = 'manage_options',
            $menu_slug = 'woovki-ui',
            $function = array($this, 'user_interface')
        );
    });

    add_action('woovki_start_auth', array($this, 'auth') );
    add_action('woovki_start_import', array($this, 'import') );

    add_action('woovki_action', [$this, 'check_code']);


  }

  function apivk($method, $args = []){

    $url = 'https://api.vk.com/method/'.$method;

    $url = add_query_arg($args, $url);
    $url = add_query_arg('access_token', get_option('woovki_access_token'), $url);
    $url = add_query_arg('v', $this->ver, $url);

    $request = wp_remote_get($url);
    $response = wp_remote_retrieve_body( $request );
    $response = json_decode( $response );

    if(isset($response->error)){
      printf('<p>Код ошибки: %s, детали: %s</p>', $response->error->error_code, $response->error->error_msg);
      return false;
    } else {
      return $response;
    }
  }


  function check_code(){
    if( ! empty($_GET['code'])){
      $code = sanitize_text_field($_GET['code']);

      update_option('woovki_vk_api_code', $code, false);

      $vk_api_data = $this->get_data_vk_api();


      $url = 'https://oauth.vk.com/access_token';
      $url = add_query_arg([
        'client_id' => $vk_api_data['app_id'],
        'client_secret' => $vk_api_data['key_secret'],
        'redirect_uri' => $vk_api_data['url_callback'],
        'code' => $code,
      ], $url);

      // var_dump($url); exit;

      $request = wp_remote_get($url);
      $response = wp_remote_retrieve_body( $request );
      $response = json_decode( $response );


      if( ! empty($response->access_token)){
        update_option('woovki_access_token', $response->access_token);
      } else {
        wp_send_json_error('No access_token');
      }

      var_dump($response->access_token);

      $this->url = $_SERVER['REQUEST_URI'];

      wp_redirect(remove_query_arg('code',$this->url));
      exit;
    }
  }

  //Старт импорта продуктов
  function import(){

    $url = 'https://api.vk.com/method/market.get';

    $url = add_query_arg('owner_id', get_option('woovki_owner_id'), $url);
    $url = add_query_arg('access_token', get_option('woovki_access_token'), $url);
    $url = add_query_arg('extended', 1, $url);
    $url = add_query_arg('v', $this->ver, $url);

    $request = wp_remote_get($url);
    $response = wp_remote_retrieve_body( $request );
    $response = json_decode( $response );

    if(isset($response->error)){
      printf('<p>Код ошибки: %s, детали: %s</p>', $response->error->error_code, $response->error->error_msg);
    }

    // echo '<pre>';
    // var_dump($response);
    // echo '</pre>';



    foreach ($response->response->items as $key => $value) {
      $this->update_product($value);
    }

  }


  /**
  * Add or update product
  */
  function update_product($data){


    printf('<h2>%s (%s)</h2>', $data->title, $data->id);

    if( ! $product_id = $this->product_exist_by_item_id($data->id)){
      $product_id = $this->add_product($data);
    }

    if(empty($product_id)){
      wp_send_json_error('No product ID for update');
    }

    printf('<p>product id: %s</p>',$product_id);

    $data_product = array(
      'ID'            => $product_id,
      'post_content'  => $data->description,
      'post_status'   => "publish",
      'post_title'    => ucfirst($data->title),
      'post_type'     => "product",
    );

    wp_update_post( $data_product );

    update_post_meta($product_id, 'woovki_import_timestamp', date("Y-m-d H:i:s"));
    update_post_meta($product_id, 'woovki_import_data_cache', serialize($data));

    $product = wc_get_product($product_id);

    $product->set_regular_price($data->price->amount/100);
    $product->save();

    printf('<p><a href="%s">edit post</a></p>', get_edit_post_link( $product_id, '' ));

    // WooVKI_Images::download_image_featured();

    echo '<pre>';
    // var_dump($data);
    echo '</pre>';
    // exit;

    do_action('woovki_update_product', $product, $data);

    // return;
  }

  //Проверяем есть ли продукт который уже загружен по ИД элемента ВК
  function product_exist_by_item_id($vk_item_id){

    $check_posts = get_posts('post_type=product&meta_key=_woovki_item_id&meta_value='.$vk_item_id);
    if( ! empty($check_posts)){
      return $check_posts[0]->ID;
    } else {
      return false;
    }
  }

  //Добавляем продукт на основе данных из ВК
  function add_product($data){

    $post = array(
      'post_author' => get_current_user_id(),
      'post_content' => $data->description,
      'post_status' => "publish",
      'post_title' => ucfirst($data->title),
      'post_type' => "product",
    );

    $post_id = wp_insert_post( $post );

    wp_set_object_terms($post_id, 'simple', 'product_type');
    update_post_meta( $post_id, '_visibility', 'visible' );
    update_post_meta( $post_id, '_stock_status', 'instock');


    if($post_id){
        add_post_meta($post_id, '_woovki_item_id', $data->id);
        echo '<p>Продукт добавлен</p>';
        return $post_id;
    } else {
      return false;
    }
  }

  //Display user interface
  function user_interface(){
    $this->url = $_SERVER['REQUEST_URI'];


    $url_import = '';
    if(empty($_GET['act'])){
      $url_import = add_query_arg('act', 'import', $this->url);
      $url_auth = add_query_arg('act', 'auth', $this->url);
    }

    do_action('woovki_action');

    ?>
    <div class="woovki-ui-wrapper">
      <header>
        <h1>WooVK</h1>
        <p>Импорт и экспорт товаров ВКонтакте</p>
      </header>

      <?php
        if( ! empty(get_option('woovki_access_token')) ) {
          printf('<p>Авторизя пройдена: %s</p>', get_option('woovki_access_token'));
        }
      ?>
      <?php if( empty($_GET['act']) ): ?>
        <section class='woovki-ui-import'>
          <h2>Импорт товаров</h2>
          <p>Импорт до 100 товаров в ручном режиме. Для тестирования и ручного запуска малых объемов.</p>
          <?php printf('<a href="%s" class="button">Авторизация</a>', $url_auth); ?>
          <?php printf('<a href="%s" class="button">Импорт</a>', $url_import); ?>
          <?php do_action('woovki_ui_action') ?>
        </section>
      <?php else: ?>
        <hr>
        <div class="woovki-result">
          <?php
          if( ! empty($_GET['act'])){
            do_action('woovki_start_' . sanitize_text_field($_GET['act']));
          }
          ?>
        </div>
      <?php endif; ?>
    </div>

    <?php

  }


  function auth(){

    $vk_api_data = $this->get_data_vk_api();

    $url = sprintf(
      'https://oauth.vk.com/authorize?client_id=%s&display=page&redirect_uri=%s&scope=market&response_type=code&v=5.64',
      $vk_api_data['app_id'],
      $vk_api_data['url_callback']
    );

    wp_redirect($url);
    exit;
  }


  //Get settings
  function get_data_vk_api(){
    return array(
      'app_id' => get_option('woovki_app_id'),
      'key_secret' => get_option('woovki_key_secret'),
      'key_access' => get_option('woovki_key_access'),
      'url_callback' => admin_url('tools.php?page=woovki-ui'),
    );
  }

}

$GLOBALS['woovki'] = new WooVKI;
