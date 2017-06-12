<?php

/**
 * Images import
 */
class WooVKI_Images {

  public $manual = false;

  function __construct() {
    add_action('woovki_update_product', [$this, 'update_featured_image'], 22, 2);

    add_action('woovki_update_product', [$this, 'update_gallery_images'], 22, 2);

    add_action('woovki_cron_download_image_featured', [$this, 'download_image_featured']);

    add_action('woovki_cron_download_gallery_images', [$this, 'download_gallery_images']);

    add_action('woovki_ui_action', [$this, 'display_ui']);

    add_action('woovki_start_download_images', [$this, 'download_images_manual']);

  }


  function download_image_featured(){


    // var_dump(1);

    $list = get_posts('post_type=product&meta_key=woovki_plan_image_featured');

    foreach ($list as $post) {

      $url = get_post_meta($post->ID, 'woovki_plan_image_featured', true);


      $img_id = WooVKI_Images::download_image_by_url($url, $post->ID);

      if(empty($img_id)){
        error_log('WooVKI - thumbnail image not load');
      } else {
        $cc = set_post_thumbnail( $post->ID, $img_id );
        delete_post_meta($post->ID, 'woovki_plan_image_featured');
      }

    }
  }

  function download_gallery_images(){
    $list = get_posts('post_type=product&meta_key=woovki_gallery_list');

    foreach ($list as $key => $post_data) {

      $gallery_source_data = get_post_meta($post_data->ID, 'woovki_gallery_list', true);

      if(empty($gallery_source_data)){
        continue;
      }

      $product = wc_get_product($post_data->ID);
      $gallery_current = $product->get_gallery_image_ids();

      $gallery_new = [];
      foreach ($gallery_source_data as $key => $value_url) {
        $img_id = $this->download_image_by_url($value_url, $post_data->ID);

        update_post_meta($img_id, 'woovki_update_timestamp', date("Y-m-d H:i:s"));

        $gallery_new[] = $img_id;
      }

      $product->set_gallery_image_ids($gallery_new);
      $check = $product->save();

      delete_post_meta($post_data->ID, 'woovki_gallery_list');

    }




  }





  function update_featured_image($product, $data){

    update_post_meta($product->get_id(), 'woovki_plan_image_featured', $data->thumb_photo);

  }

  function update_gallery_images($product, $data){

    if( ! empty($data->photos) ){

      $gallery_list = [];
      foreach ($data->photos as $value) {

        $data = [
          'id' => $value->id,
          'url' => $value->photo_1280,
        ];

        $gallery_list[$data['id']] = $data['url'];


      }

      update_post_meta($product->get_id(), 'woovki_gallery_list', $gallery_list);

    }

    // update_post_meta($product->get_id(), 'woovki_plan_image_featured', $data->thumb_photo);

  }

  function download_images_manual(){
    $this->manual = true;
    $this->download_gallery_images();
    $this->download_image_featured();
  }

  function display_ui(){
    $url = add_query_arg('act', 'download_images', $_SERVER['REQUEST_URI']);

    printf('<a href="%s" class="button">Загрузка картинок вручную</a>', $url);

  }

  function download_image_by_url($url, $post_id){

    //Check exist image
    $check = get_posts('post_type=attachment&meta_key=woovki_url_source&meta_value='.$url);

    if( ! empty($check) ){
      return $check[0]->ID;
    }

    require_once( ABSPATH . 'wp-admin/includes/image.php' );
    require_once( ABSPATH . 'wp-admin/includes/file.php' );
    require_once( ABSPATH . 'wp-admin/includes/media.php' );


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


}

new WooVKI_Images;
