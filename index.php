<?php
/**
 * Plugin Name: OG Graph Preload
 * Description: depends on qqworld, wp-api/basic-auth, rest-api, wonderm00ns
 * Author: mezzoky
 * Author URI: https://github.com/...
 * Version: 0.1
 * Plugin URI: https://github.com/...
 */

$username = $_SERVER['PHP_AUTH_USER'];
$password = $_SERVER['PHP_AUTH_PW'];


function og_graph_preload_fn($post_id) {
    error_log("dagger: save post hook $post_id");
    error_log("dagger: $username:$password");
    $a = is_admin();
    error_log("dagger: $a");
    $user = wp_authenticate( $username, $password );
    if ( is_wp_error( $user ) ) {
        error_log("dagger: AUTH ERROR");
        return $post_id;
    }

    $save=true;
    if ($save) {
        //Force Facebook update anyway - Our meta box could be hidden - Not really! We'll just update if we got our metabox
        if (get_post_status($post_id)=='publish') {
            $fb_debug_url='http://graph.facebook.com/?id='.urlencode(get_permalink($post_id)).'&scrape=true&method=post';
            $response=wp_remote_get($fb_debug_url);
            error_log("dagger: sent to fb:og: $fb_debug_url");
            if (is_wp_error($response)) {
                error_log("dagger: code(ERR send to fb:og)");
            } else {
                $code = $response['response']['code'];
                error_log("dagger: code($code)");
            }
        }
    }
    return $post_id;
}
add_action('save_post', 'og_graph_preload_fn');
