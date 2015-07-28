<?php
/*
  Plugin Name: LessonSee 
  Plugin URI: https://github.com/zozoh94/LessonSee
  Description: Un plugin permettant de connaitre si les utilisateurs ont vu les lessons en entier
  Version: 0.1
  Author: Enzo Hamelin
  Author URI: http://www.zozoh.fr
  License: GPL3
*/
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'LessonSee' ) ) :
class LessonSee
{
    // class instance
    static $instance;
 
    // customer WP_List_Table object
    public $lessonsee_obj;
    
    public function __construct()
    {
        // Auto-load classes on demand
        if ( function_exists( "__autoload" ) ) {
            spl_autoload_register( "__autoload" );
        }
        
        include_once('lessonseetable.php');
        
        //insert couple lesson/user
        add_action( 'the_post', array(__CLASS__,'insert_user_lesson') );
        //update see lesson
        add_action( 'wp_ajax_see', array(__CLASS__,'see_lesson') );
        //administration interface
        add_filter( 'set-screen-option', array(__CLASS__, 'set_screen' ), 10, 3 );
        add_action( 'admin_menu', array($this, 'add_admin_menu'));
        add_action( 'wp_loaded', array( $this, 'callback_export'));
    }

    public static function install()
    {
        global $wpdb;
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lesson_see (id INT AUTO_INCREMENT PRIMARY KEY, id_lesson INT NOT NULL, id_user INT NOT NULL, see TINYINT(1) NOT NULL, date DATETIME NOT NULL);");
    }

    public static function uninstall()
    {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}lesson_see;");
    }

    public function insert_user_lesson($post_object) {
        global $wpdb;
	global $wp_query;
        if($wp_query->is_single() && $post_object->post_type == "lesson") {
            $user = wp_get_current_user();
            if($user) {
                $row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}lesson_see WHERE id_lesson = '$post_object->ID' AND id_user = '$user->ID'");
                if (is_null($row)) {
                    $wpdb->insert("{$wpdb->prefix}lesson_see", array('id_lesson' => $post_object->ID, 'id_user' => $user->ID, 'see' => false, 'date' => current_time('mysql')));
                }
            }
        }
    }

    public static function see_lesson()
    {
        global $wpdb;
        header("Access-Control-Allow-Origin: *");
        header("Content-Type: application/json; charset=UTF-8");
        $user = wp_get_current_user();
        if(isset($_POST['lesson'])) {
            $post = get_post($_POST['lesson']);
            if($post && $post->post_type == 'lesson') {
                if($user) {
                    $row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}lesson_see WHERE id_lesson = '$post_object->ID' AND id_user = '$user->ID'");
                    if (is_null($row)) {
                        if($row->see == false) {
                            if($wpdb->update("{$wpdb->prefix}lesson_see", array('see' => true, 'date' => current_time('mysql')), array('id_lesson' => $post->ID, 'id_user' => $user->ID)))   
                                $array = array('success' => true, 'message' => 'La leçon a été prise.');
                            else
                                $array = array('success' => false, 'message' => 'Problème sur la bdd.');
                        } else
                            $array = array('success' => true, 'message' => 'Leçon déjà prise.');
                    } else
                        $array = array('success' => false, 'message' => 'Problème de la base de donnée.');
                } else
                    $array = array('success' => false, 'message' => 'Utilisateur non connecté ou leçon non spécifié.');
            } else
                $array = array('success' => false, 'message' => 'La lesson n\'existe pas ou le type est incorrect.');
        } else {
            $array = array('success' => false, 'message' => 'Id de la lesson non spécifié.');
        }
        echo json_encode($array);
        wp_die();
    }

    public static function set_screen( $status, $option, $value ) {
        return $value;
    }
    
    public function add_admin_menu()
    {
        $hook = add_menu_page('Statistiques sur le visionnage des leçons', 'Lesson See', 'manage_options', 'LessonSeeTable', array($this, 'menu_html'));
        add_action( "load-".$hook, array($this, 'screen_option' ) );
    }

    public function menu_html()
    {
        $this->lessonsee_obj->prepare_items();
        ?>
        <div class="wrap">
            <h2>LessonSee</h2>
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <div class="meta-box-sortables ui-sortable">
                            <form method="post">
                                <?php
                                $this->lessonsee_obj->display();
                                ?>
                            </form>
			</div>
		    </div>
		</div>
		<br class="clear">
            </div>
        </div>
        <?php
    }

    public function screen_option()
    {
        $option = 'per_page';
        $args   = [
            'label'   => 'LessonSee',
            'default' => 10,
            'option'  => 'lessonsee_per_page'
        ];

        add_screen_option( $option, $args );
 
        $this->lessonsee_obj = new LessonSeeTable();
    }

    public static function get_instance()
    {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
 
        return self::$instance;
    }

    public function callback_export() {
        if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-export' )
        || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-export' )) {
            include_once(plugin_dir_path( __FILE__ ) . '../../../wp-admin/includes/plugin.php');
            header("Content-type: text/csv; charset=utf-8");
            header("Content-Disposition: attachment; filename=export.csv");
            header("Pragma: no-cache");
            header("Expires: 0");
            $export_ids = esc_sql( $_POST['lessonsee'] );
            if(!empty($export_ids)) {
                echo self::generate_csv($export_ids);
            }
            exit;
        }
    }

    public static function generate_csv($ids) {
        global $wpdb;
        
        $sql = "SELECT DISTINCT {$wpdb->prefix}lesson_see.id, {$wpdb->prefix}lesson_see.id_lesson, {$wpdb->prefix}posts.post_title, {$wpdb->prefix}lesson_see.id_user, {$wpdb->prefix}users.display_name, {$wpdb->prefix}lesson_see.see, {$wpdb->prefix}lesson_see.date";
        if(is_plugin_active('groups/groups.php'))
            $sql .= ", {$wpdb->prefix}groups_group.name AS group_name";
        $sql .= " FROM {$wpdb->prefix}lesson_see INNER JOIN {$wpdb->prefix}posts ON {$wpdb->prefix}lesson_see.id_lesson = {$wpdb->prefix}posts.ID INNER JOIN {$wpdb->prefix}users ON {$wpdb->prefix}users.ID = {$wpdb->prefix}lesson_see.id_user";
        if(is_plugin_active('groups/groups.php'))
            $sql .= " INNER JOIN {$wpdb->prefix}groups_user_group ON {$wpdb->prefix}groups_user_group.user_id = {$wpdb->prefix}lesson_see.id_user INNER JOIN {$wpdb->prefix}groups_group ON {$wpdb->prefix}groups_user_group.group_id = {$wpdb->prefix}groups_group.group_id WHERE {$wpdb->prefix}groups_group.name NOT LIKE 'Registered'";
        $sql .= " AND {$wpdb->prefix}lesson_see.id IN (" . implode(',', array_map('intval', $ids)) . ")";
        $sql .= " ORDER BY {$wpdb->prefix}lesson_see.date ASC";
        $result = $wpdb->get_results( $sql, 'ARRAY_A' );

        $handle = fopen('php://temp', 'r+');
        $head = array('id', 'id de la leçon', 'titre de la leçon', 'id de l\'utilisateur', 'nom de l\'utilisateur', 'vu', 'date');
        if(is_plugin_active('groups/groups.php'))
            $head[] = 'groupe';
        fputcsv($handle, $head, ',', '"');
        foreach ($result as $line) {
            $new_line = array();
            foreach ($line as $key => $value) {
                if($key == 'see' && $value == true)
                    $new_line[$key] = "oui";
                elseif($key == 'see' && $value == false)
                    $new_line[$key] = "non";
                else
                    $new_line[$key] = $value;
            }
            fputcsv($handle, $new_line, ',', '"');
        }
        rewind($handle);
        while (!feof($handle)) {
            $contents .= fread($handle, 8192);
        }
        fclose($handle);
        
        return $contents;
    }
}
add_action( 'plugins_loaded', function () {
    LessonSee::get_instance();
} );
endif;

register_activation_hook(__FILE__, array('LessonSee', 'install'));
register_uninstall_hook(__FILE__, array('LessonSee', 'uninstall'));
?>
