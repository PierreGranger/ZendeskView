<?php
/**
 * @package Zendesk_View
 * @version 0.1
 */
/*
Plugin Name: Zendesk View
Plugin URI: https://github.com/PGranger/ZendeskView
Description: Afficher une vue Zendesk sur votre site.
Author: Pierre Granger
Version: 0.1
Author URI: http://www.pierre-granger.fr
*/

// Evite l'appel direct du plugin hors environnement Wordpress
if ( !function_exists( 'add_action' )) {
	echo 'Erreur...';
	exit;
}

include_once plugin_dir_path(__FILE__).'/PGZendesk.php' ;

class PG_Zendesk_View {

    public static $table = 'pg_zendesk_view' ;
    public $Zendesk ;

    public function __construct() {
        register_activation_hook(__FILE__, array('Zendesk_View', 'install')) ;
        register_uninstall_hook(__FILE__, array('Zendesk_View', 'uninstall')) ;
        add_action('wp_loaded', array($this, 'save_view')) ;
        add_action('wp_loaded', array($this, 'drop_view')) ;
        add_action('admin_menu',array($this,'add_admin_menu'),20) ;
        add_action('admin_init',array($this,'register_settings')) ;
        add_shortcode('pg_zendesk_view',array($this,'view_html')) ;
        $params_zd = Array() ;
        foreach ( PG_Zendesk_Class::$champs as $c )
            $params_zd[$c] = get_option('pgzd_'.$c) ;
        $this->Zendesk = new PG_Zendesk_Class($params_zd) ;
    }

    public static function install() {
        global $wpdb ;

        $wpdb->query(" create table if not exists {$wpdb->prefix}".PG_Zendesk_View::$table." (
            id int auto_increment primary key,
            subdomain varchar(100) not null,
            baseurl varchar(255) not null,
            username varchar(100) not null,
            token varchar(100) not null,
            userid varchar(100) not null
        ) ; ") ;
    }

    public static function uninstall() {
        global $wpdb ;
        $wpdb->query(" drop table if exists {$wpdb->previx}".PG_Zendesk_View::$table." ; ") ;
    }

    public function add_admin_menu() {
        add_menu_page('Zendesk view','Zendesk view','manage_options','zendesk_view',array($this,'config_generale_html')) ;
        add_submenu_page('zendesk_view','Config générale','Config générale','manage_options','zendesk_view',array($this,'config_generale_html')) ;
        add_submenu_page('zendesk_view','Config des vues','Config des vues','manage_options','zendesk_view_views',array($this,'config_vues_html')) ;
    }

    public function config_generale_html() {
        echo '<h1>'.get_admin_page_title().'</h1>' ;
        ?>
        <form method="post" action="options.php">
            <?php
                settings_fields('pg_zendesk_options') ;
                foreach ( PG_Zendesk_Class::$champs as $c )
                {
                    $value = get_option('pgzd_'.$c) ;
                    echo '<p>' ;
                        echo '<label for="pgzd_'.$c.'">'._e($c).' : </label>' ;
                        echo '<input id="pgzd_'.$c.'" name="pgzd_'.$c.'" type="text" value="'.$value.'" />' ;
                    echo '</p>' ;
                }
                submit_button() ;
            ?>
        </form>
        <?php
    }

    public function config_vues_html() {
        echo '<h1>'.get_admin_page_title().'</h1>' ;
        echo '<h1 style="color:red;">TODO : permettre de choisir ce qu\'on veut afficher pour chaque vue. Ex :</h1>' ;

        $vues_demo = Array(
            360018293851 => Array(true,'{"priority","status","submitter_id","assignee_id","organisation_id"}','{360000041171,360000082191,360000082031,360000918792}'),
            360018293852 => Array(false,'{"priority","status","submitter_id","assignee_id","organisation_id"}','{360000041171,360000918792,360001829191}')
        ) ;

        echo '<form>' ;
            echo '<table class="wp-list-table widefat fixed striped posts">' ;
                echo '<thead>' ;
                    echo '<th>Identifiant Zendesk de la vue</th>' ;
                    echo '<th>Afficher le descriptif des tickets ?</th>' ;
                    echo '<th>Champs standards à afficher (json)</th>' ;
                    echo '<th>Custom fields à afficher (json)</th>' ;
                echo '</thead>' ;
                echo '<tbody>' ;
                    foreach ( $vues_demo as $id_vue => $vue )
                    {
                        echo '<tr>' ;
                            echo '<th><input type="text" value="'.$id_vue.'" /></th>' ;
                            echo '<td><input type="checkbox" value="1"'.($vue[0]?' checked="checked"':'').' /></td>' ;
                            echo '<td><textarea>'.$vue[1].'</textarea></td>' ;
                            echo '<td><textarea>'.$vue[2].'</textarea></td>' ;
                        echo '</tr>' ;
                    }
                echo '</tbody>' ;
            echo '</table>' ;
            submit_button() ;
        echo '</form>' ;
    }

    public function register_settings() {
        foreach ( PG_Zendesk_Class::$champs as $c )
            register_setting('pg_zendesk_options','pgzd_'.$c) ;
    }

    public function save_view() {
        if ( ! isset($_POST['zendesk_view']['subdomain']) ) return ;
        global $wpdb ;

        //$sets = Array() ;
        //$wpdb->query (" insert into {$wpdb->prefix}zendesk_view set ".implode(',',$sets)." on duplicate key update ".implode(',',$sets)) ;
        
        $post = array_filter($_POST['zendesk_view'],function($k){ return in_array($k,Zendesk_View::$champs) ; },ARRAY_FILTER_USE_KEY) ;
        $wpdb->insert(" {$wpdb->prefix}zendesk_view ",$post) ;
    }

    public function drop_view() {
        if ( ! isset($_POST['zendesk_view']['drop_id']) ) return ;
        $drop_id = $_POST['zendesk_view']['drop_id'] ;
        global $wpdb ;
        $wpdb->query (" delete from {$wpdb->prefix}zendesk_view where id = '$drop_id'") ;
    }

    public function view_html($atts,$content) {
        ini_set('display_errors',1) ;
        error_reporting(E_ALL) ;
        if ( ! $this->Zendesk ) return ;
        $this->Zendesk->showView($atts['id'],$atts,$content) ;
    }

}

new PG_Zendesk_View() ;
