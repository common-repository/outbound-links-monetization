<?php

/*
Plugin Name: Outbound Links Monetization
Plugin URI: http://www.urlshortener.co
Description: This plugin will short automatically all the outbound links to monetize your website.
Version: 1.0
Author URI: http://www.urlshortener.co
Date: 9/14/2016
*/

function ShortenURL_activate()
{
    // Activation code here...
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'shorten_urls';

    $sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		long_url varchar(255) NOT NULL,
		short_url varchar(255) NOT NULL,
		post_ID bigint(20) NOT NULL,
		UNIQUE KEY id (id)
	) $charset_collate;";

    dbDelta($sql);
}

register_activation_hook(__FILE__, 'ShortenURL_activate');

function ShortenURL_deactivate()
{
    // Deactivation code here...
    delete_option('shorten_url_api_key');
    delete_option('shorten_url_access_token');
    delete_option('allow_shorten_url');
    global $wpdb;
    $table_name = $wpdb->prefix . 'shorten_urls';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

register_deactivation_hook(__FILE__, 'ShortenURL_deactivate');

function ShortenURL_scripts()
{
  //  wp_enqueue_style('default', '/wp-content/plugins/imageZoom/css/default.css');
}

add_action('wp_enqueue_scripts', 'ShortenURL_scripts');

add_action('admin_menu', 'ShortenURL_admin_menu');

add_action('admin_init', 'register_shorten_url_settings');

function ShortenURL_admin_menu()
{
    add_options_page('Links Monetization', 'Links Monetization', 'manage_options', 'shortenurl_settings', 'shortenurl_admin');
}

function register_shorten_url_settings()
{
    register_setting('shorten-url-option-group', 'shorten_url_api_key');
    register_setting('shorten-url-option-group', 'shorten_url_access_token');
    register_setting('shorten-url-option-group', 'allow_shorten_url');
}

function shortenurl_admin()
{
    echo '<div class="wrap">' .
        '<h4>Outbound Links Monetization Settings</h4>' .
        '<form method="post" action="options.php">';
    settings_fields('shorten-url-option-group');
    do_settings_sections('shorten-url-option-group');
    echo '<table class="form-table">' .
        '<tbody>' .
        '<tr>' .
        '<th scope="row"><label for="shorten_url_api_key">Urlshortener.co API Key:</label></th>' .
        '<td>' .
        '<input name="shorten_url_api_key" type="text" id="shorten_url_api_key" value="' . esc_attr(get_option('shorten_url_api_key')) . '" />' .
        '</td>' .
        '</tr>' .
        '<tr>' .
        '<th scope="row"><label for="shorten_url_access_token">Urlshortener.co Access Token:</label></th>' .
        '<td>' .
        '<input name="shorten_url_access_token" type="text" id="shorten_url_access_token" value="' . esc_attr(get_option('shorten_url_access_token')) . '" />' .
        '</td>' .
        '</tr>' .
        '<tr>';
    $value = esc_attr(get_option('allow_shorten_url'));
    if ($value == 1) {
        $checked = 'checked value="1"';
    } else {
        $checked = 'value="1"';
    }
    echo '<th scope="row"><label for="allow_shorten_url">Shorten all outbound links</label></th>' .
        '<td><input name="allow_shorten_url" type="checkbox" id="allow_shorten_url" ' . $checked . '>' .
        '</td>' .
        '</tr>' .
        '</tbody>' .
        '</table><p><i>You API Key and Token can you find in your <a href="http://www.urlshortener.co" title="CPA Link Shortener" target=_blank><strong>Urlshortener.co</strong></a> Account! <br />If you don&#x92;t have an account, you can create one and earn money with first <strong>CPA Link Shortener</strong> with <strong>Anti-Adblock Solution</strong>!</i></p>';
    submit_button();
    echo '</form > ' .
        '</div > ';
}

add_action('save_post', 'get_content_to_shorten_url', 10, 3);

function get_content_to_shorten_url($post_id)
{
    global $wpdb;
    $check = get_option('allow_shorten_url');
    $key = get_option('shorten_url_api_key');
    $token = get_option('shorten_url_access_token');
    if (isset($check) && !empty($check) && isset($key) && !empty($key) && isset($token) && !empty($token) && $check == 1) {
        $the_query = new WP_Query(array('post_type' => 'post'));
        while ($the_query->have_posts()):
            $the_query->the_post();
            $_post_id = get_the_id();
//Write Site URL below.
//Don't write http:// or anything like that. just domain.com or domain.net
            $_site_url = site_url();

//Getting Post content
            $_post_content = get_post_field('post_content', $_post_id);

            $site_parts = explode('.', $_site_url);
            $site_suffix = '.' . $site_parts[1];
//Using regular expression to match hyperlink
            preg_match_all('|<a.*(?=href=\"([^\"]*)\")[^>]*>([^<]*)</a>|i', $_post_content, $match);
            foreach ($match[0] as $link) {
                //Filtering out internal links
                $parts = explode($site_suffix, $link);
                $domain = explode('//', $parts[0]);
                if ($domain[1] != 'www.' . $site_parts[0] && $domain[1] != $site_parts[0]) {
                    $table = $wpdb->prefix . 'shorten_urls';
                    $alreadyExist = $wpdb->get_results('SELECT * from  ' . $table . ' WHERE short_url like "%' . $match[1][0] . '%"');
                    if (empty($alreadyExist)) {
                        $shortener = 'http://access.urlshortener.co/short/?token=' . $token . '&key=' . $key . '&absolute_url=' . $match[1][0];
                        $response = file_get_contents($shortener);
                        if (isset($response) && !empty($response)) {
                            $wpdb->insert('wp_shorten_urls', array('long_url' => $match[1][0], 'short_url' => $response, 'post_ID' => $_post_id));
                            $wpdb->query("update wp_posts set post_content = replace(post_content,'" . $match[1][0] . "','" . $response . "')");
                        }
                    }
                }
            }

        endwhile;
        wp_reset_postdata();
    }
}

?>
