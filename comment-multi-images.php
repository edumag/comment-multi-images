<?php
/*
Plugin Name: Comment Multi Images
Plugin URI: http://mamedu.com/
Description: Allow your readers easily to attach multi images to their comment.
Version: 1.0
Author: Eduardo Magrané
Author URI: http://mamedu.com
Author Email: eduardo@mamedu.com
License:

  Copyright 2012 - 2013 Eduardo Magrané (eduardo@mamedu.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as 
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*
 * Include dependencies necessary for adding Comment Multi Images to the Media Uplower
 *
 * See also:	http://codex.wordpress.org/Function_Reference/media_sideload_image
 * @since		1.8
 */
require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');

/**The URL of the plugin directory*/
define('COMMENTSMULTIIMAGES_DIR_URL',plugin_dir_url(__FILE__));

/** Nombre del directorio donde guardar las imágenes dentro de upload */
define('COMMENTSMULTIIMAGES_DIR_IMG','comments_imgs');

/**
 * Imágenes para comentarios
 */

class Comment_Multi_Image {

   /** 
    * Array con los tipos de post con comment-multi-images activado
    *
    * Si no se define estara activado en todos los tipos de post
    */

   private $type_post = '';

   /**
    * Limite de imágenes
    */

   private $limit_images = 5;

	/*--------------------------------------------*
	 * Constructor
	 *--------------------------------------------*/
	
	/**
	 * Initializes the plugin by setting localization, admin styles, and content filters.
	 */
	function __construct() {

      // Recogemos configuración
      if( false !== get_option( 'comment_mi_maximum_files' ) || null !== get_option( 'comment_mi_maximum_files' ) ) {
         $this->limit_images = get_option( 'comment_mi_maximum_files' );
         }
      if( false !== get_option( 'comment_mi_type_post' ) || null !== get_option( 'comment_mi_type_post' ) ) {
         $this->type_post = explode(',',get_option( 'comment_mi_type_post' ));
         }


		// Load plugin textdomain
		add_action( 'init', array( $this, 'plugin_textdomain' ) );
	
		// Determine if the hosting environment can save files.
		if( $this->can_save_files() ) {
	
			// We need to update all of the comments thus far
			if( false == get_option( 'update_comment_multi_image' ) || null == get_option( 'update_comment_multi_image' ) ) {
				$this->update_old_comments();
			} // end if
	
			// Add comment related stylesheets and Java Script
			add_action( 'wp_enqueue_scripts', array( $this, 'add_styles' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );
			
			// Add the Upload input to the comment form
			add_action( 'comment_form_field_comment' , array( $this, 'add_image_upload_form' ),10 );
         // add_filter('comment_form_default_fields', array( $this, 'add_image_upload_form' ));
			add_filter( 'wp_insert_comment', array( $this, 'save_comment_multi_image' ) );
			add_filter( 'comments_array', array( $this, 'display_comment_multi_image' ) );
			
			// Add a note to recent comments that they have Comment Multi Images
			add_filter( 'comment_row_actions', array( $this, 'recent_comment_has_image' ), 20, 2 );
			
			// Add a column to the Post editor indicating if there are Comment Multi Images
			add_filter( 'manage_posts_columns', array( $this, 'post_has_comment_multi_image' ) );
			add_filter( 'manage_posts_custom_column', array( $this, 'post_comment_multi_image' ), 20, 2 );
			
			// Add a column to the comment images if there is an image for the given comment
			add_filter( 'manage_edit-comments_columns', array( $this, 'comment_has_image' ) );
			add_filter( 'manage_comments_custom_column', array( $this, 'comment_multi_image' ), 20, 2 );
			
			// Setup the Project Completion metabox
			add_action( 'add_meta_boxes', array( $this, 'add_comment_multi_image_meta_box' ) );
			add_action( 'save_post', array( $this, 'save_comment_multi_image_display' ) );
			
         // Añadir posibilidad de eliminar imágenes al editar comentario
         add_action( 'add_meta_boxes', array($this,'myplugin_add_custom_box') );

		// If not, display a notice.	
		} else {
		
			add_action( 'admin_notices', array( $this, 'save_error_notice' ) );
			
		} // end if/else

      /////////////////////////////////
      // Acciones para jquery-upload //
      /////////////////////////////////

      /* Add the resources */
      add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts') );

      /* Load the inline script */
      add_action( 'wp_footer', array( $this,'add_inline_script')  );

      /* Hook on ajax call */
      add_action('wp_ajax_load_ajax_function', array( $this,'load_ajax_function') );
      add_action('wp_ajax_nopriv_load_ajax_function', array( $this,'load_ajax_function') );

	} // end constructor
	
	/*--------------------------------------------*
	 * Core Functions
	 *---------------------------------------------*/

   /**
    * Caja para la edición de imágenes
    */

   function myplugin_add_custom_box() {
       add_meta_box(
           'comment-multi-images-edit',
           __( 'Comment Multi Images', 'comment-multi-images' ),
           array($this,'edit_images'),
           'comment',
           'normal'
       );
   }

   /**
    * Editando imágenes
    */

   function edit_images(){

      $comment_ID = $_REQUEST['c'];

      if( true == get_comment_meta( $comment_ID, 'comment_multi_image' ) ) {
   
         $comment_multi_image = get_comment_meta( $comment_ID, 'comment_multi_image', false );

         $lista_imagenes = FALSE ;
         foreach ( $comment_multi_image as $contenido ) {
         
            if ( isset($contenido['post_id']) && (! empty($contenido['post_id']) ) ) 
               //$lista_imagenes .= '<img style="width: 200px; margin: 10px" src="'.$contenido['url'].'"/>';
               $lista_imagenes .= '<a href="'.get_bloginfo('wpurl').'/wp-admin/post.php?post='.$contenido['post_id'].'&action=edit" ><img style="width: 200px; margin: 10px" src="'.$contenido['url'].'"/></a>';
            }
         
         if ( $lista_imagenes ) {
            echo $lista_imagenes;
            }

      } // end if
      
      return;

      }

	 /**
	  * Adds a column to the 'All Posts' page indicating whether or not there are 
	  * Comment Multi Images available for this post.
	  *
	  * @param	array	$cols	The columns displayed on the page.
	  * @param	array	$cols	The updated array of columns.
	  * @since	1.8
	  */
	 public function post_has_comment_multi_image( $cols ) {
	 
		 $cols['comment-multi-images'] = __( 'Comment Multi Images', 'comment-multi-images' );
		 
		 return $cols;
		 
	 } // end post_has_comment_multi_image
	 
	 /**
	  * Provides a link to the specified post's comments page if the post has comments that contain
	  * images.
	  *
	  * @param	string	$column_name	The name of the column being rendered.
	  * @param	int		$int			The ID of the post being rendered.
	  * @since	1.8
	  */
	 public function post_comment_multi_image( $column_name, $post_id ) {

		 if( 'comment-multi-images' == strtolower( $column_name ) ) {
		 
		 	// Get the comments for the current post.
		 	$args = array( 
		 		'post_id' => $post_id
		 	);
		 	$comments = get_comments( $args );
		 	
		 	// Look at each of the comments to determine if there's at least one comment image
		 	$has_comment_multi_image = false;
		 	foreach( $comments as $comment ) {
			 	
			 	// If the comment meta indicates there's a comment image and we've not yet indicated that it does...
			 	if( 0 != get_comment_meta( $comment->comment_ID, 'comment_multi_image', true ) && ! $has_comment_multi_image ) {
			 		
			 		// ..Make a note in the column and link them to the media for that post
					$html = '<a href="edit-comments.php?p=' . $comment->comment_post_ID . '">';
						$html .= __( 'View Post Comment Multi Images', 'comment-multi-images' );
					$html .= '</a>';
			 		
			 		echo $html;
			 		
			 		// Mark that we've discovered at least one comment image
			 		$has_comment_multi_image = true;
			 		
			 	} // end if
			 	
		 	} // end foreach
		 
		 } // end if
		 
	 } // end post_comment_multi_image
	 
	 /**
	  * Adds a column to the 'Comments' page indicating whether or not there are 
	  * Comment Multi Images available.
	  *
	  * @param	array	$columns	The columns displayed on the page.
	  * @param	array	$columns	The updated array of columns.
	  */
	 public function comment_has_image( $columns ) {
		 
		 $columns['comment-multi-images'] = __( 'Comment Multi Image', 'comment-multi-images' );
		 
		 return $columns;
		 
	 } // end comment_has_image
	 
	 /**
	  * Renders the actual image for the comment.
	  *
	  * @param	string	The name of the column being rendered.
	  * @param	int		The ID of the comment being rendered.
	  * @since	1.8
	  */
	 public function comment_multi_image( $column_name, $comment_id ) {

      $_SESSION['mamedu_msg'][] = 'Presentando imagen: '; // DEV
		 if( 'comment-multi-images' == strtolower( $column_name ) ) {

			 if( 0 != ( $comment_multi_image_data = get_comment_meta( $comment_id, 'comment_multi_image', true ) ) ) {
	
				 $image_url = $comment_multi_image_data['url'];
             $html = '';
             // $html =  '<a title="Edita">';
             $html .= '<img src="' . $image_url . '" width="150" />';
             // $html .= '</a>';
				 
				 echo $html;
			 
	 		 } // end if
 		 
 		 } // end if/else
		 
	 } // end comment_multi_image
	 
	 /**
	  * Determines whether or not the current comment has comment images. If so, adds a new link
	  * to the 'Recent Comments' dashboard.
	  *
	  * @param	array	$options	The array of options for each recent comment
	  * @param	object	$comment	The current recent comment
	  * @return	array	$options	The updated list of options
	  * @since	1.8
	  */
	 public function recent_comment_has_image( $options, $comment ) {
	 
		 if( 0 != ( $comment_multi_image = get_comment_meta( $comment->comment_ID, 'comment_multi_image', true ) ) ) {
			 
			 $html = '<a href="edit-comments.php?p=' . $comment->comment_post_ID . '">';
			 	$html .= __( 'Comment Multi Images', 'comment-multi-images' );
			 $html .= '</a>';
			 
			 $options['comment-multi-images'] = $html;

		 } // end if
		 
		 return $options;
		 
	 } // end recent_comment_has_image
	 
	 /**
	  * Loads the plugin text domain for translation
	  */
	 function plugin_textdomain() {
		 load_plugin_textdomain( 'comment-multi-images', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
	 } // end plugin_textdomain
	 
	 /**
	  * In previous versions of the plugin, the image were written out after the comments. Now,
	  * they are actually part of the comment content so we need to update all old options.
	  *
	  * Note that this option is not removed on deactivation because it will run *again* if the
	  * user ever re-activates it this duplicating the image.
	  */
	 private function update_old_comments() {
		 
		// Update the option that this has not run
		update_option( 'update_comment_multi_image', false );
		
		// Iterate through each of the comments...
 		foreach( get_comments() as $comment ) {
 		
			// If the comment image meta value exists...
			if( true == get_comment_meta( $comment->comment_ID, 'comment_multi_image' ) ) {
			
				// Get the associated comment image
				$comment_multi_image = get_comment_meta( $comment->comment_ID, 'comment_multi_image', true );
				
				// Append the image to the comment content
				$comment->comment_content .= '<p class="comment-multi-images">';
					$comment->comment_content .= '<img src="' . $comment_multi_image['url'] . '" alt="" />';
				$comment->comment_content .= '</p><!-- /.comment-multi-images -->';
				
				// Now we need to actually update the comment
				wp_update_comment( (array)$comment );
				
			} // end if
 		
		} // end if
		
		// Update the fact that this has run so we don't run it again
		update_option( 'update_comment_multi_image', true );
		 
	 } // end update_old_comments
	 
	 /**
	  * Display a WordPress error to the administrator if the hosting environment does not support 'file_get_contents.'
	  */
	 function save_error_notice() {
		 
		 $html = '<div id="comment-multi-images-notice" class="error">';
		 	$html .= '<p>';
		 		$html .= __( '<strong>Comment Multi Images Notice:</strong> Unfortunately, your host does not allow uploads from the comment form. This plugin will not work for your host.', 'comment-multi-images' );
		 	$html .= '</p>';
		 $html .= '</div><!-- /#comment-multi-images-notice -->';
		 
		 echo $html;
		 
	 } // end save_error_notice
	 
	 /**
	  * Adds the public stylesheet to the single post page.
     */

	 function add_styles() {

       // Comprobar tipo de post
       if ( ! empty($this->type_post) && ! in_array(get_post_type($post_id),$this->type_post) ) return ;

       // wp_enqueue_style( 'comment-multi-images', plugins_url( '/comment-multi-images/css/plugin.css'));

      }

	 
	/**
	 * Adds the public JavaScript to the single post page.
	 */ 
	function add_scripts() {
	
       // Comprobar tipo de post
       if ( ! empty($this->type_post) && ! in_array(get_post_type($post_id),$this->type_post) ) return ;

			// wp_register_script( 'comment-multi-images', plugins_url( '/comment-multi-images/js/plugin.min.js' ), array( 'jquery' ) );
			// wp_enqueue_script( 'comment-multi-images' );
			
      } // end add_scripts

	/**
	 * Adds the comment image upload form to the comment form.
	 *
    * @param	$campos_formulario Array con los campos del formulario
	 */
 	function add_image_upload_form( $campo_comentario ) {

      global $post;

      echo $campo_comentario;

      $post_id = $post->ID;

      // Comprobar opción individual del post 
      if( 'disable' == get_post_meta( $post_id, 'comment_multi_image_toggle', true ) ) return ;

      // Comprobar tipo de post
      if ( ! empty($this->type_post) && ! in_array(get_post_type($post_id),$this->type_post) ) return ;

      $this->comment_multi_images_hook();


		 
	} // end add_image_upload_form
	
	/**
	 * Adds the comment image upload form to the comment form.
	 *
	 * @param	$comment_id	The ID of the comment to which we're adding the image.
	 */
	function save_comment_multi_image( $comment_id ) {
	
      global $current_user;

      @session_start();

		// The ID of the post on which this comment is being made
		$post_id = $_POST['comment_post_ID'];

      // Definimos directorio donde se encuentran las imágenes temporales.
      get_currentuserinfo();
      $current_user_id=$current_user->ID;
      $upload_array = wp_upload_dir();
      $upload_dir=$upload_array['basedir'].'/'.COMMENTSMULTIIMAGES_DIR_IMG.'/tmp/'.$current_user_id;

      $imagenes_comentario = glob($upload_dir.'/*');

      $_SESSION['mamedu_msg'][] = 'Guardando imagen de comentario ['.$comment_id.']: '
         .' en ['.$upload_dir.'] '
         .print_r($imagenes_comentario,1); // DEV

		// The key ID of the comment image
		$comment_multi_image_id = "comment_multi_image_$post_id";
		
      if ( ! $imagenes_comentario ) return ;
      if ( empty($imagenes_comentario) ) return ;

      foreach ( $imagenes_comentario as $img ) {
      
         // Store the parts of the file name into an array
         $file_name_parts = explode( '.', basename($img) );
         
         // If the file is valid, upload the image, and store the path in the comment meta
         if( $this->is_valid_file_type( $file_name_parts[ count( $file_name_parts ) - 1 ] ) ) {;
         
            // Upload the comment image to the uploads directory
            $comment_multi_image_file = wp_upload_bits( basename($img), null, file_get_contents( $img ) );

            // Una descripción automatizada para la imagen
            $desc = "Comentario: $comment_id / Usuario: $current_user_id"; 

            // do the validation and storage stuff
            $id = media_handle_sideload( array('tmp_name' => $img, 'name' => basename($img)), $post_id, $desc );

            // If error storing permanently, unlink
            if ( is_wp_error($id) ) {
               @unlink($img);
               return FALSE;
               }

		      $src = wp_get_attachment_url( $id );

            $comment_multi_image_file['url'] = $src;
            $comment_multi_image_file['post_id'] = $id;
            
            // Set post meta about this image. Need the comment ID and need the path.
            if( false == $comment_multi_image_file['error'] ) {
               
               // Since we've already added the key for this, we'll just update it with the file.
               add_comment_meta( $comment_id, 'comment_multi_image', $comment_multi_image_file );
               
            } // end if/else
         
         } // end if
         
         // Borramos imagen temporal
         unlink($img);

      } // end foreach

		
	} // end save_comment_multi_image
	
   /**
    * Añadir relación de imagen con el comentario 
    * 
    * @param $comment_id Identificador de comentario
    * @param $image_id   Identificador de imagen de la galería
    */

   static function insertar_relacion_imagen_comentario($comment_id, $image_id) {

      $src = wp_get_attachment_url( $image_id );

      $comment_multi_image_file['url'] = $src;
      $comment_multi_image_file['post_id'] = $image_id;
      
      add_comment_meta( $comment_id, 'comment_multi_image', $comment_multi_image_file );
         
      }


	/**
    * Presentamos galeria de imágenes.
    *
    * @todo Hacer configurable: ¿Presentar con gallery?
    * @todo Crear plantilla para la presentación.
	 *
	 * @param	$comment	The content of the comment.
	 */
	function display_comment_multi_image( $comments ) {

      @session_start();

		// Make sure that there are comments
		if( count( $comments ) > 0 ) {
		
			// Loop through each comment...
			foreach( $comments as $comment ) {
			
				// ...and if the comment has a comment image...
				if( true == get_comment_meta( $comment->comment_ID, 'comment_multi_image' ) ) {
			
					// ...get the comment image meta
					$comment_multi_image = get_comment_meta( $comment->comment_ID, 'comment_multi_image', false );

               $lista_imagenes = FALSE ;
               foreach ( $comment_multi_image as $contenido ) {
               
                  // ...and render it in a paragraph element appended to the comment
                  // $comment->comment_content .= '<p class="comment-multi-images">';
                  //    $comment->comment_content .= '<img src="' . $contenido['url'] . '" alt="" />';
                  // $comment->comment_content .= '</p><!-- /.comment-multi-images -->';	

                  if ( isset($contenido['post_id']) && (! empty($contenido['post_id']) ) ) 
                     $lista_imagenes .= $contenido['post_id'].',';

                  $_SESSION['mamedu_msg'][] = 'lista: '.print_r($contenido,1);

                  }
               
               if ( $lista_imagenes ) {
                  $lista_imagenes = rtrim($lista_imagenes,',');
                  $shortcode = "
                     <p>
                        <div id='galeria_pais'>
                        ".do_shortcode('[gallery gallery columns="5" link="file" include="'.$lista_imagenes.'"]')."
                        </div>
                     </p>
                     ";
                  $comment->comment_content .= $shortcode;
                  $_SESSION['mamedu_msg'][] = 'shortcode: '.'[gallery gallery columns="5" link="file" include="'.$lista_imagenes.'"]';
                  }

				} // end if
				
			} // end foreach
			
		} // end if
		
		return $comments;

	} // end display_comment_multi_image
	
	/*--------------------------------------------*
	 * Meta Box Functions
	 *---------------------------------------------*/
	
	 /**
	  * Registers the meta box for displaying the 'Comment Multi Images' options in the post editor.
	  *
	  * @version	1.0
	  * @since 		1.8
	  */
	 public function add_comment_multi_image_meta_box() {
		 
       if ( empty($this->type_post) || $this->type_post == '' ) {
             add_meta_box(
               'disable_comment_multi_image',
               __( 'Comment Multi Images', 'comment-multi-images' ),
               array( $this, 'comment_multi_image_display' ),
               '',
               'side',
               'low'
             );
       } else {

          foreach ( $this->type_post as $type_post ) {
             add_meta_box(
               'disable_comment_multi_image',
               __( 'Comment Multi Images', 'comment-multi-images' ),
               array( $this, 'comment_multi_image_display' ),
               $type_post,
               'side',
               'low'
             );
             }
          
          }
	 } // end add_project_completion_meta_box
	 
	 /**
	  * Displays the option for disabling the Comment Multi Images upload field.
	  *
	  * @version	1.0
	  * @since 		1.8
	  */
	 public function comment_multi_image_display( $post ) {
		 
		 wp_nonce_field( plugin_basename( __FILE__ ), 'comment_multi_image_display_nonce' );

		 $html = '<select name="comment_multi_image_toggle" id="comment_multi_image_toggle" class="comment_multi_image_toggle_select">';
		 	$html .= '<option value="enable" ' . selected( 'enable', get_post_meta( $post->ID, 'comment_multi_image_toggle', true ), false ) . '>' . __( 'Enable comment images for this post.', 'comment-multi-images' ) . '</option>';
		 	$html .= '<option value="disable" ' . selected( 'disable', get_post_meta( $post->ID, 'comment_multi_image_toggle', true ), false ) . '>' . __( 'Disable comment images for this post.', 'comment-multi-images' ) . '</option>';
		 $html .= '</select>';
  
		 echo $html;
		 
	 } // end comment_multi_image_display
	 
	 /**
	  * Saves the meta data for displaying the 'Comment Multi Images' options in the post editor.
	  *
	  * @version	1.0
	  * @since 		1.8
	  */
	 public function save_comment_multi_image_display( $post_id ) {
		 
		 // If the user has permission to save the meta data...
		 if( $this->user_can_save( $post_id, 'comment_multi_image_display_nonce' ) ) { 
		 
		 	// Delete any existing meta data for the owner
			if( get_post_meta( $post_id, 'comment_multi_image_toggle' ) ) {
				delete_post_meta( $post_id, 'comment_multi_image_toggle' );
			} // end if
			update_post_meta( $post_id, 'comment_multi_image_toggle', $_POST[ 'comment_multi_image_toggle' ] );		
			 
		 } // end if
		 
	 } // end save_comment_multi_image_display
	
	/*--------------------------------------------*
	 * Utility Functions
	 *---------------------------------------------*/
	
	/**
	 * Determines if the specified type if a valid file type to be uploaded.
	 *
	 * @param	$type	The file type attempting to be uploaded.
	 * @return			Whether or not the specified file type is able to be uploaded.
	 */ 
	private function is_valid_file_type( $type ) { 
	
		$type = strtolower( trim ( $type ) );
		return $type == __( 'png', 'comment-multi-images' ) || $type == __( 'gif', 'comment-multi-images' ) || $type == __( 'jpg', 'comment-multi-images' ) || $type == __( 'jpeg', 'comment-multi-images' );
		
	} // end is_valid_file_type
	
	/**
	 * Determines if the hosting environment allows the users to upload files.
	 *
	 * @return			Whether or not the hosting environment supports the ability to upload files.
	 */ 
	private function can_save_files() {
		return function_exists( 'file_get_contents' );
	} // end can_save_files
	
	 /**
	  * Determines whether or not the current user has the ability to save meta data associated with this post.
	  *
	  * @param		int		$post_id	The ID of the post being save
	  * @param		bool				Whether or not the user has the ability to save this post.
	  * @version	1.0
	  * @since		1.8
	  */
	 private function user_can_save( $post_id, $nonce ) {
		
	    $is_autosave = wp_is_post_autosave( $post_id );
	    $is_revision = wp_is_post_revision( $post_id );
	    $is_valid_nonce = ( isset( $_POST[ $nonce ] ) && wp_verify_nonce( $_POST[ $nonce ], plugin_basename( __FILE__ ) ) ) ? true : false;
	    
	    // Return true if the user is able to save; otherwise, false.
	    return ! ( $is_autosave || $is_revision) && $is_valid_nonce;

	 } // end user_can_save
  
   ////////////////////////////////
   // Métodos para jquery-upload //
   ////////////////////////////////

   /**
    * Añadir todo el javascript y css necesario
    */

   function enqueue_scripts() {

      $stylepath=COMMENTSMULTIIMAGES_DIR_URL.'css/';
      $scriptpath=COMMENTSMULTIIMAGES_DIR_URL.'js/';

      wp_enqueue_style ( 'jquery-ui-style', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.0/themes/base/jquery-ui.css' );
      // wp_enqueue_style ( 'jquery-image-gallery-style', 'http://blueimp.github.com/jQuery-Image-Gallery/css/jquery.image-gallery.min.css');
      wp_enqueue_style ( 'jquery-fileupload-ui-style', $stylepath . 'jquery.fileupload-ui.css');
      wp_enqueue_script ( 'enable-html5-script', 'http://html5shim.googlecode.com/svn/trunk/html5.js');

      // if(!wp_script_is('jquery')) {
      //    wp_enqueue_script ( 'jquery', '//ajax.googleapis.com/ajax/libs/jquery/1.9.0/jquery.min.js',array(),'',false);
      // }
      // wp_enqueue_script ( 'jquery-ui-script', '//ajax.googleapis.com/ajax/libs/jqueryui/1.10.0/jquery-ui.min.js',array('jquery'),'',true);

      wp_enqueue_script('jquery');
      wp_enqueue_script('jquery-ui-core');
      wp_enqueue_script('jquery-ui-widget');
      wp_enqueue_script('jquery-ui-progressbar');

      wp_enqueue_script ( 'tmpl-script',  $scriptpath .'tmpl.min.js',array('jquery'),'',true);
      wp_enqueue_script ( 'load-image-script', $scriptpath .'load-image.min.js',array('jquery'),'',true);
      wp_enqueue_script ( 'canvas-to-blob-script',$scriptpath . 'canvas-to-blob.min.js',array('jquery'),'',true);
      wp_enqueue_script ( 'jquery-image-gallery-script',$scriptpath .  'jquery.image-gallery.min.js',array('jquery'),'',true);
      wp_enqueue_script ( 'jquery-iframe-transport-script', $scriptpath . 'jquery.iframe-transport.js',array('jquery'),'',true);
      wp_enqueue_script ( 'jquery-fileupload-script', $scriptpath . 'jquery.fileupload.js',array('jquery'),'',true);
      wp_enqueue_script ( 'jquery-fileupload-fp-script', $scriptpath . 'jquery.fileupload-fp.js',array('jquery'),'',true);
      wp_enqueue_script ( 'jquery-fileupload-ui-script', $scriptpath . 'jquery.fileupload-ui.js',array('jquery'),'',true);
      wp_enqueue_script ( 'jquery-fileupload-jui-script', $scriptpath . 'jquery.fileupload-jui.js',array('jquery'),'',true);
      wp_enqueue_script ( 'transport-script', $scriptpath . 'cors/jquery.xdr-transport.js',array('jquery'),'',true);
   }	

   /**
    * Método utilizado por jquery-upload para subir las imágenes
    *
    * Las imágenes serán subidas a un directorio temporal crenado subcarpetas
    * para no confundir las imágenes de otros usuarios.
    *
    * Directorio temporal:
    *
    * upload/comment_multi_images/tmp/identificador_usuario/
    *
    * Una vez guardado el comentario ya con el identificador del mismo, guardamos
    * las imágenes a su destino definitivo.
    *
    * En caso de que un usuario suba imágenes pero no guarde el comentario, estas 
    * imágenes permaneceran el directorio temporal y le seran mostradas cada vez 
    * que se muestre el formulario del comentario.
    *
    */

   function load_ajax_function() {

      /* Include the upload handler */
      require 'UploadHandler.php';
      global $current_user;
      get_currentuserinfo();
      $current_user_id=$current_user->ID;
      if(!isset($current_user_id) || $current_user_id=='')
         $current_user_id='guest';
      $upload_handler = new UploadHandler(null,'tmp/'.$current_user_id,true);
      die(); 
   }

   function add_inline_script() {

      // Comprobar tipo de post
      if ( ! empty($this->type_post) && ! in_array(get_post_type($post_id),$this->type_post) ) return ;

   ?>
   <script type="text/javascript">
   /*
    * jQuery File Upload Plugin JS Example 7.0
    * https://github.com/blueimp/jQuery-File-Upload
    *
    * Copyright 2010, Sebastian Tschan
    * https://blueimp.net
    *
    * Licensed under the MIT license:
    * http://www.opensource.org/licenses/MIT
    */
   jQuery(function () {
       'use strict';

       // Initialize the jQuery File Upload widget:
       jQuery('#commentform').fileupload({
           url: '<?php print(admin_url('admin-ajax.php'));?>'
              , maxNumberOfFiles: <?php echo $this->limit_images; ?>
              , autoUpload: true
       });

       // Enable iframe cross-domain access via redirect option:
       jQuery('#commentform').fileupload(
           'option',
           'redirect',
           window.location.href.replace(
               /\/[^\/]*$/,
            <?php
            $absoluteurl=str_replace(home_url(),'',COMMENTSMULTIIMAGES_DIR_URL);
            print("'".$absoluteurl."cors/result.html?%s'");
            ?>
           )
       );

      if(jQuery('#commentform')) {
         // Load existing files:
           jQuery.ajax({
               // Uncomment the following to send cross-domain cookies:
               //xhrFields: {withCredentials: true},
               url: jQuery('#commentform').fileupload('option', 'url'),
            data : {action: "load_ajax_function"},
            acceptFileTypes: /(\.|\/)(<?php print(get_option('comment_mi_accepted_file_types')); ?>)$/i,
            dataType: 'json',
            context: jQuery('#commentform')[0]
            
               
           }).done(function (result) {
            jQuery(this).fileupload('option', 'done')
                     .call(this, null, {result: result});
           });
       }

       // Initialize the Image Gallery widget:
       jQuery('#commentform .files').imagegallery();

       // Initialize the theme switcher:
       jQuery('#theme-switcher').change(function () {
           var theme = jQuery('#theme');
           theme.prop(
               'href',
               theme.prop('href').replace(
                   /[\w\-]+\/jquery-ui.css/,
                   jQuery(this).val() + '/jquery-ui.css'
               )
           );
       });

   });

   </script>
   <?php
   }

   /* Block of code that need to be printed to the form*/
   function comment_multi_images_hook() {
   ?>
   <!-- The file upload form used as target for the file upload widget -->
       <!-- <form id="fileupload" action="<?php print(admin_url().'admin-ajax.php');?>" method="POST" enctype="multipart/form-data"> -->
           <!-- Redirect browsers with JavaScript disabled to the origin page -->
<div id="comment-multiimages">
          <input type="hidden" name="action" value="load_ajax_function" />
           <!-- The fileupload-buttonbar contains buttons to add/delete files and start/cancel the upload -->
           <div class="row fileupload-buttonbar">
               <div class="span7">
                   <!-- The fileinput-button span is used to style the file input field as button -->
                   <span class="btn btn-success fileinput-button">
                       <i class="icon-plus icon-white"></i>
                       <span>Seleccionar fotos...</span>
                       <input type="file" name="files[]" multiple>
                   </span>
                   <button style="display: none" type="submit" class="btn btn-primary start">
                       <i class="icon-upload icon-white"></i>
                       <span>Enviar todas</span>
                   </button>
                   <button style="display: none" type="button" class="btn btn-danger delete">
                       <i class="icon-trash icon-white"></i>
                       <span>Borrar Seleccionadas</span>
                   </button>
                   <input style="display: none" type="checkbox" class="toggle">
               </div>
               <!-- The global progress information -->
               <div class="span5 fileupload-progress fade">
                   <!-- The global progress bar -->
                   <div class="progress progress-success progress-striped active" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                       <div class="bar" style="width:0%;"></div>
                   </div>
                   <!-- The extended global progress information -->
                   <div class="progress-extended">&nbsp;</div>
               </div>
           </div>
         
           <!-- The loading indicator is shown during file processing -->
           <div class="fileupload-loading"></div>
           <br>
           <!-- The table listing the files available for upload/download -->
         
         
           <table role="presentation" class="table table-striped" style="width:590px;"><tbody class="files" data-toggle="modal-gallery" data-target="#modal-gallery"></tbody></table>
         
       <!-- </form> -->
       <br>
       <div class="well">
          
       Numero máximo de imagenes <?php echo $this->limit_images; ?>
       </div>

</div> <!-- Acaba comment-multiimages -->

   <!-- The template to display files available for upload -->
   <script id="template-upload" type="text/x-tmpl">
   {% for (var i=0, file; file=o.files[i]; i++) { %}
       <tr class="template-upload fade">
           <td class="preview"><span class="fade"></span></td>
          
           {% if (file.error) { %}
               <td class="error" colspan="2"><span class="label label-important">Error: </span> {%=file.error%}</td>
           {% } else if (o.files.valid && !i) { %}
               <td >
                   <div class="progress progress-success progress-striped active" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="bar" style="width:0%;"></div></div>
               </td>
               <td style="display: none" class="start" colspan="3">{% if (!o.options.autoUpload) { %}
                   <button class="btn btn-primary">
                       <i class="icon-upload icon-white"></i>
                       <span>Enviar</span>
                   </button>
               {% } %}</td>
           {% } else { %}
               <td colspan="2"></td>
           {% } %}
           <td class="cancel">{% if (!i) { %}
               <button class="btn btn-warning">
                   <i class="icon-ban-circle icon-white"></i>
                   <span>Eliminar</span>
               </button>
           {% } %}</td>
       </tr>
   {% } %}
   </script>
   <!-- The template to display files available for download -->
   <script id="template-download" type="text/x-tmpl">
   {% for (var i=0, file; file=o.files[i]; i++) { %}
       <tr class="template-download fade">
           {% if (file.error) { %}
         <td class="error" colspan="5"><span class="label label-important">Error: </span> {%=file.error%} ({%=file.name.substring(4)%})</td>            
               
           {% } else { %}
               <td class="preview">{% if (file.thumbnail_url) { %}
                   <a href="{%=file.url%}" title="{%=file.name%}" data-gallery="gallery" download="{%=file.name%}"><img src="{%=file.thumbnail_url%}"></a>
               {% } %}</td>
               <td class="name" style="width:200px;">
   <div style="width:190px;overflow-x:hidden;">
                   <a href="{%=file.url%}" title="{%=file.name%}" data-gallery="{%=file.thumbnail_url&&'gallery'%}" download="{%=file.name%}">{%=file.name%}</a>
              </div> </td>
               <td class="size"><span>{%=o.formatFileSize(file.size)%}</span></td>
               <td colspan="2"></td>
           {% } %}
           <td class="delete">
               <button class="btn btn-danger" data-type="{%=file.delete_type%}" data-url="{%=file.delete_url%}&action=load_ajax_function"{% if (file.delete_with_credentials) { %} data-xhr-fields='{"withCredentials":true}'{% } %}>
                   <i class="icon-trash icon-white"></i>
                   <span>Eliminar</span>
               </button>
               <input style="display: none" type="checkbox" name="delete" value="1">
           </td>
       </tr>
   {% } %}
   </script>
   <?php
   }

} // end class

///////////////////////////////
// Administración del plugin //
///////////////////////////////

/* Runs when plugin is activated */
register_activation_hook(__FILE__,'comment_multi_images_install'); 

/* Runs on plugin deactivation*/
register_deactivation_hook( __FILE__, 'comment_multi_images_remove' );

function comment_multi_images_install() {
	add_option("comment_mi_accepted_file_types", 'gif|jpeg|jpg|png', '', 'yes');
	add_option("comment_mi_inline_file_types", 'gif|jpeg|jpg|png', '', 'yes');
	add_option("comment_mi_maximum_file_size", '5', '', 'yes');
	add_option("comment_mi_maximum_files", '5', '', 'yes');
	add_option("comment_mi_type_post", '', '', 'yes');
	
	$upload_array = wp_upload_dir();
	$upload_dir=$upload_array['basedir'].'/'.COMMENTSMULTIIMAGES_DIR_IMG.'/';
	/* Create the directory where you upoad the file */
	if (!is_dir($upload_dir)) {
		$is_success=mkdir($upload_dir, '0755', true);
		if(!$is_success)
			die('Unable to create a directory tmp within the upload folder');
	}
}

function comment_multi_images_remove() {
	/* Deletes the database field */
	delete_option('comment_mi_accepted_file_types');
	delete_option('comment_mi_inline_file_types');
	delete_option('comment_mi_maximum_file_size');
	delete_option('comment_mi_maximum_files');
	delete_option('comment_mi_type_post');
}

if(isset($_POST['savesetting']) && $_POST['savesetting']=="Save Setting")
{
	update_option("comment_mi_accepted_file_types", $_POST['accepted_file_types']);
	update_option("comment_mi_inline_file_types", $_POST['inline_file_types']);
	update_option("comment_mi_maximum_file_size", $_POST['maximum_file_size']);
	update_option("comment_mi_maximum_files", $_POST['maximum_files']);
	update_option("comment_mi_type_post", $_POST['type_post']);
}

// Add settings link on plugin page
function comment_multi_images_settings_link($links) { 
  $settings_link = '<a href="options-general.php?page=comment-multi-images-setting.php">Settings</a>'; 
  array_unshift($links, $settings_link); 
  return $links; 
}
 
$plugin = plugin_basename(__FILE__); 
add_filter("plugin_action_links_$plugin", 'comment_multi_images_settings_link' );

if ( is_admin() ){

/* Call the html code */
add_action('admin_menu', 'comment_multi_images_admin_menu');


function comment_multi_images_admin_menu() {
add_options_page('Comment Multi Images Setting', 'Comment Multi Images Setting', 'administrator',
'comment-multi-images-setting', 'comment_multi_images_html_page');
}
}

function comment_multi_images_html_page() {
?>
<h2>Comment Multi Images Setting</h2>

<form method="post" >
<?php wp_nonce_field('update-options'); ?>

<table >
<tr >
<td>Accepted File Types</td>
<td >
<input type="text" name="accepted_file_types" value="<?php print(get_option('comment_mi_accepted_file_types')); ?>" />&nbsp;filetype seperated by | (e.g. gif|jpeg|jpg|png)
</td>
</tr>
<tr >
<td>Inline File Types</td>
<td >
<input type="text" name="inline_file_types" value="<?php print(get_option('comment_mi_inline_file_types')); ?>" />&nbsp;filetype seperated by | (e.g. gif|jpeg|jpg|png)
</td>
</tr>
<tr >
<td>Maximum File Size</td>
<td >
<input type="text" name="maximum_file_size" value="<?php print(get_option('comment_mi_maximum_file_size')); ?>" />&nbsp;MB
</td>
</tr>
<tr >
<td>Maximum Files</td>
<td >
<input type="text" name="maximum_files" value="<?php print(get_option('comment_mi_maximum_files')); ?>" />&nbsp;Máximo de imágenes permitidas
</td>
</tr>
<tr >
<td colspan="2">
Indicar los tipos de post donde se da la opción de subir imágenes en los comentarios a los usuarios separados por comas.<br /> 
Si dejamos vacío se permitirá en todos los tipos de post.
</td>
<tr >
<td>Type of posts</td>
<td >
<input type="text" name="type_post" value="<?php print(get_option('comment_mi_type_post')); ?>" />&nbsp;(e.g. post,actividades,portafolio). 
</td>
</tr>
<tr >
<td colspan="2">
<input type="submit" name="savesetting" value="Save Setting" />
</td>
</tr>
</table>
</form>
<?php
}

new Comment_Multi_Image();


?>
