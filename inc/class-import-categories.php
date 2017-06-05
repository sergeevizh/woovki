<?php

/**
 * Import categories from vk.com
 */
class WooVKI_Import_Categories {

  function __construct() {
    add_action('woovki_update_product', [$this, 'update_category'], 22, 2);

  }

  function update_category($product, $data){

    if(empty($data->albums_ids)){
      return;
    }

    foreach ($data->albums_ids as $value) {

      var_dump($value);
      # code...
    }

    echo '<pre>';
    // var_dump($data);
    echo '</pre>';

  }
}

new WooVKI_Import_Categories;
