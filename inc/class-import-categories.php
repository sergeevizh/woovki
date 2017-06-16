<?php

/**
 * Import categories from vk.com
 */
class WooVKI_Import_Categories {

  function __construct() {
    add_action('woovki_update_product', [$this, 'import_categories'], 22, 2);

    add_action('admin_init', [$this, 'add_settings_ui']);



  }

  function add_settings_ui(){
    add_settings_section(
			$name = 'woovki_settings_section_categories',
			$title = 'Импорт категорий',
			$callback = array($this, 'display_woovki_settings_section_categories'),
			$page = 'woovki'
		);


    register_setting(
			$option_group = 'woovki',
			$option_name = 'woovki_import_categories_type'
		);

		add_settings_field(
			$id = 'woovki_import_categories_type',
			$title = 'Тип импорта товаров без подборок',
			$callback = [$this, 'display_woovki_import_categories_type'],
			$page = 'woovki',
			$section = 'woovki_settings_section_categories'
		);


    register_setting(
			$option_group = 'woovki',
			$option_name = 'woovki_import_categories_selected'
		);

		add_settings_field(
			$id = 'woovki_import_categories_selected',
			$title = 'Категория в которую загружать товары без подборок',
			$callback = [$this, 'display_woovki_import_categories_selected'],
			$page = 'woovki',
			$section = 'woovki_settings_section_categories'
		);


  }

  function display_woovki_settings_section_categories(){
    ?>
    <p>Особенности импорта категорий из ВКонтакте</p>
    <hr>
    <?php
  }

  function display_woovki_import_categories_type(){
    $name = 'woovki_import_categories_type';
    printf('<p><input type="radio" id="%s0" name="%s" value="0" size="55" %s /><label for="%s0">Без выбора категорий</label></p>', $name, $name, checked( 0, get_option($name), false ), $name );
    printf('<p><input type="radio" id="%s1" name="%s" value="1" size="55" %s /><label for="%s1">Не загружать товары без категорий</label></p>', $name, $name, checked( 1, get_option($name), false ), $name );
    printf('<p><input type="radio" id="%s2" name="%s" value="2" size="55" %s /><label for="%s2">Загружать в специальную категорию</label></p>', $name, $name, checked( 2, get_option($name), false ), $name );

  }

  function display_woovki_import_categories_selected(){
    $name = 'woovki_import_categories_selected';

    $args = [
      'taxonomy' => 'product_cat',
      'hide_empty' => false,
      'name' => $name,
      'selected' => get_option($name),
      'show_option_none' => "Выберите категорию",
    ];
    wp_dropdown_categories( $args );

    printf('<p>Создать категорию можно <a href="%s">тут</a></p>', admin_url('edit-tags.php?taxonomy=product_cat&post_type=product') );

  }

  function import_categories($product, $data){


    if(empty($data->albums_ids)){


      $type_import = get_option('woovki_import_categories_type');

      if( empty($type_import) ){
        return;
      }


      if($type_import == 2){
        $term_id_other = (int)get_option('woovki_import_categories_selected');
        if( ! empty($term_id_other)){
          $terms_list = [$term_id_other];
        }
      }

    } else {


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



    }


    if( ! empty($terms_list) ){
      wp_set_post_terms( $product->get_id(), $terms_list, $taxonomy = 'product_cat' );

    } else {
      wp_set_post_terms( $product->get_id(), '', $taxonomy = 'product_cat' );
    }

  }

  function update_categories(){


    if( ! empty(get_transient('woovki_albums_cache'))){
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

    set_transient('woovki_albums_cache', 1, 60);



    foreach ($response->response as $value) {

      if(empty($value->id)){
        continue;
      }

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

    $check = get_terms([
      'taxonomy' => 'product_cat',
      'hide_empty' => false,
      'meta_key' => 'woovki_id',
      'meta_value' => $data['id'],
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
