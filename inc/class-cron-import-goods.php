<?php

/**
 * Auto import goods by WP Cron
 */


class WooVKI_Goods_Import_Cron {

  public $manual;

  function __construct() {

    add_action( 'admin_init', array($this, 'settings_init') );

    add_action('init', [$this, 'check_enabled']);

    add_action('woovki_cron_import_goods', [$this, 'start_import']);

    add_action('woovki_start_import_manual', [$this, 'import_manual_start']);

    add_action('woovki_ui_action', [$this, 'display_ui']);
  }


  function import_manual_start(){
    $this->manual = true;
    $this->start_import();
  }

  function display_ui(){
    $url = add_query_arg('act', 'import_manual', $_SERVER['REQUEST_URI']);

    printf('<br/><a href="%s" class="button">Старт импорта товаров вручную</a>', $url);

  }



  function start_import(){

    $args = [
      'owner_id' => get_option('woovki_owner_id'),
      'extended' => 1,
    ];

    $data = $GLOBALS['woovki']->vkapi($method='market.get', $args);

    if(empty($data->response->items)){
      return false;
    }

    if( ! is_array($data->response->items)){
      return false;
    }

    foreach ($data->response->items as $vk_product) {
      do_action('wooovki_import_item', $vk_product);
    }

  }

  //Setup cron by option
  function check_enabled(){

    if(empty(get_option('woovki_import_goods_cron_enabled'))){
      wp_clear_scheduled_hook( 'woovki_cron_import_goods' );
    } else {
      if( ! wp_next_scheduled( 'woovki_cron_import_goods' ) ) {
       wp_schedule_event( time(), 'hourly', 'woovki_cron_import_goods' );
      }
    }
    
  }

  function settings_init(){
    add_settings_section(
      $name = 'woovki_settings_section_autoimport',
      $title = 'Автоматический импорт товаров',
      $callback = array($this, 'display_woovki_settings_section_autoimport'),
      $page = 'woovki'
    );


    register_setting(
      $option_group = 'woovki',
      $option_name = 'woovki_import_goods_cron_enabled'
    );

    add_settings_field(
      $id = 'woovki_import_goods_cron_enabled',
      $title = 'Включить автоимпорт на каждый час',
      $callback = [$this, 'display_woovki_import_goods_cron_enabled'],
      $page = 'woovki',
      $section = 'woovki_settings_section_autoimport'
    );

  }

  function display_woovki_settings_section_autoimport(){
    echo '<p>Тут можно включить автоматический импорт который будет происходить каждый час</p>';
  }

  function display_woovki_import_goods_cron_enabled(){
    $name = 'woovki_import_goods_cron_enabled';
    printf('<input type="checkbox" id="%s" name="%s" value="1" %s />', $name, $name, checked('1', get_option($name), false) );

  }
}
new WooVKI_Goods_Import_Cron;
