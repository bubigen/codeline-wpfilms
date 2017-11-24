<?php
/*
Plugin Name: CodeLine Films
Description: This is a CPT plugin to demonstrate WP basic skills
Author: Boobalan K
Version: 1.0
*/

if(!class_exists('ClineFilms')) {
	/**
	*  Class responsible for making films plugin
	*/
	class ClineFilms
	{
		
		protected $taxonomies;
		protected $metaboxes;

		/**
		*  Initiate and register plugin, shortcodes
		*/
		function __construct()
		{
			$this->setTaxonomies();
			$this->setCustomMetaBoxes();

			// register custom post types
			add_action('init', array($this, 'registerPostType'));

			// register taxonomies
			add_action('init', array($this, 'registerTaxonomies'));

			// register, process and save meta boxes
			add_action('add_meta_boxes', array($this, 'registerCustomMetaBox'));
			add_action('save_post', array($this, 'saveCustomMeta'), 1, 2 );

			// add resources
			add_action('admin_enqueue_scripts', array($this, 'registerResources'));
			add_action('wp_head', array($this, 'registerSiteResources'));

			// hook the content
			add_filter('the_content', array($this, 'printContentMetaData'), 20);

			// register shortcode
			add_shortcode('cline_films_list', array($this, 'filmListWidget'));
			add_filter('widget_text', 'do_shortcode');
		}

		/**
		*  Register custom post type: films
		*/
		function registerPostType()
		{
			register_post_type('film',
				// CPT Options
				array(
					'labels' => array(
						'name' => __( 'Films' ),
						'singular_name' => __( 'Film' )
					),
					'supports' => array( 'title', 'editor', 'excerpt', 'custom-fields', 'thumbnail','page-attributes' ),
					'taxonomies' => array( 'genre' ),	
					'exclude_from_search' => false,
					'capability_type' => 'post',
					'public' => true,
					'has_archive' => true,
					'show_ui'             => true,
					'show_in_menu'        => true,
					'show_in_nav_menus'   => true,
					'show_in_admin_bar'   => true,
					'menu_position'       => 15,
					'can_export'          => true,
					'has_archive'         => true,
					'rewrite' => array('slug' => 'films'),
					'menu_icon'			  => 'dashicons-video-alt2'
				)
			);
		}

		/**
		*  Register custom post type: films
		*/
		function registerTaxonomies() 
		{
			foreach ($this->taxonomies as $taxonomy) {
				register_taxonomy(
					$taxonomy,
					'film',
					array(
						'label'				=> __(ucfirst($taxonomy)),
						'rewrite' 			=> array( 'slug' => $taxonomy ),
						'show_admin_column' => true,
						'query_var'         => true,
						'show_ui'           => true,
						'hierarchical' 		=> true,
					)
				);
			}
		}

		/**
		*  Register custom meta boxes
		*/
		function registerCustomMetaBox() 
		{
			foreach ($this->metaboxes as $metabox) {
				add_meta_box(
					$metabox['id'],
					$metabox['title'],
					array($this, $metabox['cb']),
					'film',
					'side',
					'default'
				);
			}
		}

		/**
		*  Register resources for admin
		*/
		function registerResources()
		{
			wp_enqueue_style('jquery-ui-datepicker', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.2/themes/smoothness/jquery-ui.css');
			wp_enqueue_script(
				'clinefilms-script', 
				plugins_url('js/script.js', __FILE__ ), 
				array('jquery', 'jquery-ui-core', 'jquery-ui-datepicker'),
				time(),
				true
			);
		}

		/**
		*  Register resources for frontend
		*/
		function registerSiteResources()
		{
			wp_enqueue_style('clinefilms-styles', plugins_url('css/style.css', __FILE__ ));
		}

		/**
		*  Set the taxonomies to be registered
		*/
		function setTaxonomies()
		{
			$this->taxonomies = ['genre', 'country', 'year', 'actors'];
		}

		/**
		*  Set the custom meta boxes
		*/
		function setCustomMetaBoxes()
		{
			$this->metaboxes = [
				['id' => 'ticket_price', 'title' => 'Ticket Price', 'cb' => 'setInputTicketPrice'],
				['id' => 'release_date', 'title' => 'Release Date', 'cb' => 'setInputReleaseDate']
			];
		}

		/**
		*  Custom meta input
		*/
		function setInputTicketPrice()
		{
			global $post;
			wp_nonce_field( basename( __FILE__ ), 'film_meta' );
			$ticket_price = get_post_meta( $post->ID, 'ticket_price', true );
			echo '<input type="text" name="ticket_price" value="' . esc_textarea( $ticket_price )  . '" class="widefat">';
		}

		/**
		*  Custom meta input
		*/
		function setInputReleaseDate()
		{
			global $post;
			wp_nonce_field( basename( __FILE__ ), 'film_meta' );
			$release_date = get_post_meta( $post->ID, 'release_date', true );
			echo '<input type="text" name="release_date" value="' . esc_textarea( $release_date )  . '" class="widefat">';
		}

		/**
		 * Save the metabox data
		 */
		function saveCustomMeta( $post_id, $post ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return $post_id;
			}

			if ( ! isset( $_POST['ticket_price'] ) && !isset( $_POST['release_date'] ) || ! wp_verify_nonce( $_POST['film_meta'], basename(__FILE__) ) ) {
				return $post_id;
			}

			$film_meta['ticket_price'] = esc_textarea( $_POST['ticket_price'] );
			$film_meta['release_date'] = esc_textarea( $_POST['release_date'] );

			foreach ( $film_meta as $key => $value ) {
				if ( 'revision' === $post->post_type ) {
					return;
				}
				if ( get_post_meta( $post_id, $key, false ) ) {
					update_post_meta( $post_id, $key, $value );
				} else {
					add_post_meta( $post_id, $key, $value);
				}
				if ( ! $value ) {
					delete_post_meta( $post_id, $key );
				}
			}
		}

		/* FRONT END */
		/**
		* Return meta data based on page type
		* @param $content string
		* @return $content string
		*/
		function printContentMetaData($content)
		{
			if(is_single()) {
				$content .= $this->contentMetaDataForSingle();
			}
			else if(is_archive()) {
				$content .= $this->contentMetaDataForArchive();
			}
			return $content;
		}

		/**
		* Return single page data
		* @return $content string
		*/
		function contentMetaDataForSingle() 
		{
			$data = $this->getCommonData();
			
			$html = '<dl class="dl-horizontal">'."\n";
			$html .= '<dt>Ticket Price</dt>'."\n";
			$html .= '<dd>'.$data['ticket_price'].'</dd>'."\n";

			$html .= '<dt>Release Date</dt>'."\n";
			$html .= '<dd>'.$data['release_date'].'</dd>'."\n";

			$html .= '<dt>Genre</dt>'."\n";
			$html .= '<dd>'.implode(', ', $data['genres']).'</dd>'."\n";

			$html .= '<dt>Country</dt>'."\n";
			$html .= '<dd>'.implode(', ', $data['countries']).'</dd>'."\n";

			return $html;
		}

		/**
		* Return archive data
		* @return $content string
		*/
		function contentMetaDataForArchive() 
		{
			$data = $this->getCommonData();
			
			$html = '<div class="extra-data">';
			$html .= '<span class="label label-primary">Price: '.$data['ticket_price'].'</span>'."\n";
			$html .= '<span class="label label-warning">Release: '.$data['release_date'].'</span>'."\n";
			$html .= '<span class="label label-info">Genre: '.implode(', ', $data['genres']).'</span>'."\n";
			$html .= '<span class="label label-default">Country: '.implode(', ', $data['countries']).'</span>'."\n";
			$html .= '</div>';

			return $html;
		}

		/**
		* Print html output for shortcode
		* @param $atts array
		* @return $html string
		*/
		function filmListWidget($atts)
		{
			extract(shortcode_atts(array(
					'show' 		=> 5,
					'orderby' 	=> 'id',
					'order'		=> 'desc'
			), $atts));

			$args = array( 'post_type' => 'film', 'posts_per_page' => $show, 'orderby' => $orderby, 'order' => $order );
			$loop = new WP_Query( $args );
			$html = '<dl class="cline-film-widget">'."\n";
			while ( $loop->have_posts() ) : $loop->the_post();
				$data = $this->getCommonData();
				$html .= '<dt><a href="'.get_the_permalink().'">'.get_the_title().'</a></dt>'."\n";
				$html .= '<dd>';
				$html .= '<span class="label label-default">'.$data['ticket_price'].'</span>'."\n";
				$html .= '<span class="label label-default">'.$data['release_date'].'</span>'."\n";
				$html .= '<span class="label label-default">'.implode(', ', $data['genres']).'</span>'."\n";
				$html .= '<span class="label label-default">'.implode(', ', $data['countries']).'</span>'."\n";
				$html .= '</dd>'."\n";

			endwhile;
			$html .= '</dl>'."\n";

			return $html;
		}

		/**
		* Return a common meta data
		* @return $data array
		*/
		function getCommonData()
		{
			global $post;
			$data['ticket_price'] = get_post_meta($post->ID, 'ticket_price', true);
			$data['release_date'] = get_post_meta($post->ID, 'release_date', true);
			$data['genres'] = wp_get_post_terms($post->ID, 'genre', array("fields" => "names"));
			$data['countries'] = wp_get_post_terms($post->ID, 'country', array("fields" => "names"));
			return $data;
		}
	}

	// light up!
	$codelineFilms = new ClineFilms();
}

?>
