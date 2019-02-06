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
        add_action('admin_menu',array($this,'add_admin_menu'),20) ;
        add_action('admin_init',array($this,'register_settings')) ;
        add_shortcode('pg_zendesk_view',array($this,'view_html')) ;
        $params_zd = Array() ;
        foreach ( PG_Zendesk::$champs as $c )
            $params_zd[$c] = get_option('pgzd_'.$c) ;
        $this->Zendesk = new PG_Zendesk($params_zd) ;
        //register_activation_hook(__FILE__, array('Zendesk_View', 'install')) ;
        register_uninstall_hook(__FILE__, array('Zendesk_View', 'uninstall')) ;
    }

    public function add_admin_menu() {
        add_menu_page('Zendesk view','Zendesk view','manage_options','zendesk_view',array($this,'config_generale_html')) ;
        add_submenu_page('zendesk_view','Config générale','Config générale','manage_options','zendesk_view',array($this,'config_generale_html')) ;
    }

    public function config_generale_html() {
        echo '<h1>'.get_admin_page_title().'</h1>' ;
        ?>
        <form method="post" action="options.php">
            <?php
                settings_fields('pg_zendesk_options') ;
                foreach ( PG_Zendesk::$champs as $c )
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

    public function register_settings() {
        foreach ( PG_Zendesk::$champs as $c )
            register_setting('pg_zendesk_options','pgzd_'.$c) ;
    }

    public function view_html($atts,$content) {
        ini_set('display_errors',1) ;
        error_reporting(E_ALL) ;
        if ( ! $this->Zendesk ) return ;
        $this->Zendesk->showView($atts['id'],$atts,$content) ;
    }

    //public static function install() { }

    public static function uninstall() { 
        foreach ( PG_Zendesk::$champs as $c )
            delete_option('pgzd_'.$c) ;
    }

}

new PG_Zendesk_View() ;
