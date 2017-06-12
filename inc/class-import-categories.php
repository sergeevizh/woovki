<?php

/**
 * Import categories from vk.com
 */
class WooVKI_Import_Categories {

  function __construct() {
    add_action('woovki_update_product', [$this, 'import_categories'], 22, 2);

  }

  function import_categories($product, $data){

    if(empty($data->albums_ids)){
      return;
    }

    $this->update_categories();

    $terms = get_terms([
      'taxonomy' => 'product_cat',
      'hide_empty' => false,
      'meta_key' => 'woovki_id',
      'meta_value' => $data->albums_ids,
      'meta_compare' => 'IN'

    ]);

    // var_dump($data->albums_ids);

    $terms_list = [];
    foreach ($terms as $value) {
      $terms_list[] = $value->term_id;
    }
    wp_set_post_terms( $product->get_id(), $terms_list, $taxonomy = 'product_cat' );

  }

  function update_categories(){

    if( ! empty(get_transient('woovki_albums'))){
      return true;
    }

    $url = 'https://api.vk.com/method/market.getAlbums';

    $url = add_query_arg('owner_id', get_option('woovki_owner_id'), $url);
    $url = add_query_arg('access_token', get_option('woovki_access_token'), $url);
    $url = add_query_arg('v', $this->ver, $url);

    $request = wp_remote_get($url);
    $response = wp_remote_retrieve_body( $request );
    $response = json_decode( $response );

    if(empty($response->response)){
      return false;
    }

    // var_dump($response->response);

    set_transient('woovki_albums', $response->response, 60);

    foreach ($response->response as $value) {

      $data = [
        'id' => $value->id,
        'title' => $value->title,
      ];

      $term_id = $this->update_category($data, $response->response);

    }

  }

  // Update category or add from $data
  // Return false or $term_id
  function update_category($data, $d){

    // var_dump($data);

    $check = get_terms([
      'taxonomy' => 'product_cat',
      'hide_empty' => false,
      'meta_query' => [
        'key' => 'woovki_id',
        'value' => $data['id'],
      ],
    ]);

    if( ! empty($check)){
      return $check[0]->term_id;
    }

    $term_name = $data['title'];
    $term = wp_insert_term( $term_name, $taxonomy = 'product_cat');
    if(is_wp_error($term)){
      // var_dump($term);
      return false;
    }

    $term_id = $term['term_id'];

    update_term_meta( $term_id, $meta_key = 'woovki_id', $data['id'] );

    return $term_id;

  }
}

new WooVKI_Import_Categories;
