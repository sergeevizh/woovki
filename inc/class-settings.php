<?php

/**
 * Settings UI
 */
class WooVKI_Settings {

  function __construct() {
    add_action('admin_menu', function(){
			add_options_page(
				$page_title = 'WooVKI',
				$menu_title = 'WooVKI',
				$capability = 'manage_options',
				$menu_slug = 'woovki',
				$function = array($this, 'settings_ui')
			);
		});

    add_action( 'admin_init', array($this, 'settings_init') );

  }

  function settings_init(){
    add_settings_section(
			$name = 'woovki_settings_section_main',
			$title = 'Основные настройки',
			$callback = array($this, 'display_woovki_settings_section_main'),
			$page = 'woovki'
		);


    register_setting(
			$option_group = 'woovki',
			$option_name = 'woovki_app_id'
		);

		add_settings_field(
			$id = 'woovki_app_id',
			$title = 'ID приложения',
			$callback = [$this, 'display_woovki_app_id'],
			$page = 'woovki',
			$section = 'woovki_settings_section_main'
		);


    register_setting(
			$option_group = 'woovki',
			$option_name = 'woovki_key_secret'
		);

		add_settings_field(
			$id = 'woovki_key_secret',
			$title = 'Защищённый ключ',
			$callback = [$this, 'display_woovki_key_secret'],
			$page = 'woovki',
			$section = 'woovki_settings_section_main'
		);


    register_setting(
			$option_group = 'woovki',
			$option_name = 'woovki_key_access'
		);

		add_settings_field(
			$id = 'woovki_key_access',
			$title = 'Сервисный ключ доступа',
			$callback = [$this, 'display_woovki_key_access'],
			$page = 'woovki',
			$section = 'woovki_settings_section_main'
		);


    register_setting(
			$option_group = 'woovki',
			$option_name = 'woovki_owner_id'
		);

		add_settings_field(
			$id = 'woovki_owner_id',
			$title = 'ИД владельца товаров',
			$callback = [$this, 'display_woovki_owner_id'],
			$page = 'woovki',
			$section = 'woovki_settings_section_main'
		);
  }

  function display_woovki_owner_id(){
    printf('<input type="text" id="woovki_owner_id" name="woovki_owner_id" value="%s" size="55"  />', get_option('woovki_owner_id') );
  }
  function display_woovki_app_id(){
    printf('<input type="text" id="woovki_app_id" name="woovki_app_id" value="%s" size="55"  />', get_option('woovki_app_id') );
  }
  function display_woovki_key_secret(){
    printf('<input type="text" id="woovki_key_secret" name="woovki_key_secret" value="%s" size="55"  />', get_option('woovki_key_secret') );
  }
  function display_woovki_key_access(){
    printf('<input type="text" id="woovki_key_access" name="woovki_key_access" value="%s" size="55"  />', get_option('woovki_key_access') );
  }


  function display_woovki_settings_section_main(){
    ?>
    <p>Параметры для настройки интеграции можно получить на специальной странице: <a href="https://vk.com/apps?act=manage" target="_blank">https://vk.com/apps?act=manage</a></p>
    <hr>
    <?php
  }

  function settings_ui(){
    ?>
    <div class="wrap">
        <h1>WooVKI - настройка интеграции ВКонтакте и WooCommerce</h1>
        <form action="options.php" method="POST">
            <?php settings_fields( 'woovki' ); ?>
            <?php do_settings_sections( 'woovki' ); ?>
            <?php submit_button(); ?>
        </form>
	    </div>

    <?php
  }
}
new WooVKI_Settings;
