<?php

/*
Plugin Name: MySuggest 
Plugin URI: https://kmak.1funkybit.com/
Description: Displays a modal form to visitors (not logged in users) requesting suggestions for improving the site. Saves the suggestion in DB. If the user cancels and doesn't provide a suggestion, then after 20 seconds when he loads another page he will get another modal.
Version: 0.1
Author: Konstantinos Katsamakas
Author URI: https://kmak.1funkybit.com/
License: GPLv2 or later
Text Domain: mysuggest
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
        echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
        exit;
}

//This class implements mysuggest plugin
class MySuggest {

    public function plugin_activation(){
        //create tables in DB here
        global $wpdb;

        $table_name = $wpdb->prefix . 'mysuggestions';

        $sql = "CREATE TABLE $table_name (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `email` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
                `message` mediumtext COLLATE utf8_unicode_ci NOT NULL,
                `ip` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
                `browser` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
                `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        add_option( 'mysuggest_db_version', '1.0' );
    }

    public function plugin_deactivation(){
    }

    public function initCookies(){
        if(!isset($_COOKIE['requested_suggestion']) && !isset($_COOKIE['have_made_suggestion'])){
            //We add a 20 seconds as a nag timeout, before visitors get another modal if they cancel it without providing a suggestion
            setcookie("requested_suggestion", 0, time()+20, COOKIEPATH, COOKIE_DOMAIN, false, true);
        }
    }

    public function showModal(){
        //decide if we will show modal this time ....
        if ( is_user_logged_in() || isset($_COOKIE['have_made_suggestion']) || isset($_COOKIE['requested_suggestion']) ) {
            return; //we show no modal
        }

        //show a modal on any page
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('jquery-ui-dialog', '/wp-includes/css/jquery-ui-dialog.min.css');
        wp_enqueue_style('jquery-ui-fix', plugin_dir_url(__FILE__) . 'mysuggest.css');
        wp_enqueue_script('mysuggest.js');
        wp_enqueue_script('test', plugin_dir_url(__FILE__) . 'mysuggest.js');

        $modal_form = 
        '<div id="modal_suggestion_dialog" style="display: none;">
            <form action="'.esc_url( admin_url('admin-post.php') ).'" method="post">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="emailSug" required>
                <label for="message">How can we improve our site?</label>
                <textarea name="message" id="messageSug"></textarea>
                <input type="hidden" name="action" value="mysuggestion" id="actionSug">
                '.wp_nonce_field( 'mysuggestion_csrf' ).'
                <input id="suggestButton" type="button" value="Send My Suggestion" onclick="ajaxTheSuggestion();" style="margin-top: 10px;">
                <img id="suggestLoading" src="'. plugin_dir_url(__FILE__).'images/loading.gif" alt="loading" style="display: none;" />
            </form>
        </div>
        <div id="dialog-message" title="Some message">
          <p id="ajaxRetMsg">
          </p>
        </div>
        ';

        echo $modal_form;
    }

    public function postSuggestion(){
        //We verify the nonce , CRSF protection
        $nonce = $_REQUEST['_wpnonce'];
        if ( ! wp_verify_nonce( $nonce, 'mysuggestion_csrf' ) ) {
            wp_send_json(array('success' => false, 'error' => 'CSRF token check failed!'));
            exit; // Corrupted nonce, perhaps CSRF attack? we exit
        }

        $email = trim($_REQUEST['email']);
        $message = trim($_REQUEST['message']);
        $ip = $_SERVER['REMOTE_ADDR'];
        $browser = $_SERVER['HTTP_USER_AGENT'];

        //We save the values in DB
        $email = sanitize_email($email);
        $message = sanitize_textarea_field($message); //We sanitize user input before saving in DB, we dont want SQL injections or XSS attacks
        //We do some validations regarding email and message now
        if ($email=='' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            wp_send_json(array('success' => false, 'error' => 'Not a valid formatted email!'));
            exit;
	}
        if($message==''){
            wp_send_json(array('success' => false, 'error' => 'Message can not be empty!'));
            exit;
        }
        
        //We save in the DB using the API and not crafting an SQL query with user input, thus avoiding SQL injections
        global $wpdb;
        $table_name = $wpdb->prefix . 'mysuggestions';
        $wpdb->insert( $table_name, array( 'email' => $email, 'message' => $message, 'ip' => $ip, 'browser' => $browser ), array( '%s', '%s', '%s', '%s' ) );

        //set cookie that we have made a suggestion, visitor will not get
        //another modal for 2 months.
        setcookie("have_made_suggestion", 1, strtotime( '+60 days' ), COOKIEPATH, COOKIE_DOMAIN, false, true);

        //we return success
        $message = esc_html($message); //we handle output escaping here
        wp_send_json(array('success' => true, 'suggestion' => $message));
    }

    public function filter_nonce_user_logged_out($uid, $action){
        //If we are here, we have $uid=0 ,a not logged in user, we must for security
        //create a nonce with some other than uid seed, otherwise all
        //non loggedin users will have the same nonce and this beats the
        //purpose for csrf protection.
        //use ip and browser
        return $_SERVER['REMOTE_ADDR'].$_SERVER['HTTP_USER_AGENT'];
    }

    public function myAdminMenu(){
        add_menu_page( 'Visitor suggestions view page', 'Visitor suggestions!', 'manage_options', 'mysuggest/mysuggest-admin-page.php', array($this, 'myAdminPage'), 'dashicons-format-status', 6 );
    }

    public function myAdminPage(){
        //query database for all suggestions
        global $wpdb;
        $suggestions = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}mysuggestions", OBJECT );
        if(count($suggestions)==0){
            echo '<h2>No suggestions yet!</h2>';
            return;
        }
        else{
            echo "<h2>There are ".count($suggestions)." suggestions</h2>";
        }
        wp_enqueue_style('jquery-ui-fix', plugin_dir_url(__FILE__) . 'mysuggest.css');

?>
        <table class="mysuggestTable">
            <tr><th>ID</th><th>email</th><th>message</th><th>created at</th><th>IP</th><th>Browser</th></tr>
        <?php foreach ( $suggestions as $s ): ?>
            <tr><th><?php echo $s->id; ?></th><th><?php echo esc_html($s->email); ?></th><th><?php echo esc_html($s->message); ?></th><th><?php echo $s->created; ?></th><th><?php echo $s->ip; ?></th><th><?php echo $s->browser; ?></th></tr>
        <?php endforeach; ?>
        </table>
<?php	
    }
}

$rs = new MySuggest();

add_action('init', array($rs, 'initCookies'));
add_action('wp_footer', array($rs, 'showModal'));
add_action('admin_post_nopriv_mysuggestion', array($rs, 'postSuggestion') );
add_filter( 'nonce_user_logged_out', array($rs, 'filter_nonce_user_logged_out'), 10, 2 );
add_action( 'admin_menu', array($rs, 'myAdminMenu') );

register_activation_hook( __FILE__, array( $rs, 'plugin_activation' ) );
register_deactivation_hook( __FILE__, array( $rs, 'plugin_deactivation' ) );

