<?php

add_action( 'wp_enqueue_scripts', 'cline_child_enqueue_styles' );
function cline_child_enqueue_styles() {
    wp_enqueue_style('main-style', get_template_directory_uri() . '/style.css');
}
?>