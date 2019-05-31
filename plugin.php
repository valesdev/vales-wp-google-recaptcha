<?php
/**
 * Plugin Name: Vales WP Google reCAPTCHA
 * Description: Silence is golden.
 * Version: 1.0.0
 * Author: Vales Digital
 * Author URI: https://valesdigital.com/
 * GitHub Plugin URI: valesdev/vales-wp-google-recaptcha
 */

class Vales_WP_Google_reCAPTCHA {

  const PLUGIN_NAME = 'Vales WP Google reCAPTCHA';
  const PLUGIN_SLUG = 'vales-wp-google-recaptcha';

  public static function init() {
    add_action('plugins_loaded'       , array(get_class(), 'loadTextdomain'));
    add_action('login_form'           , array(get_class(), 'printLoginFormFields'));
    add_action('login_enqueue_scripts', array(get_class(), 'printLoginFormScripts'));
    add_action('authenticate'         , array(get_class(), 'check'), 50, 3);
  }

  public static function loadTextdomain () {
    return load_plugin_textdomain(static::PLUGIN_SLUG, false, dirname(plugin_basename(__FILE__)) . '/lang');
  }

  public static function printLoginFormFields () {
    switch (static::getOption('version')) {
      case '2':
        ?>
        <div class="g-recaptcha" data-sitekey="<?php echo esc_attr(static::getOption('site_key')); ?>"></div>
        <?php
        break;
      case '3':
        ?>
        <input type="hidden" name="g-recaptcha-response" />
        <?php
        break;
    }
  }

  public static function printLoginFormScripts () {
    switch (static::getOption('version')) {
      case '2':
        ?>
          <style type="text/css">
            #login { width: 352px !important; }
            .g-recaptcha { margin-bottom: 16px !important; }
          </style>
          <script src="https://www.google.com/recaptcha/api.js" async defer></script>
        <?php
        break;
      case '3':
        ?>
          <script src="https://www.google.com/recaptcha/api.js?render=<?php echo esc_attr(static::getOption('site_key')); ?>"></script>
          <script>
            grecaptcha.ready(function() {
              grecaptcha
                .execute('<?php echo esc_attr(static::getOption('site_key')); ?>')
                .then(function (token) {
                  window.document.getElementsByName('g-recaptcha-response')[0].value = token;
                });
            });
          </script>
        <?php
        break;
    }
  }

  public static function check ($user, $username, $password) {
    if (! empty($username) && ! empty($password)) {
      $ok = false;
      $secretKey = static::getOption('secret_key');
      if (empty($secretKey)) {
        $ok = true;
      }
      if ($ok !== true && array_key_exists('g-recaptcha-response', $_POST)) {
        $ch = curl_init();
        curl_setopt_array($ch, array(
          CURLOPT_URL            => 'https://www.google.com/recaptcha/api/siteverify',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_TIMEOUT        => 30,
          CURLOPT_POST           => true,
          CURLOPT_POSTFIELDS     => array(
            'secret'   => static::getOption('secret_key'),
            'response' => $_POST['g-recaptcha-response'],
            'remoteip' => $_SERVER['REMOTE_ADDR'],
          ),
        ));
        $response = curl_exec($ch);
        curl_close($ch);
        $response_data = json_decode($response, true);
        if (is_array($response_data) && array_key_exists('success', $response_data) && $response_data['success'] === true) {
          $ok = true;
        }
      }
      if ($ok !== true) {
        $user = new WP_Error('authentication_failed', _x('<strong>ERROR</strong>: reCAPTCHA validation failed.', 'messages', static::PLUGIN_SLUG));
      }
    }
    return $user;
  }

  private static function getOption ($key) {
    $options = get_option(static::PLUGIN_SLUG);
    if (is_array($options) && array_key_exists($key, $options)) {
      return $options[$key];
    }
  }
}

class Vales_WP_Google_reCAPTCHA_Admin {

  public static function init() {
    add_action('admin_menu', array(get_class(), 'addAdminPages'));
    add_action('admin_init', array(get_class(), 'initSettings'));
  }

  public static function addAdminPages () {
    add_options_page(
      Vales_WP_Google_reCAPTCHA::PLUGIN_NAME,
      'Google reCAPTCHA',
      'manage_options',
      Vales_WP_Google_reCAPTCHA::PLUGIN_SLUG,
      array(get_class(), 'renderOptionsPage')
    );
  }

  public static function initSettings () {
    register_setting(Vales_WP_Google_reCAPTCHA::PLUGIN_SLUG, Vales_WP_Google_reCAPTCHA::PLUGIN_SLUG);

    add_settings_section(
      'main',
      null,
      null,
      Vales_WP_Google_reCAPTCHA::PLUGIN_SLUG
    );

    add_settings_field(
      'version',
      'Version',
      array(get_class(), 'renderSettingsFieldVersion'),
      Vales_WP_Google_reCAPTCHA::PLUGIN_SLUG,
      'main'
    );

    add_settings_field(
      'site_key',
      'Site Key',
      array(get_class(), 'renderSettingsFieldSiteKey'),
      Vales_WP_Google_reCAPTCHA::PLUGIN_SLUG,
      'main'
    );

    add_settings_field(
      'secret_key',
      'Secret Key',
      array(get_class(), 'renderSettingsFieldSecretKey'),
      Vales_WP_Google_reCAPTCHA::PLUGIN_SLUG,
      'main'
    );
  }

  public static function renderOptionsPage () {
    ?>
    <div class="wrap">
      <h1><?php echo esc_html(Vales_WP_Google_reCAPTCHA::PLUGIN_NAME); ?></h1>
      <form action="options.php" method="post">
        <?php
          settings_fields(Vales_WP_Google_reCAPTCHA::PLUGIN_SLUG);
          do_settings_sections(Vales_WP_Google_reCAPTCHA::PLUGIN_SLUG);
          submit_button();
        ?>
      </form>
    </div>
    <?php
  }

  public static function renderSettingsFieldVersion () {
    $options = get_option(Vales_WP_Google_reCAPTCHA::PLUGIN_SLUG);
    ?>
    <select name="<?php echo esc_attr(Vales_WP_Google_reCAPTCHA::PLUGIN_SLUG); ?>[version]">
      <option value="2"<?php selected($options['version'], '2'); ?>>v2</option>
      <option value="3"<?php selected($options['version'], '3'); ?>>v3</option>
    </select>
    <?php
  }

  public static function renderSettingsFieldSiteKey () {
    $options = get_option(Vales_WP_Google_reCAPTCHA::PLUGIN_SLUG);
    ?>
    <input type="text" name="<?php echo esc_attr(Vales_WP_Google_reCAPTCHA::PLUGIN_SLUG); ?>[site_key]" value="<?php echo esc_attr($options['site_key']); ?>" class="regular-text">
    <?php
  }

  public static function renderSettingsFieldSecretKey () {
    $options = get_option(Vales_WP_Google_reCAPTCHA::PLUGIN_SLUG);
    ?>
    <input type="text" name="<?php echo esc_attr(Vales_WP_Google_reCAPTCHA::PLUGIN_SLUG); ?>[secret_key]" value="<?php echo esc_attr($options['secret_key']); ?>" class="regular-text">
    <?php
  }
}

Vales_WP_Google_reCAPTCHA::init();

Vales_WP_Google_reCAPTCHA_Admin::init();
