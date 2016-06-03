<?php
/**
 * Plugin Name: OG Graph Preload
 * Description: depends on qqworld, wp-api/basic-auth, rest-api, wonderm00ns
 * Author: mezzoky
 * Author URI: https://github.com/...
 * Version: 0.1
 * Plugin URI: https://github.com/...
 */

//Admin
$username = $_SERVER['PHP_AUTH_USER'];
$password = $_SERVER['PHP_AUTH_PW'];
error_log("dagger: $username:$password");

function og_graph_preload_fn($post_id) {
    error_log("dagger: save post hook $post_id");

    $save=true;
    // If this is an autosave, our form has not been submitted, so we don't want to do anything.
    // if ( (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || empty($_POST['post_type']))
    //     error_log("dagger: DOING_AUTOSAVE");
    //     return $post_id;

    // If the post is not publicly_queryable (or a page) this doesn't make sense
    // $post_type=get_post_type_object(get_post_type($post_id));
    // if ($post_type->publicly_queryable || $post_type->name=='page') {
    //     //OK - Go on
    // } else {
    //     //Not publicly_queryable (or page) -> Go away
    //     error_log("dagger: Not publicly_queryable (or page)");
    //     return $post_id;
    // }

    // Check the user's permissions.
    // if ('page' == $_POST['post_type']) {
    //     if (!current_user_can('edit_page', $post_id))
    //         $save=false;
    //         error_log("dagger: edit page false");
    // } else {
    //     if (!current_user_can('edit_post', $post_id))
    //         error_log("dagger: edit post false");
    //         $save=false;
    // }

    // if ($save) {
    //     /* OK, its safe for us to save the data now. */
    //     // Sanitize user input.
    //     $mydata = sanitize_text_field($_POST['webdados_fb_open_graph_specific_image']);
    //     // Update the meta field in the database.
    //     update_post_meta($post_id, '_webdados_fb_open_graph_specific_image', $mydata);
    // }

    error_log("dagger: presave $save");

    if ($save) {
        error_log("dagger: saving");

        //Force Facebook update anyway - Our meta box could be hidden - Not really! We'll just update if we got our metabox
        if (get_post_status($post_id)=='publish') {
            $fb_debug_url='http://graph.facebook.com/?id='.urlencode(get_permalink($post_id)).'&scrape=true&method=post';
            $response=wp_remote_get($fb_debug_url);
            error_log("dagger: sent to fb: $fb_debug_url");
            if (is_wp_error($response)) {
                $_SESSION['wd_fb_og_updated_error']=1;
                $_SESSION['wd_fb_og_updated_error_message']=__('URL failed:', 'wd-fb-og').' '.$fb_debug_url;
            } else {
                $code = $response['response']['code'];
                error_log("dagger: code($code)");
                if ($response['response']['code']==200) {
                    error_log("dagger: SUCCESS");
                    $_SESSION['wd_fb_og_updated']=1;
                }
            }
        }
    }

    return $post_id;

}
add_action('save_post', 'og_graph_preload_fn');
