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
class LessonSee
{
    public function __construct()
    {
        register_activation_hook(__FILE__, array('LessonSee', 'install'));
        register_uninstall_hook(__FILE__, array('Zero_Newsletter', 'uninstall'));
        
        //insert couple lesson/user
        add_action( 'the_post', array(__CLASS__,'insert_user_lesson') );
        //update see lesson
        add_action( 'wp_ajax_see', array(__CLASS__,'see_lesson') );
    }

    public static function install()
    {
        global $wpdb;

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lesson_see (id INT AUTO_INCREMENT PRIMARY KEY, id_lesson INT NOT NULL, id_user INT NOT NULL, see TINYINT(1) NOT NULL);");
    }

    public static function uninstall()
    {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}lesson_see;");
    }

    public function insert_user_lesson($post_object) {
        global $wpdb;
        if($post_object->post_type == "lesson") {
            $user = wp_get_current_user();
            if($user) {
                $row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}lesson_see WHERE id_lesson = '$post_object->ID' AND id_user = '$user->ID'");
                if (is_null($row)) {
                    $wpdb->insert("{$wpdb->prefix}lesson_see", array('id_lesson' => $post_object->ID, 'id_user' => $user->ID, 'see' => false));
                }
            }
        }
    }

    public static function see_lesson() {
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
                            $wpdb->update("{$wpdb->prefix}lesson_see", array('see' => true), array('id_lesson' => $post->ID, 'id_user' => $user->ID));                    
                            $array = array('success' => true, 'message' => 'La leçon a été prise.');
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

}
new LessonSee();