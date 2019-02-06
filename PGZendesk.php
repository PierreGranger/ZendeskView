<?php
/**
 * @package Zendesk_View
 * @version 0.1
 */
/*
Classe d'utilisation de l'API de Zendesk.
*/
class PG_Zendesk {

    private $subdomain ;
    private $baseurl ;
    private $username ;
    private $token ;
    private $userid ;
    private $debug = false ;

    public static $champs = Array('subdomain','baseurl','username','token','userid') ;

    private $cache = Array() ;
    private $expire = 60*15 ; // Secondes

    private static $champsDate = Array('updated','created','updated_at','created_at') ;
    private static $dateFormat = 'd/m/Y' ;

    private static $champsUser = Array('assignee','assignee_id','requester','requester_id','submitter','submitter_id') ;

    private static $presentations = Array('table','liste') ;

    public function __construct($params) {
        foreach ( self::$champs as $c )
            if ( isset($params[$c]) ) $this->$c = $params[$c] ; else throw new Exception(__CLASS__.':Missing '.$c) ;
        if ( isset($params['debug']) ) $this->debug = $params['debug'] ;
    }

    public function getView($id) {
        return @$this->getCurl('/api/v2/views/'.$id.'.json')['view'] ;
    }

    public function getViewTickets($id) {
        $tickets = $this->getCurl('/api/v2/views/'.$id.'/tickets.json')['tickets'] ;
        if ( $tickets === false ) return false ;
        foreach ( $tickets as $k => $v )
        {
            if ( isset($v['fields']) )
            {
                $tmp_fields = $v['fields'] ;
                $tickets[$k]['fields'] = Array() ;
                foreach ( $tmp_fields as $f )
                {
                    $tickets[$k]['fields'][$f['id']] = $f['value'] ;
                }
            }
        }
        return $tickets ;
    }

    public function getGroup($id) {
        return $this->getCurl('/api/v2/groups/'.$id.'.json')['group'] ;
    }

    public function getForm($id) {
        return $this->getCurl('/api/v2/ticket_forms/'.$id.'.json')['ticket_form'] ;
    }

    public function getUser($id) {
        return $this->getCurl('/api/v2/users/'.$id.'.json')['user'] ;
    }

    public function getField($id) {
        $tmp = $this->getCurl('/api/v2/ticket_fields/'.$id.'.json')['ticket_field'] ;
        if ( $tmp === false ) return false ;
        if ( isset($tmp['custom_field_options']) )
        {
            $tmp['custom_field_options_by_value'] = Array() ;
            foreach ( $tmp['custom_field_options'] as $i_opt => $opt )
                $tmp['custom_field_options_by_value'][$opt['value']] = $opt ;
            $tmp['custom_field_options_by_id'] = Array() ;
                foreach ( $tmp['custom_field_options'] as $i_opt => $opt )
                    $tmp['custom_field_options_by_id'][$opt['id']] = $opt ;
        }
        return $tmp ;
    }

    public function getCurl($path,$ret='array') {

        $cacheKey = preg_replace('#[^a-zA-Z0-9]#','_',$path) ;

        if ( ! isset($this->cache['curl'][$path]) )
        {
            $url = $this->baseurl.$path ;
            $cacheRet = $this->cacheGet($cacheKey) ;
            if ( $cacheRet === false )
            {
                $ch = curl_init() ;
                curl_setopt($ch,CURLOPT_URL,$url) ;
                curl_setopt($ch, CURLOPT_USERPWD, $this->username . "/token:" . $this->token) ;
                curl_setopt($ch,CURLOPT_RETURNTRANSFER,1) ;
                $result_raw = curl_exec($ch) ;
                $this->cache['curl'][$path] = json_decode($result_raw,true) ;
                $this->cacheSet($cacheKey,$result_raw) ;
                if ( isset($this->cache['curl'][$path]['error']) )
                {
                    $this->debug($this->cache['curl'][$path]) ;
                    $this->cache['curl'][$path] = false ;
                    return false ;
                }
            }
            else
            {
                $this->cache['curl'][$path] = json_decode($cacheRet,true) ;
            }
            if ( $this->debug ) $this->debug($url) ;
        }
        if ( $ret == 'json' ) return json_encode($this->cache['curl'][$path],JSON_PRETTY_PRINT) ;
        return $this->cache['curl'][$path] ;
    }

    public function cacheSet($cle,$valeur) {
        if ( isset($this->cacheClass) && $this->cacheClass ) return $this->cacheClass->set($cle,$valeur) ;
        elseif ( function_exists('wp_cache_set') ) return wp_cache_set( $cle, $valeur, 'PGZendesk', $this->expire ) ;
        return false ;
    }

    public function cacheGet($cle) {
        if ( isset($this->cacheClass) && $this->cacheClass ) return $this->cacheClass->get($cle) ;
        elseif ( function_exists('wp_cache_get') )return wp_cache_get( $cle, 'PGZendesk' ) ;
        return false ;
    }

    public function debug($array) {
        if ( ! $this->debug ) return false ;
        if ( is_array($array) )
            echo '<script>console.log('.addslashes(json_encode($array)).')</script>' ;
        else
            echo '<script>console.log("'.addslashes($array).'")</script>' ;
    }

    public function showView($cle,$atts,$content) {
        
        $view = $this->getView($cle) ;
        $tickets = $this->getViewTickets($cle) ;

        $classes = Array() ;

        $presentation = ( isset($atts['presentation']) && in_array($atts['presentation'],self::$presentations) ) ? $atts['presentation'] : @array_shift(@array_values(self::$presentations)) ;

        echo '<div id="'.$cle.'" class="'.implode(' ',$classes).'">' ;

            echo '<h2>' ;
                if ( $presentation == 'table' ) echo '<a href="'.$this->baseurl.'agent/filters/'.$view['id'].'" onclick="window.open(this.href);return false ;">' ;
                    echo $view['title'] ;
                if ( $presentation == 'table' ) echo '</a>' ;
            echo '</h2>' ;

            if ( isset($view['description']) && $view['description'] ) echo '<p>'.$view['description'].'</p>' ;
            $wc = @$this->getCurl('/api/v2/views/'.$view['id'].'/count.json')['view_count']['value'] ;
            if ( $wc ) echo '<span>'.$wc.' '.translate('tickets').'</span>' ;

            $customfields = Array() ;

            if ( $presentation == 'table' )
            {
                echo '<table class="wp-list-table widefat fixed striped posts">' ;
                    
                    echo '<thead>' ;
                        echo '<tr>' ;
                        foreach ( $view['execution']['columns'] as $c )
                        {
                            echo '<th>' ;
                                echo $c['title'] ;
                            echo '</th>' ;
                        }
                        echo '</tr>' ;
                    echo '</thead>' ;
                    
                    $id_champ_group = $view['execution']['group']['id'] ;
                    $title_champ_group = $view['execution']['group']['title'] ;
                    $last_group = null ;

                    echo '<tbody>' ;
                        $tickets = $this->getViewTickets($view['id']) ;
                        foreach ( $tickets as $ticket )
                        {
                            $group_value = $this->getTicketValue($ticket,$id_champ_group) ;
                            if ( $last_group != $group_value )
                            {
                                echo '<tr>' ;
                                    echo '<th colspan="'.sizeof($view['execution']['columns']).'">'.$title_champ_group.' : '.$group_value.'</th>' ;
                                echo '</tr>' ;
                            }
                            echo '<tr>' ;
                                foreach ( $view['execution']['columns'] as $c )
                                {
                                    echo '<td>' ;
                                        if ( $c['id'] == 'subject' ) echo '<a href="'.$this->baseurl.'/agent/tickets/'.$ticket['id'].'" onclick="window.open(this.href);return false ;">' ;
                                        echo $this->getTicketValue($ticket,$c['id']) ;
                                        if ( $c['id'] == 'subject' ) echo '</a>' ;
                                    echo '</td>' ;
                                }
                            echo '</tr>' ;
                            $last_group = $group_value ;
                        }
                    echo '</tbody>' ;

                echo '</table>' ;
            }
            elseif ( $presentation == 'liste' )
            {
                $id_champ_group = $view['execution']['group']['id'] ;
                $title_champ_group = $view['execution']['group']['title'] ;
                $last_group = null ;

                $groupes = Array() ;

                    $tickets = $this->getViewTickets($view['id']) ;
                    foreach ( $tickets as $ticket )
                    {
                        $group_value = $this->getTicketValue($ticket,$id_champ_group) ;
                        if ( ! $group_value ) $group_value = 'null' ;
                        if ( ! isset($groupes[$group_value]) )
                        {
                            $groupes[$group_value] = Array('titre'=>$group_value,'tickets'=>Array()) ;
                        }
                        
                        $infos = Array() ;
                        foreach ( $view['execution']['columns'] as $c )
                        {
                            if ( $c['id'] !== 'subject' ) continue ;
                            $info = null ;
                            $info .= $this->getTicketValue($ticket,$c['id']) ;
                            if ( $info != null ) $infos[] = $info ;
                        }
                        if ( sizeof($infos) > 0 ) $groupes[$group_value]['tickets'][] = $infos ;
                        
                        $last_group = $group_value ;
                    }

                    foreach ( $groupes as $k => $groupe )
                    {
                        if ( $groupe['titre'] != 'null' ) echo "\n\t".'<h3>'.$groupe['titre'].'</h3>' ;
                        echo "\n\t".'<ul>' ;
                        foreach ( $groupe['tickets'] as $ticket_infos )
                        {
                            echo "\n\t\t".'<li>'.implode(', ',$ticket_infos).'</li>' ;
                        }
                        echo "\n\t".'</ul>' ;
                    }
            }


            
            
        echo '</div>' ;
    }

    /**
     * Renvoie la clé utilisée sur le ticket selon la clé utilisée sur la vue
     * Ex : "requester" sur la view correspond à "requester_id" sur le ticket
     * 
     */
    public function getTicketKey($cle_champ_view) {
        switch($cle_champ_view) {
            case 'ticket_id' : return 'id' ;
            case 'requester' : 
            case 'assignee' : 
            case 'organisation' : 
            case 'group' : 
            case 'assignee' : return $cle_champ_view.'_id' ;
            case 'created' :
            case 'updated' : return $cle_champ_view.'_at' ;
            default : return $cle_champ_view ;
        } ;
    }

    public function getTicketValue($ticket,$cle_champ_view) {

        $ret = null ;

        $cle_champ_ticket = $this->getTicketKey($cle_champ_view) ; // ticket_id => id, assignee_id => assignee
        if ( is_int($cle_champ_ticket) ) // On est sur un custom field
        {
            $valeur_field = $ticket['fields'][$cle_champ_ticket] ;
            $field = $this->getField($cle_champ_ticket) ;
            if ( $field && isset($field['custom_field_options_by_value'][$valeur_field]) )
            {
                $ret .= $field['custom_field_options_by_value'][$valeur_field]['name'] ;
            }
        }
        elseif ( isset($ticket[$cle_champ_ticket]) )
        {
            if ( in_array($cle_champ_ticket,self::$champsDate) ) $ret .= $this->showDate($ticket[$cle_champ_ticket]) ;
            elseif ( in_array($cle_champ_ticket,self::$champsUser) ) $ret .= $this->showUser($ticket[$cle_champ_ticket]) ;
            else $ret .= $ticket[$cle_champ_ticket] ;
        }

        return $ret ;
    }

    public function showDate($d) {
        $d = new DateTime($d) ;
        return $d->format(self::$dateFormat) ;
    }

    public function showUser($id) {
        $u = $this->getUser($id) ;
        if ( $u ) return $u['name'] ;
        else return $id ;
    }

}