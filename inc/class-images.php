<?php

/**
 * Images import
 */
class WooVKI_Images {

  public $manual = false;

  function __construct() {
    add_action('woovki_update_product', [$this, 'update_featured_image'], 22, 2);

    add_action('woovki_update_product', [$this, 'update_gallery_images'], 22, 2);

    add_action('woovki_cron_download_image_featured', ['WooVKI_Images', 'download_image_featured']);

    add_action('woovki_ui_action', [$this, 'display_ui']);

    add_action('woovki_start_download_images', [$this, 'download_images_manual']);

  }

  function download_images_manual(){
    $this->manual = true;
    $this->download_image_featured();
  }

  function display_ui(){
    $url = add_query_arg('act', 'download_images', $_SERVER['REQUEST_URI']);

    printf('<a href="%s" class="button">Загрузка картинок вручную</a>', $url);

  }

  function download_image_featured(){


    // var_dump(1);

    $list = get_posts('post_type=product&meta_key=woovki_plan_image_featured');

    foreach ($list as $post) {

      $url = get_post_meta($post->ID, 'woovki_plan_image_featured', true);


      $img_id = WooVKI_Images::download_image_by_url($url, $post->ID);

      if($this->manual){
        var_dump($img_id);
      }


      if(empty($img_id)){
        error_log('WooVKI - thumbnail image not load');
      } else {
        $cc = set_post_thumbnail( $post->ID, $img_id );
        delete_post_meta($post->ID, 'woovki_plan_image_featured');
      }

    }
  }

  function download_image_by_url($url, $post_id){

    //Check exist image
    $check = get_posts('post_type=attachment&meta_key=woovki_url_source&meta_value='.$url);

    if( ! empty($check) ){
      return $check[0]->ID;
    }

    // wp_mail('yumashev@fleep.io', 'test', 23);


    require_once( ABSPATH . 'wp-admin/includes/file.php' );

    $tmp = download_url( $url, $timeout = 900);

    preg_match('/[^\?]+\.(jpg|jpe|jpeg|gif|png)/i', $url, $matches );
    $file_array['name'] = basename( $matches[0] );
    $file_array['tmp_name'] = $tmp;

    // загружаем файл
    $id = media_handle_sideload( $file_array, $post_id );

    // если ошибка
    if( is_wp_error( $id ) ) {
    	@unlink($file_array['tmp_name']);
    	return false;
    }

    // удалим временный файл
    @unlink( $file_array['tmp_name'] );

    update_post_meta($id, 'woovki_url_source', $url);

    return $id;

  }


  function update_featured_image($product, $data){

    update_post_meta($product->get_id(), 'woovki_plan_image_featured', $data->thumb_photo);
    // echo '<pre>';
    // var_dump($product->id);
    // var_dump($data->thumb_photo);
    // var_dump($data);
    // // var_dump($product);
    // echo '</pre>';

  }

  function update_gallery_images($product, $data){

  }

}

new WooVKI_Images;
// $GLOBALS['woovki_images'] = new WooVKI_Images;
