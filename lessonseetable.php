<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class LessonSeeTable extends WP_List_Table
{
    public function __construct()
    {
        global $status, $page;
        
        parent::__construct( array(
            'singular'=> 'LessonSee',
            'plural' => 'LessonSee',
            'ajax'   => false
        ) );

        add_action('admin_head', array( &$this, 'admin_header' ));
    }

    function admin_header() {
        echo '<style type="text/css">';
        echo '.wp-list-table .column-see { width: 12%; }';
        echo '.wp-list-table .column-date { width: 15%; }';
        echo '</style>';
    }

    function extra_tablenav( $which ) {
        if ( 'top' == $which )
            echo
                "<br class=\"clear\" /><input type=\"hidden\" name=\"page\" value=\"".$_REQUEST['page']."\" />"
                .$this->search_box('Rechercher sur le nom ou l\'id du groupe', 'search_group');
    }

    public static function get_lessonsee( $per_page = 10, $page_number = 1 )
    {
        global $wpdb;

        $sql = "SELECT DISTINCT {$wpdb->prefix}lesson_see.id, {$wpdb->prefix}lesson_see.id_lesson, {$wpdb->prefix}posts.post_title, {$wpdb->prefix}lesson_see.id_user, {$wpdb->prefix}users.display_name, {$wpdb->prefix}lesson_see.see, {$wpdb->prefix}lesson_see.date";
        if(is_plugin_active('groups/groups.php'))
            $sql .= ", {$wpdb->prefix}groups_group.name AS group_name, {$wpdb->prefix}groups_group.group_id AS id_group";
        $sql .= " FROM {$wpdb->prefix}lesson_see INNER JOIN {$wpdb->prefix}posts ON {$wpdb->prefix}lesson_see.id_lesson = {$wpdb->prefix}posts.ID INNER JOIN {$wpdb->prefix}users ON {$wpdb->prefix}users.ID = {$wpdb->prefix}lesson_see.id_user";
        if(is_plugin_active('groups/groups.php'))
            $sql .= " INNER JOIN {$wpdb->prefix}groups_user_group ON {$wpdb->prefix}groups_user_group.user_id = {$wpdb->prefix}lesson_see.id_user INNER JOIN {$wpdb->prefix}groups_group ON {$wpdb->prefix}groups_user_group.group_id = {$wpdb->prefix}groups_group.group_id WHERE {$wpdb->prefix}groups_group.name NOT LIKE 'Registered'";        
        if (is_plugin_active('groups/groups.php') && ! empty( $_REQUEST['id_group'] ) ) {
            $sql .= " AND {$wpdb->prefix}groups_group.group_id = ".intval($_REQUEST['id_group']);
        }
        elseif ( ! empty( $_REQUEST['s'] ) ) {
            $sql .= " AND ({$wpdb->prefix}groups_group.name LIKE '%".$_REQUEST['s']."%' OR {$wpdb->prefix}groups_group.group_id = ".intval($_REQUEST['s']).")";
        }
        if ( ! empty( $_REQUEST['orderby'] ) ) {
            $sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
            $sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
        }
        $sql .= " LIMIT $per_page";
        $sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;
 
        $result = $wpdb->get_results( $sql, 'ARRAY_A' );
 
        return $result;
    }

    public static function delete_lessonsee( $id )
    {
        global $wpdb;
 
        $wpdb->delete(
            "{$wpdb->prefix}lesson_see",
            [ 'id' => $id ],
            [ '%d' ]
        );
    }

    public static function see_lessonsee( $id )
    {
        global $wpdb;

        $wpdb->update(
            "{$wpdb->prefix}lesson_see",
            [ 'see' => true, 'date' => current_time('mysql') ],
            [ 'id' => $id ],
            [ '%d' ]
        );
    }

    public static function nsee_lessonsee( $id )
    {
        global $wpdb;

        $wpdb->update(
            "{$wpdb->prefix}lesson_see",
            [ 'see' => false ],
            [ 'id' => $id ],
            [ '%d' ]
        );
    }

    public static function record_count()
    {
        global $wpdb;
 
        $sql = "SELECT DISTINCT COUNT(*) FROM {$wpdb->prefix}lesson_see";
        if(is_plugin_active('groups/groups.php'))
            $sql .= " INNER JOIN {$wpdb->prefix}groups_user_group ON {$wpdb->prefix}groups_user_group.user_id = {$wpdb->prefix}lesson_see.id_user INNER JOIN {$wpdb->prefix}groups_group ON {$wpdb->prefix}groups_user_group.group_id = {$wpdb->prefix}groups_group.group_id WHERE {$wpdb->prefix}groups_group.name NOT LIKE 'Registered'";
 
        return $wpdb->get_var( $sql );
    }

    public function column_default( $item, $column_name )
    {
        switch ( $column_name ) {
        case 'post_title':
        case 'display_name':
        case 'see' :
        case 'id':
        case 'id_user':
        case 'id_lesson':
        case 'date':
        case 'group_name':
        case 'id_group':
            return $item[ $column_name ];
        default:
            return print_r( $item, true ); //Show the whole array for troubleshooting purposes
        }
    }

    function column_cb( $item )
    {
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%3$s"/>',
            /*$1%s*/ $this->_args['singular'],  //Let's simply repurpose the table's singular label ("video")
            /*$1%s*/ $this->_args['singular'],  //Let's simply repurpose the table's singular label ("video")
            /*$2%s*/ $item['id']             //The value of the checkbox should be the record's id
        );
    }

    function column_see( $item )
    {
        $see_nonce = wp_create_nonce( 'sp_see_lessonsee' );
        $nsee_nonce = wp_create_nonce( 'sp_nsee_lessonsee' );
        $actions = array(
            'see'      => sprintf('<a href="?page=%s&action=%s&lessonsee=%s&_wpnonce=%s">Marquer comme vu</a>',esc_attr( $_REQUEST['page'] ),'see',$item['id'], $see_nonce),
            'nsee'     => sprintf('<a href="?page=%s&action=%s&lessonsee=%s&_wpnonce=%s">Marquer comme non vu</a>', esc_attr( $_REQUEST['page'] ),'nsee',$item['id'], $nsee_nonce)
        );
          
        if($item['see'] == 1)
            $string = 'Oui';
        else
            $string = 'Non';

        return $string . $this->row_actions($actions);
    }

    function column_display_name( $item )
    {
        return "<a href=\"".get_edit_user_link( $item['id_user'])."\">". $item['display_name'] . "</a>";
    }

    function column_post_title( $item )
    {
        return "<a href=\"".get_permalink( $item['id_lesson'])."\">". $item['post_title'] . "</a>";
    }

    function column_group_name( $item )
    {
        return "<a href=\"?page=".esc_attr( $_REQUEST['page'] )."&id_group=".$item['id_group']."\" >".$item['group_name']."</a>";
    }
    
    function get_columns() {
        $columns = [
            'cb'      => '<input type="checkbox" />',
            'display_name'    => 'Utilisateur',
            'post_title' => 'Leçon',
            'date' => 'Date',
        ];
        if(is_plugin_active('groups/groups.php'))
            $columns['group_name'] = 'Nom du groupe';
        $columns['see'] = 'Vu';
        return $columns;
    }

    public function get_sortable_columns()
    {
        $sortable_columns = array(
            'display_name' => array( 'diplay_name', false ),
            'post_title' => array( 'post_title', false ),
            'date' => array( 'date', true),
        );

        if(is_plugin_active('groups/groups.php'))
            $sortable_columns['group_name'] = array( 'group_name', false);
 
        return $sortable_columns;
    }

    public function get_bulk_actions()
    {
        $actions = [
            'bulk-delete' => 'Supprimer',
            'bulk-see' => 'Marquer comme vu',
            'bulk-nsee' => 'Marquer comme non vu',
            'bulk-export' => 'Exporter en CSV',
        ];
 
        return $actions;
    }

    public function prepare_items()
    {
        $this->_column_headers = $this->get_column_info();
        
        /** Process bulk action */
        $this->process_bulk_action();

        $per_page     = $this->get_items_per_page( 'lessonsee_per_page', 10 );
        $current_page = $this->get_pagenum();
        $total_items  = self::record_count();
        $hidden = array('id', 'id_user', 'id_lesson', 'id_group');

        $this->set_pagination_args( [
            'total_items' => $total_items, //WE have to calculate the total number of items
            'per_page'    => $per_page //WE have to determine how many items to show on a page
        ] );

        $this->items = self::get_lessonsee( $per_page, $current_page );
    }

    public function process_bulk_action() {
        //Detect when a bulk action is being triggered...
        if ( 'delete' === $this->current_action() ) {
            // In our file that handles the request, verify the nonce.
            $nonce = esc_attr( $_REQUEST['_wpnonce'] );
            if ( ! wp_verify_nonce( $nonce, 'sp_delete_lessonsee' ) ) {
                die( 'Go get a life script kiddies' );
            }
            else {
                self::delete_lessonsee( absint( $_GET['lessonsee'] ) );
                echo'<script> window.location="'.esc_url( add_query_arg() ).'"; </script> ';
                exit;
            }
        }
        if ( 'see' === $this->current_action() ) {
            // In our file that handles the request, verify the nonce.
            $nonce = esc_attr( $_REQUEST['_wpnonce'] );
            if ( ! wp_verify_nonce( $nonce, 'sp_see_lessonsee' ) ) {
                die( 'Go get a life script kiddies' );
            }
            else {
                self::see_lessonsee( absint( $_GET['lessonsee'] ) );
                echo'<script> window.location="'.esc_url( add_query_arg() ).'"; </script> ';
                exit;
            }
        }
        if ( 'nsee' === $this->current_action() ) {
            // In our file that handles the request, verify the nonce.
            $nonce = esc_attr( $_REQUEST['_wpnonce'] );
            if ( ! wp_verify_nonce( $nonce, 'sp_nsee_lessonsee' ) ) {
                die( 'Go get a life script kiddies' );
            }
            else {
                self::nsee_lessonsee( absint( $_GET['lessonsee'] ) );
                echo'<script> window.location="'.esc_url( add_query_arg() ).'"; </script> ';
                exit;
            }
        }
        
        // If the delete bulk action is triggered
        if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-delete' )
        || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-delete' )
        ) {

            $delete_ids = esc_sql($_REQUEST['lessonsee']);
            if(!empty($delete_ids)) {
                // loop over the array of record IDs and delete them
                foreach ( $delete_ids as $id ) {
                    self::delete_lessonsee( $id ); 
                }
            }
            //echo'<script> window.location="'.esc_url( add_query_arg() ).'"; </script> ';
            exit;            
        }
        if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-see' )
        || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-see' )
        ) {
 
            $see_ids = esc_sql( $_POST['lessonsee'] );
            if(!empty($see_ids)) {
                // loop over the array of record IDs and delete them
                foreach ( $see_ids as $id ) {
                    self::see_lessonsee( $id ); 
                }
            }
            echo'<script> window.location="'.esc_url( add_query_arg() ).'"; </script> ';
            exit;
        }
        if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-nsee' )
        || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-nsee' )
        ) {
 
            $nsee_ids = esc_sql( $_POST['lessonsee'] );
            if(!empty($nsee_ids)) {
                // loop over the array of record IDs and delete them
                foreach ( $nsee_ids as $id ) {
                    self::nsee_lessonsee( $id ); 
                }
            }
            echo'<script> window.location="'.esc_url( add_query_arg() ).'"; </script> ';
            exit;
        }
    }
}