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
if (is_admin()) {
    add_action('admin_menu', 'webdados_fb_open_graph_add_options');
    
    register_activation_hook(__FILE__, 'webdados_fb_open_graph_activate');
    
    function webdados_fb_open_graph_add_options() {
        global $webdados_fb_open_graph_plugin_name;
        if(function_exists('add_options_page')){
            add_options_page($webdados_fb_open_graph_plugin_name, $webdados_fb_open_graph_plugin_name, 'manage_options', basename(__FILE__), 'webdados_fb_open_graph_admin');
        }
    }
    
    function webdados_fb_open_graph_activate() {
        //Clear WPSEO notices
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare("DELETE FROM ".$wpdb->usermeta." WHERE meta_key LIKE %s", 'wd_fb_og_wpseo_notice_ignore')
        );
    }
    
    function webdados_fb_open_graph_settings_link( $links, $file ) {
        if( $file == 'wonderm00ns-simple-facebook-open-graph-tags/wonderm00n-open-graph.php' && function_exists( "admin_url" ) ) {
            $settings_link = '<a href="' . admin_url( 'options-general.php?page=wonderm00n-open-graph.php' ) . '">' . __('Settings') . '</a>';
            array_push( $links, $settings_link ); // after other links
        }
        return $links;
    }
    add_filter('plugin_row_meta', 'webdados_fb_open_graph_settings_link', 9, 2 );
    
    
    function webdados_fb_open_graph_admin() {
        global $webdados_fb_open_graph_plugin_settings, $webdados_fb_open_graph_plugin_name, $webdados_fb_open_graph_plugin_version;
        webdados_fb_open_graph_upgrade();
        include_once 'includes/settings-page.php';
    }
    
    function webdados_fb_open_graph_scripts() {
        wp_enqueue_script('media-upload');
        wp_enqueue_script('thickbox');
        wp_enqueue_script('jquery');
    }
    function webdados_fb_open_graph_styles() {
        wp_enqueue_style('thickbox');
    }
    add_action('admin_print_scripts', 'webdados_fb_open_graph_scripts');
    add_action('admin_print_styles', 'webdados_fb_open_graph_styles');

    function webdados_fb_open_graph_add_posts_options() {
        global $webdados_fb_open_graph_settings, $webdados_fb_open_graph_plugin_name;
        if (intval($webdados_fb_open_graph_settings['fb_image_use_specific'])==1) {
            global $post;
            //Do not show for some post types
            $exclude_types = array(
                'attachment',
                'nav_menu_item',
                'scheduled-action',
            );
            //WooCommerce?
            if ( class_exists('woocommerce') ) {
                $exclude_types = array_merge( $exclude_types , array(
                    'shop_order',
                    'shop_coupon',
                ) );
            }
            $exclude_types = apply_filters( 'fb_og_metabox_exclude_types', $exclude_types );
            if (is_object($post)) {
                if (!in_array(get_post_type($post->ID), $exclude_types)) {
                    add_meta_box(
                        'webdados_fb_open_graph',
                        $webdados_fb_open_graph_plugin_name,
                        'webdados_fb_open_graph_add_posts_options_box',
                            $post->post_type
                    );
                }
            }
        }
    }
    function webdados_fb_open_graph_add_posts_options_box() {
        global $post;
        // Add an nonce field so we can check for it later.
        wp_nonce_field( 'webdados_fb_open_graph_custom_box', 'webdados_fb_open_graph_custom_box_nonce' );
        // Current value
        $value = get_post_meta($post->ID, '_webdados_fb_open_graph_specific_image', true);
        echo '<label for="webdados_fb_open_graph_specific_image">';
        _e('Use this image:', 'wd-fb-og');
        echo '</label> ';
        echo '<input type="text" id="webdados_fb_open_graph_specific_image" name="webdados_fb_open_graph_specific_image" value="' . esc_attr( $value ) . '" size="75"/>
              <input id="webdados_fb_open_graph_specific_image_button" class="button" type="button" value="'.__('Upload/Choose Open Graph Image','wd-fb-og').'"/>
              <input id="webdados_fb_open_graph_specific_image_button_clear" class="button" type="button" value="'.__('Clear field','wd-fb-og').'"/>';
        echo '<br/>'.__('Recommended size: 1200x630px', 'wd-fb-og');
        echo '<script type="text/javascript">
                jQuery(document).ready(function(){
                    jQuery(\'#webdados_fb_open_graph_specific_image_button\').live(\'click\', function() {
                        tb_show(\'Upload image\', \'media-upload.php?post_id='.$post->ID.'&type=image&context=webdados_fb_open_graph_specific_image_button&TB_iframe=true\');
                    });
                    jQuery(\'#webdados_fb_open_graph_specific_image_button_clear\').live(\'click\', function() {
                        jQuery(\'#webdados_fb_open_graph_specific_image\').val(\'\');
                    });
                });
            </script>';
    }
    add_action('add_meta_boxes', 'webdados_fb_open_graph_add_posts_options');
    function webdados_fb_open_graph_add_posts_options_box_save($post_id) {
        error_log("save post hook $post_id");

        global $webdados_fb_open_graph_settings;
        $save=true;
        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if ( (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || empty($_POST['post_type']))
            return $post_id;

        // If the post is not publicly_queryable (or a page) this doesn't make sense
        $post_type=get_post_type_object(get_post_type($post_id));
        if ($post_type->publicly_queryable || $post_type->name=='page') {
            //OK - Go on
        } else {
            //Not publicly_queryable (or page) -> Go away
            return $post_id;
        }

        // Check if our nonce is set.
        if (!isset($_POST['webdados_fb_open_graph_custom_box_nonce']))
            $save=false;
        
        $nonce=(isset($_POST['webdados_fb_open_graph_custom_box_nonce']) ? $_POST['webdados_fb_open_graph_custom_box_nonce'] : '');

        // Verify that the nonce is valid.
        if (!wp_verify_nonce($nonce, 'webdados_fb_open_graph_custom_box'))
            $save=false;

        // Check the user's permissions.
        if ('page' == $_POST['post_type']) {
            if (!current_user_can('edit_page', $post_id))
                $save=false;
        } else {
            if (!current_user_can('edit_post', $post_id))
                $save=false;
        }

        if ($save) {
            /* OK, its safe for us to save the data now. */
            // Sanitize user input.
            $mydata = sanitize_text_field($_POST['webdados_fb_open_graph_specific_image']);
            // Update the meta field in the database.
            update_post_meta($post_id, '_webdados_fb_open_graph_specific_image', $mydata);
        }

        if ($save) {
            //Force Facebook update anyway - Our meta box could be hidden - Not really! We'll just update if we got our metabox
            if (get_post_status($post_id)=='publish' && intval($webdados_fb_open_graph_settings['fb_adv_notify_fb'])==1) {
                $fb_debug_url='http://graph.facebook.com/?id='.urlencode(get_permalink($post_id)).'&scrape=true&method=post';
                $response=wp_remote_get($fb_debug_url);
                if (is_wp_error($response)) {
                    $_SESSION['wd_fb_og_updated_error']=1;
                    $_SESSION['wd_fb_og_updated_error_message']=__('URL failed:', 'wd-fb-og').' '.$fb_debug_url;
                } else {
                    if ($response['response']['code']==200 && intval($webdados_fb_open_graph_settings['fb_adv_supress_fb_notice'])==0) {
                        $_SESSION['wd_fb_og_updated']=1;
                    } else {
                        if ($response['response']['code']==500) {
                            $_SESSION['wd_fb_og_updated_error']=1;
                            $error=json_decode($response['body']);
                            $_SESSION['wd_fb_og_updated_error_message']=__('Facebook returned:', 'wd-fb-og').' '.$error->error->message;
                        }
                    }
                }
            }
        }

        return $post_id;

    }
    add_action('save_post', 'webdados_fb_open_graph_add_posts_options_box_save');
    function webdados_fb_open_graph_facebook_updated() {
        if ($screen = get_current_screen()) {
            if (isset($_SESSION['wd_fb_og_updated']) && $_SESSION['wd_fb_og_updated']==1 && $screen->parent_base=='edit' && $screen->base=='post') {
                global $post;
                ?>
                <div class="updated">
                    <p><?php _e('Facebook Open Graph Tags cache updated/purged.', 'wd-fb-og'); ?> <a href="http://www.facebook.com/sharer.php?u=<?php echo urlencode(get_permalink($post->ID));?>" target="_blank"><?php _e('Share this on Facebook', 'wd-fb-og'); ?></a></p>
                </div>
                <?php
            } else {
                if (isset($_SESSION['wd_fb_og_updated_error']) && $_SESSION['wd_fb_og_updated_error']==1 && $screen->parent_base=='edit' && $screen->base=='post') {
                    ?>
                    <div class="error">
                        <p><?php
                            echo '<b>'.__('Error: Facebook Open Graph Tags cache NOT updated/purged.', 'wd-fb-og').'</b>';
                            echo '<br/>'.$_SESSION['wd_fb_og_updated_error_message'];
                        ?></p>
                    </div>
                    <?php
                }
            }
        }
        unset($_SESSION['wd_fb_og_updated']);
        unset($_SESSION['wd_fb_og_updated_error']);
        unset($_SESSION['wd_fb_og_updated_error_message']);
    }
    add_action('admin_notices', 'webdados_fb_open_graph_facebook_updated');
    

    // Media insert code
    function webdados_fb_open_graph_media_admin_head() {
        ?>
        <script type="text/javascript">
            function wdfbogFieldsFileMediaTrigger(guid) {
                window.parent.jQuery('#webdados_fb_open_graph_specific_image').val(guid);
                window.parent.jQuery('#TB_closeWindowButton').trigger('click');
            }
        </script>
        <style type="text/css">
            tr.submit, .ml-submit, #save, #media-items .A1B1 p:last-child  { display: none; }
        </style>
        <?php
    }
    function webdados_fb_open_graph_media_fields_to_edit_filter($form_fields, $post) {
        // Reset form
        $form_fields = array();
        $url = wp_get_attachment_url( $post->ID );
        $form_fields['wd-fb-og_fields_file'] = array(
            'label' => '',
            'input' => 'html',
            'html' => '<a href="#" title="' . $url
            . '" class="wd-fb-og-fields-file-insert-button'
            . ' button-primary" onclick="wdfbogFieldsFileMediaTrigger(\''
            . $url . '\')">'
            . __( 'Use as Image Open Graph Tag', 'wd-fb-og') . '</a><br /><br />',
        );
        return $form_fields;
    }
    if ( (isset( $_GET['context'] ) && $_GET['context'] == 'webdados_fb_open_graph_specific_image_button')
            || (isset( $_SERVER['HTTP_REFERER'] )
            && strpos( $_SERVER['HTTP_REFERER'],
                    'context=webdados_fb_open_graph_specific_image_button' ) !== false)
    ) {
        // Add button
        add_filter( 'attachment_fields_to_edit', 'webdados_fb_open_graph_media_fields_to_edit_filter', 9999, 2 );
        // Add JS
        add_action( 'admin_head', 'webdados_fb_open_graph_media_admin_head' );
    }

    //Facebook, Google+ and Twitter user fields
    function webdados_fb_open_graph_add_usercontacts($usercontacts) {
        if (!defined('WPSEO_VERSION')) {
            //Google+
            $usercontacts['googleplus'] = __('Google+', 'wd-fb-og');
            //Twitter
            $usercontacts['twitter'] = __('Twitter username (without @)', 'wd-fb-og');
            //Facebook
            $usercontacts['facebook'] = __('Facebook profile URL', 'wd-fb-og');
        }
        return $usercontacts;
    }
    //WPSEO already adds the fields, so we'll just add them if WPSEO is not active
    add_filter('user_contactmethods', 'webdados_fb_open_graph_add_usercontacts', 10, 1);

    //WPSEO warning
    function webdados_fb_open_graph_wpseo_notice() {
        if (defined('WPSEO_VERSION')) {
            global $current_user, $webdados_fb_open_graph_plugin_name;
            $user_id=$current_user->ID;
            if (!get_user_meta($user_id,'wd_fb_og_wpseo_notice_ignore')) {
                ?>
                <div class="error">
                    <p>
                        <b><?php echo $webdados_fb_open_graph_plugin_name; ?>:</b>
                        <br/>
                        <?php _e('Please ignore the (dumb) Yoast WordPress SEO warning regarding open graph issues with this plugin. Just disable WPSEO Social settings at', 'wd-fb-og'); ?>
                        <a href="admin.php?page=wpseo_social&amp;wd_fb_og_wpseo_notice_ignore=1"><?php _e('SEO &gt; Social','wd-fb-og'); ?></a>
                    </p>
                    <p><a href="?wd_fb_og_wpseo_notice_ignore=1">Ignore this message</a></p>
                </div>
                <?php
            }
        }
    }
    add_action('admin_notices', 'webdados_fb_open_graph_wpseo_notice');
    function webdados_fb_open_graph_wpseo_notice_ignore() {
        if (defined('WPSEO_VERSION')) {
            global $current_user;
            $user_id=$current_user->ID;
            if (isset($_GET['wd_fb_og_wpseo_notice_ignore'])) {
                if (intval($_GET['wd_fb_og_wpseo_notice_ignore'])==1) {
                    add_user_meta($user_id, 'wd_fb_og_wpseo_notice_ignore', '1', true);
                }
            }
        }
    }
    function webdados_fb_open_graph_register_session(){
        if(!session_id())
            session_start();
    }
    function webdados_fb_open_graph_admin_init() {
        webdados_fb_open_graph_wpseo_notice_ignore();
        webdados_fb_open_graph_register_session();
    }
    add_action('admin_init', 'webdados_fb_open_graph_admin_init');

}
