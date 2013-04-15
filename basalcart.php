<?php
/*
Plugin Name: BasalCart
Plugin URI: http://oliver.jetsets.jp/
Description: Simple Cart Plugin.
Version: 0.1
Author: Jetset Inc.
Author URI: http://jetsets.jp/
License: GPLv2 or later
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

class wp_basalcart {
	
	function __construct() {
		if(isset($_GET['wp_basalcart_output_js_file']) && ($_GET['wp_basalcart_output_js_file'] == 'true')) {
			$this->wp_basalcart_output_dynamic_js_file();
		} else {
			add_action('wp_enqueue_scripts', array($this, 'wp_basalcart_enqueue_script_files'));
			add_action('admin_enqueue_scripts', array($this, 'wp_basalcart_enqueue_admin_script_files'));
			
			add_filter('query_vars', array($this, 'wp_basalcart_add_query_vars'));
			add_filter('rewrite_rules_array', array($this, 'wp_basalcart_add_rewrite_rules'));
			
			add_action('init', array($this, 'wp_basalcart_add_new_post_type'));
			add_action('admin_head', array($this, 'wp_basalcart_change_post_type_top_icon'));
			add_action('init', array($this, 'wp_basalcart_add_cat_and_tag_meta_boxes'));
			add_action('post_edit_form_tag', array($this, 'wp_basalcart_add_edit_form_multipart_encoding'));
			add_action('admin_init', array($this, 'wp_basalcart_setup_meta_boxes'));
			add_action('save_post', array($this, 'wp_basalcart_update_post_image'), 10, 2);
			add_action('save_post', array($this, 'wp_basalcart_update_general_post_meta_box'), 10, 2);
			add_filter('manage_wp_basalcart_product_posts_columns' , array($this, 'wp_basalcart_set_product_columns'));
			add_action('manage_wp_basalcart_product_posts_custom_column', array($this, 'wp_basalcart_add_columns_value'), 10, 2);
			add_filter('manage_edit-wp_basalcart_product_sortable_columns', array($this, 'wp_basalcart_register_sortable_columns'));
			
			add_action('init', array($this, 'wp_basalcart_init_session'));
			add_action('wp_ajax_call_response', array($this, 'wp_basalcart_call_response'));
			add_action('wp_ajax_nopriv_call_response', array($this, 'wp_basalcart_call_response'));
			
			add_action('wp_head', array($this, 'wp_basalcart_add_form_contents_to_session'));
			add_action('wp_head', array($this, 'wp_basalcart_move_cart_session_to_db'));
			add_action('wp_head', array($this, 'wp_basalcart_move_user_session_to_db'));
			add_action('wp_head', array($this, 'wp_basalcart_insert_cross_sales_to_db'));
			
		}
	}
	
	
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	///
	///
	///  Methods for Back-end start here
	///
	///
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	function wp_basalcart_initialize_plugin() {
		global $wp_rewrite;
		$rules = get_option('rewrite_rules');
		$wp_rewrite->flush_rules();
		
		global $wpdb;
		$sqls = array();
		$sqls[] = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."basalcart_also_bought (
					productid bigint(20) NOT NULL,
					alsobought bigint(20) NOT NULL,
					amount int(10) NOT NULL DEFAULT 1
					) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
		
		$sqls[] = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."basalcart_customers (
					id bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
					firstname varchar(255) NOT NULL,
					lastname varchar(255) NOT NULL,
					firstnamekana varchar(255),
					lastnamekana varchar(255),
					zipcode varchar(60) NOT NULL,
					address varchar(255) NOT NULL,
					detailedaddress varchar(255),
					phonenumber varchar(100) NOT NULL,
					email varchar(100),
					purchasetimes bigint(20) NOT NULL,
					user_login varchar(60) UNIQUE,
					user_pass varchar(64)
					) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
					
		$sqls[] = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."basalcart_orders (
					id bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
					products text NOT NULL,
					totalprice int(10) NOT NULL,
					userid bigint(20) NOT NULL,
					date datetime NOT NULL,
					processed tinyint(1) NOT NULL DEFAULT 0,
					deleted tinyint(1) NOT NULL DEFAULT 0
					) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
		
		$sqls[] = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."basalcart_product_rating (
					ipnum longtext NOT NULL,
					productid bigint(20) NOT NULL PRIMARY KEY,
					rated tinyint(1) NOT NULL,
					times bigint(20) NOT NULL,
					rating tinyint(1) NOT NULL
					) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
					
		foreach($sqls as $sql) {
			$wpdb->query($wpdb->prepare($sql));
		}
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_add_query_vars($vars) {
		array_push($vars, 'is_checkout_step');
		return $vars;
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_add_rewrite_rules($rules) {
		$newrules = array();
		$newrules['checkout/?$'] = 'index.php?is_checkout_step=1';
		$newrules['checkout-confirmation/?$'] = 'index.php?is_checkout_step=2';
		$newrules['order-placed/?$'] = 'index.php?is_checkout_step=3';
		return $newrules + $rules;
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_get_link_to_checkout($level) {
		$output = '';
		if(isset($level)) {
			global $wp_rewrite;
			if($wp_rewrite->using_permalinks()){
				switch((int)$level) {
					case 1:
						$output = get_bloginfo('home').'/checkout/';
						break;
					case 2:
						$output = get_bloginfo('home').'/checkout-confirmation/';
						break;
					case 3:
						$output = get_bloginfo('home').'/order-placed/';
						break;
				}
			} else {
				switch((int)$level) {
					case 1:
						$output = get_bloginfo('home').'/index.php?is_checkout_step=1';
						break;
					case 2:
						$output = get_bloginfo('home').'/index.php?is_checkout_step=2';
						break;
					case 3:
						$output = get_bloginfo('home').'/index.php?is_checkout_step=3';
						break;
				}
			}
		}
		return $output;
	}
	
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_enqueue_script_files() {
		if (!is_admin()) {
			wp_deregister_script('jquery');
			wp_register_script('jquery', 'http://code.jquery.com/jquery-latest.min.js');
			wp_enqueue_script('jquery');
			wp_enqueue_script('wp_basalcart_output_js_file', plugins_url('/basalcart.php?wp_basalcart_output_js_file=true', __FILE__), false, null);
			if(isset($_GET['wp_basalcart_output_js_file']) && ($_GET['wp_basalcart_output_js_file'] == 'true')) {
				add_action('init', array($this, 'wp_basalcart_output_dynamic_js_file'));
			}
			wp_localize_script('wp_basalcart_output_js_file', 'ajaxurl', get_bloginfo('home').'/wp-admin/admin-ajax.php');
		}
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_enqueue_admin_script_files($hook) {
		//if($hook != 'edit.php' && $hook != 'post.php' && $hook != 'post-new.php') { return; }
		wp_deregister_script('jquery');
		wp_register_script('jquery', 'http://code.jquery.com/jquery-latest.min.js');
		wp_enqueue_script('jquery');
		wp_enqueue_script('wp_basalcart_output_js_file', plugins_url('/basalcart.php?wp_basalcart_output_js_file=true', __FILE__), false, null);
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_add_new_post_type() {
		$labels = array(
			'name' => __('Products', 'post type general name'),
			'singular_name' => __('Product', 'post type singular name'),
			'add_new' => __('Add New', 'product'),
			'add_new_item' => __('Add New Product'),
			'edit_item' => __('Edit Product'),
			'new_item' => __('New Product'),
			'all_items' => __('All Products'),
			'view_item' => __('View Product'),
			'search_items' => __('Search Products'),
			'not_found' =>  __('No products found'),
			'not_found_in_trash' => __('No products found in Trash'), 
			'parent_item_colon' => '',
			'menu_name' => __('Products')
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => true, 
			'query_var' => true,
			'rewrite' => true,
			'capability_type' => 'post',
			'has_archive' => false, 
			'hierarchical' => false,
			'menu_position' => null,
			'menu_icon' => plugins_url('images/admin_side_blue.jpg', __FILE__),
			'supports' => array( 'title', 'editor', 'author', 'excerpt', 'comments'),
			'taxonomies' => array('wp_basalcart_product_category', 'wp_basalcart_product_tag')
		); 
		register_post_type('wp_basalcart_product', $args);
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	
	function wp_basalcart_change_post_type_top_icon() {
		global $post_type;
		if (($_GET['post_type'] == 'wp_basalcart_product') || ($post_type == 'wp_basalcart_product')) {
			echo '<style type="text/css">#icon-edit { background:transparent url("'.plugins_url('images/admin_top_blue.jpg', __FILE__).'") no-repeat; }</style>';
		}
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_add_cat_and_tag_meta_boxes() {
		$labels = array(
			'name' => __( 'Product Categories', 'category general name' ),
			'singular_name' => __( 'Product Category', 'category singular name' ),
			'search_items' =>  __( 'Search Category' ),
			'all_items' => __( 'All Categories' ),
			'parent_item' => __( 'Parent Category' ),
			'parent_item_colon' => __( 'Parent Category:' ),
			'edit_item' => __( 'Edit Category' ), 
			'update_item' => __( 'Update Category' ),
			'add_new_item' => __( 'Add New Category' ),
			'new_item_name' => __( 'New Category Name' ),
			'menu_name' => __( 'Categories' ),
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'hierarchical' => true,
			'query_var' => true,
		);
		register_taxonomy('wp_basalcart_product_category', 'wp_basalcart_product', $args);
		
		$labels = array(
			'name' => __( 'Product Tags', 'taxonomy general name' ),
			'singular_name' => __( 'Product Tag', 'taxonomy singular name' ),
			'search_items' =>  __( 'Search Product Tags' ),
			'all_items' => __( 'All Product Tags' ),
			'parent_item' => __( 'Parent Product Tag' ),
			'parent_item_colon' => __( 'Parent Product Tag:' ),
			'edit_item' => __( 'Edit Product Tag' ), 
			'update_item' => __( 'Update Product Tag' ),
			'add_new_item' => __( 'Add New Product Tag' ),
			'new_item_name' => __( 'New Product Tag Name' ),
			'menu_name' => __( 'Product Tags' ),
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'hierarchical' => false,
			'query_var' => true,
		);
		register_taxonomy('wp_basalcart_product_tag', 'wp_basalcart_product', $args);
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_setup_meta_boxes() {
		add_meta_box('wp_basalcart_mainimagebox', 'Upload Mainimage', array($this, 'wp_basalcart_render_image_attachment_box'), 'wp_basalcart_product', 'normal', 'high', array('typeofbox'=>'mainimage'));
		add_meta_box('wp_basalcart_pic1box', 'Upload Pic1', array($this, 'wp_basalcart_render_image_attachment_box'), 'wp_basalcart_product', 'normal', 'high', array('typeofbox'=>'pic1'));
		add_meta_box('wp_basalcart_pic2box', 'Upload Pic2', array($this, 'wp_basalcart_render_image_attachment_box'), 'wp_basalcart_product', 'normal', 'high', array('typeofbox'=>'pic2'));
		add_meta_box('wp_basalcart_pic3box', 'Upload Pic3', array($this, 'wp_basalcart_render_image_attachment_box'), 'wp_basalcart_product', 'normal', 'high', array('typeofbox'=>'pic3'));
		add_meta_box('wp_basalcart_price', 'Price', array($this, 'wp_basalcart_render_general_meta_box'), 'wp_basalcart_product', 'side', 'default', array('typeofbox'=>'price'));
		add_meta_box('wp_basalcart_sku', 'SKU', array($this, 'wp_basalcart_render_general_meta_box'), 'wp_basalcart_product', 'side', 'default', array('typeofbox'=>'sku'));
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_add_edit_form_multipart_encoding() {
		echo ' enctype="multipart/form-data"';
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_render_image_attachment_box($post, $metabox) {
		global $post;
		$current_meta_box = $metabox['args']['typeofbox'];
		$get_all_metabox_values = maybe_unserialize(get_post_meta($post->ID,'wp_basalcart_metadata', true));
		$existing_image_id = $get_all_metabox_values[$current_meta_box];
		
		if(is_numeric($existing_image_id)) {
			echo '<div>';
				$arr_existing_image = wp_get_attachment_image_src($existing_image_id, 'large');
				$existing_image_url = $arr_existing_image[0];
				echo '<img style="width:100%;height:auto;" src="' . $existing_image_url . '" />';
			echo '</div>';
		}
		
		echo 'Upload an image: <input type="file" name="'.$current_meta_box.'" id="'.$current_meta_box.'" />';
		
		$status_message = get_post_meta($post->ID,'_wp_basalcart_attached_image_upload_feedback', true);
		
		if($status_message) {
			echo '<div class="upload_status_message">';
				echo $status_message;
			echo '</div>';
		}
		echo '<input type="hidden" name="wp_basalcart_manual_save_flag" value="true" />';
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_update_post_image($post_id, $post) {
		global $post;
		
		if($post_id && isset($_POST['wp_basalcart_manual_save_flag'])) {
			$meta_data_array = maybe_unserialize(get_post_meta($post_id,'wp_basalcart_metadata', true));
			
			if(isset($_FILES['mainimage']) && ($_FILES['mainimage']['size'] > 0)) {
				$meta_boxes_containing_values[] = 'mainimage';
			}
			if(isset($_FILES['pic1']) && ($_FILES['pic1']['size'] > 0)) {
				$meta_boxes_containing_values[] = 'pic1';
			}
			if(isset($_FILES['pic2']) && ($_FILES['pic2']['size'] > 0)) {
				$meta_boxes_containing_values[] = 'pic2';
			}
			if(isset($_FILES['pic3']) && ($_FILES['pic3']['size'] > 0)) {
				$meta_boxes_containing_values[] = 'pic3';
			}
			
			if(!empty($meta_boxes_containing_values)) {
				foreach($meta_boxes_containing_values as $meta_box_with_value) {
					$arr_file_type = wp_check_filetype(basename($_FILES[$meta_box_with_value]['name']));
                    $uploaded_file_type = $arr_file_type['type'];
					$allowed_file_types = array('image/jpg','image/jpeg','image/gif','image/png');
					
					if(in_array($uploaded_file_type, $allowed_file_types)) {
						$upload_overrides = array('test_form' => false);
						$uploaded_file = wp_handle_upload($_FILES[$meta_box_with_value], $upload_overrides);
						
						if(isset($uploaded_file['file'])) {
							$file_name_and_location = $uploaded_file['file'];
							$file_title_for_media_library = 'product_photo_for_post_'.$post_id;
							$attachment = array(
                                'post_mime_type' => $uploaded_file_type,
                                'post_title' => 'Uploaded image ' . addslashes($file_title_for_media_library),
                                'post_content' => '',
                                'post_status' => 'inherit'
                            );
							$attach_id = wp_insert_attachment($attachment, $file_name_and_location, $post_id);
                            require_once(ABSPATH . "wp-admin" . '/includes/image.php');
                            $attach_data = wp_generate_attachment_metadata($attach_id, $file_name_and_location);
                            wp_update_attachment_metadata($attach_id, $attach_data);
							
							$existing_uploaded_image = $meta_data_array[$meta_box_with_value];
                            if(is_numeric($existing_uploaded_image)) {
                                wp_delete_attachment($existing_uploaded_image);
                            }
							$meta_data_array[$meta_box_with_value] = $attach_id;
							
						} else {
							$upload_feedback = 'There was a problem with your upload.';
							update_post_meta($post_id,'_wp_basalcart_attached_image_upload_feedback',$upload_feedback);
						}
						
					} else {
						$upload_feedback = 'Please upload only image files (jpg, gif or png).';
                        update_post_meta($post_id,'_wp_basalcart_attached_image_upload_feedback',$upload_feedback);
					}
				}
				update_post_meta($post_id,'wp_basalcart_metadata',$meta_data_array);
				$upload_feedback = false;
				update_post_meta($post_id,'_wp_basalcart_attached_image_upload_feedback',$upload_feedback);
			}
		}
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_render_general_meta_box($post, $metabox) {
		global $post;
		$current_meta_box = $metabox['args']['typeofbox'];
		$get_all_metabox_values = maybe_unserialize(get_post_meta($post->ID,'wp_basalcart_metadata', true));
		$current_meta_box_value = $get_all_metabox_values[$current_meta_box];
		
		if($current_meta_box=='price') { echo "&yen;"; }
		echo '<input type="text" name="wp_basalcart_'.$current_meta_box.'" id="wp_basalcart_'.$current_meta_box.'" value="'.$current_meta_box_value.'" />';
		echo '<input type="hidden" name="wp_basalcart_manual_save_flag" value="true" />';
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_update_general_post_meta_box($post_id, $post) {
		global $post;
		
		$meta_data_array = maybe_unserialize(get_post_meta($post_id,'wp_basalcart_metadata', true));
		$meta_box_array = array('price','sku');
		
		foreach($meta_box_array as $single_meta_box) {
			if(isset($_POST['wp_basalcart_'.$single_meta_box]) && isset($_POST['wp_basalcart_manual_save_flag'])) {
				if($meta_data_array[$single_meta_box]!=$_POST['wp_basalcart_price']) {
					switch($single_meta_box) {
						case 'price':
							if(preg_match('/(\d+)/', $_POST['wp_basalcart_'.$single_meta_box], $matches)) {
								$meta_data_array[$single_meta_box]=$matches[1];
							}
							break;
						case 'sku':
							if(preg_match('/(\w+)/', $_POST['wp_basalcart_'.$single_meta_box], $matches)) {
								$meta_data_array[$single_meta_box]=$matches[1];
							}
							break;
						default:
							$meta_data_array[$single_meta_box]=0;
					}
					update_post_meta($post_id,'wp_basalcart_metadata',$meta_data_array);
				}
			}
		}
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_set_product_columns($columns) {
		return array(
			'cb' => '<input type="checkbox" />',
			'title' => __('Title'),
			'thumbnail' => __('Thumbnail'),
			'rating' => __('Rating'),
			'price' => __('Price'),
			'sku' => __('SKU'),
			'wp_basalcart_product_category' => ('Category'),
			'wp_basalcart_product_tag' => ('Tags')
		);
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_add_columns_value($column_name, $post_id) {
		global $wpdb;
		$get_all_metabox_values = maybe_unserialize(get_post_meta($post_id,'wp_basalcart_metadata', true));
		switch($column_name) {
			case 'thumbnail':
				$thumbnail_id = $get_all_metabox_values['mainimage'];
				if(is_numeric($thumbnail_id)) {
					$arr_existing_image = wp_get_attachment_image_src($thumbnail_id, 'thumbnail');
					$existing_image_url = $arr_existing_image[0];
					echo '<img style="width:80px;height:auto;" src="' . $existing_image_url . '" />';
				}
				break;
			case 'rating':
				$sql = 'SELECT rating FROM '.$wpdb->prefix.'basalcart_product_rating WHERE productid='.$post_id;
				$rating = $wpdb->get_var($wpdb->prepare($sql));
				$output = '';
				if(!empty($rating)) {
					for($i=0;$i<5;$i++) {
						if($rating>$i) {
							$output .= '<img src="'.plugins_url('images/rating_star_on.png', __FILE__).'" alt="" />';
						} else {
							$output .= '<img src="'.plugins_url('images/rating_star_off.png', __FILE__).'" alt="" />';
						}
					}
				}
				echo $output;
				break;
			
			case 'price':
				echo $get_all_metabox_values['price'];
				break;
			case 'sku':
				echo $get_all_metabox_values['sku'];
				break;
			case 'wp_basalcart_product_category':
				$category = get_the_terms($post_id, 'wp_basalcart_product_category');
				if($category) {
					foreach($category as $k=>$v) {
						$categorynameonly[] = $v->name;
					}
					echo implode(', ', $categorynameonly);
				}
				break;
			case 'wp_basalcart_product_tag':
				$tags = get_the_terms($post_id, 'wp_basalcart_product_tag');
				if($tags) {
					foreach($tags as $k=>$v) {
						$tagnameonly[] = $v->name;
					}
					echo implode(', ', $tagnameonly);
				}
				break;
		}
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_register_sortable_columns($columns) {
		$columns['rating'] = 'rating';
		$columns['price'] = 'price';
		$columns['sku'] = 'sku';
		
		return $columns;
	}
	
	
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	///
	///
	///  Methods for Front-end start here
	///
	///
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_init_session() {
		if(!session_id()) {
			session_start();
			if(!isset($_SESSION['wp_basalcart_cart'])) {
				$_SESSION['wp_basalcart_cart'] = array();
			}
		}
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_add_product_to_cart($product_id) {
		$product_id = (int)$product_id;
		if(isset($_SESSION['wp_basalcart_cart'])) {
			if(array_key_exists($product_id, $_SESSION['wp_basalcart_cart'])) {
				$_SESSION['wp_basalcart_cart'][$product_id]['amount']++;
			} else {
				$new_product_array = get_post($product_id, ARRAY_A);
				if($new_product_array['post_type']=='wp_basalcart_product') {
					$new_product_array['amount']=1;
					$_SESSION['wp_basalcart_cart'][$product_id] = $new_product_array;
				}
			}
		}
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_remove_product_from_cart($product_id) {
		$product_id = (int)$product_id;
		if(isset($_SESSION['wp_basalcart_cart'])) {
			if(array_key_exists($product_id, $_SESSION['wp_basalcart_cart'])) {
				if($_SESSION['wp_basalcart_cart'][$product_id]['amount']>1) {
					$_SESSION['wp_basalcart_cart'][$product_id]['amount']--;
				}
				elseif($_SESSION['wp_basalcart_cart'][$product_id]['amount']<=1) {
					unset($_SESSION['wp_basalcart_cart'][$product_id]);
				}
			}
		}
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_add_form_contents_to_session() {
		global $wp_query;
		
		if((int)$wp_query->query_vars['is_checkout_step']==2) {
			if(!isset($_SESSION['wp_basalcart_user'])) {
				$_SESSION['wp_basalcart_user'] = array();
			}
			if(isset($_POST['firstname'])) { $_SESSION['wp_basalcart_user']['firstname'] = $_POST['firstname']; } else { $_SESSION['wp_basalcart_user']['firstname'] = ''; }
			if(isset($_POST['lastname'])) { $_SESSION['wp_basalcart_user']['lastname'] = $_POST['lastname']; } else { $_SESSION['wp_basalcart_user']['lastname'] = ''; }
			if(isset($_POST['zipcode'])) { $_SESSION['wp_basalcart_user']['zipcode'] = $_POST['zipcode']; } else { $_SESSION['wp_basalcart_user']['zipcode'] = ''; }
			if(isset($_POST['address'])) { $_SESSION['wp_basalcart_user']['address'] = $_POST['address']; } else { $_SESSION['wp_basalcart_user']['address'] = ''; }
			if(isset($_POST['address2'])) { $_SESSION['wp_basalcart_user']['address2'] = $_POST['address2']; } else { $_SESSION['wp_basalcart_user']['address2'] = ''; }
			if(isset($_POST['phone'])) { $_SESSION['wp_basalcart_user']['phone'] = $_POST['phone']; } else { $_SESSION['wp_basalcart_user']['phone'] = ''; }
			if(isset($_POST['email'])) { $_SESSION['wp_basalcart_user']['email'] = $_POST['email']; } else { $_SESSION['wp_basalcart_user']['email'] = ''; }
			if(isset($_POST['username'])) { $_SESSION['wp_basalcart_user']['username'] = $_POST['username']; } else { $_SESSION['wp_basalcart_user']['username'] = ''; }
			if(isset($_POST['password'])) { $_SESSION['wp_basalcart_user']['password'] = md5($_POST['password']); } else { $_SESSION['wp_basalcart_user']['password'] = ''; }
		}
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_move_cart_session_to_db() {
		global $wp_query;
		global $wpdb;
		
		if((int)$wp_query->query_vars['is_checkout_step']==3) {
			if(isset($_SESSION['wp_basalcart_cart']) && isset($_SESSION['wp_basalcart_user'])) {
				$product_array = array();
				$totalprice = 0;
				foreach($_SESSION['wp_basalcart_cart'] as $product_key=>$product_info) {
					$get_all_metabox_values = maybe_unserialize(get_post_meta($product_key,'wp_basalcart_metadata', true));
					$product_array[] = array('id'=>$product_info['ID']);
					$totalprice = $totalprice + ((int)$get_all_metabox_values["price"]*(int)$product_info["amount"]);
				}
				$product_array = maybe_serialize($product_array);
				
				$sql = 'SELECT id FROM '.$wpdb->prefix.'basalcart_customers ORDER BY id DESC LIMIT 1';
				$userids = $wpdb->get_results($sql, ARRAY_A);
				$user_id = 0;
				foreach($userids as $userid) {
					(int)$user_id = $userid['id'];
				}
				if(is_numeric($user_id)) {
					$user_id++;
				} else {
					$user_id=0;
				}
				
				$wpdb->insert(
					$wpdb->prefix.'basalcart_orders',
					array(
						'products' => $product_array,
						'totalprice' => $totalprice,
						'userid' => $user_id,
						'date' => current_time('mysql'),
						'processed' => 0,
						'deleted' => 0
					),
					array(
						'%s',
						'%d',
						'%d',
						'%s',
						'%d',
						'%d'
					)
				);
			}
		}
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_move_user_session_to_db() {
		global $wp_query;
		global $wpdb;
		
		if((int)$wp_query->query_vars['is_checkout_step']==3) {
			if(isset($_SESSION['wp_basalcart_cart']) && isset($_SESSION['wp_basalcart_user'])) {
				$user_id = $_SESSION['wp_basalcart_user']['username'];
				if(empty($user_id)) {
					$sql = 'SELECT id FROM '.$wpdb->prefix.'basalcart_customers ORDER BY id DESC LIMIT 1';
					$userids = $wpdb->get_results($sql, ARRAY_A);
					$user_id = 0;
					foreach($userids as $userid) {
						(int)$user_id = $userid['id'];
					}
					if(is_numeric($user_id)) {
						$user_id++;
					} else {
						$user_id=0;
					}
					$user_id='Guest'.$user_id;
				}
				
				
				$wpdb->insert(
					$wpdb->prefix.'basalcart_customers',
					array(
						'firstname' => $_SESSION['wp_basalcart_user']['firstname'],
						'lastname' => $_SESSION['wp_basalcart_user']['lastname'],
						'firstnamekana' => $_SESSION['wp_basalcart_user']['firstname'],
						'lastnamekana' => $_SESSION['wp_basalcart_user']['lastname'],
						'zipcode' => $_SESSION['wp_basalcart_user']['zipcode'],
						'address' => $_SESSION['wp_basalcart_user']['address'],
						'detailedaddress' => $_SESSION['wp_basalcart_user']['address2'],
						'phonenumber' => $_SESSION['wp_basalcart_user']['phone'],
						'email' => $_SESSION['wp_basalcart_user']['email'],
						'purchasetimes' => 1,
						'user_login' => $user_id,
						'user_pass' => $_SESSION['wp_basalcart_user']['password'],
					),
					array(
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%d',
						'%s',
						'%s'
					)
				);
			}
		}
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_insert_cross_sales_to_db() {
		global $wp_query;
		global $wpdb;
		
		if((int)$wp_query->query_vars['is_checkout_step']==3) {
			if(isset($_SESSION['wp_basalcart_cart']) && isset($_SESSION['wp_basalcart_user'])) {
				$product_array = array();
				foreach($_SESSION['wp_basalcart_cart'] as $product_key=>$product_info) {
					foreach($_SESSION['wp_basalcart_cart'] as $product_key2=>$product_info2) {
						if($product_key!=$product_key2) {
							$sql = 'SELECT amount FROM '.$wpdb->prefix.'basalcart_also_bought WHERE productid='.$product_key.' AND alsobought='.$product_key2;
							$row_exists = $wpdb->get_var($wpdb->prepare($sql));
							if($row_exists) {
								$sql = 'UPDATE wp_basalcart_also_bought SET amount=amount+1 WHERE productid='.$product_key.' AND alsobought='.$product_key2;
							} else {
								$sql = 'INSERT INTO '.$wpdb->prefix.'basalcart_also_bought (productid,alsobought,amount) VALUES ('.$product_key.','.$product_key2.',1)';
							}
							$wpdb->query($wpdb->prepare($sql));
						}
					}
				}
			}
		}
	}
	
	
	
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	///
	///
	///  Ajax Functions
	///
	///
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_generate_js_header() {
		//header( 'Content-type: application/javascript' );
		header( 'Content-type: text/javascript' );
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_output_dynamic_js_file() {
		$this->wp_basalcart_generate_js_header();
		echo "
		function wp_basalcart_ajax_call(id, method, parameter) {
            var id = id;
			if(id == false || id == 'false') {
				id = 0;
			}
			else if(id == true || id == 'true') {
				id = 1;
			}
			parseInt(id);
			var method = String(method);
			var parameter = parameter;
			if(typeof parameter === 'undefined' && typeof parameter !== 'boolean' && typeof parameter !== 'number') {
				parameter=false;
			}
            if(typeof id !== 'undefined' && typeof id === 'number') {
				if(typeof method !== 'undefined' && typeof method === 'string') {
					$.post(ajaxurl, { 'action':'call_response', 'id':id, 'method':method, 'parameter':parameter  } );
				}
            }
        }
		";
		
		echo "
		function wp_basalcart_validate_form(selectedInput,submitForm) {
			var globalError = 0;
			var generalRegex = /^[a-zA-Z0-9-]+$/;
			var zipRegex = /^[0-9]{3}-[0-9]{4}$/;
			var addressRegex = /^[\sa-zA-Z0-9\.,-]+$/;
			var phoneRegex = /^([0-9]{2,3})-([0-9]{3})-([0-9]{3})$/;
			var emailRegex = /^([a-z0-9_\.-]+)@([\da-z\.-]+)\.([a-z\.]{2,6})$/;
			var userRegex = /^[a-zA-Z0-9_-]{3,16}$/;
			var passRegex = /^[a-zA-Z0-9_-]{6,18}$/;
			
			var inputElements = new Array();
			if(typeof selectedInput !== 'undefined' && typeof submitForm === 'undefined') {
				inputElements[0] = selectedInput;
			} else {
				$('#wp_basalcart_checkout_form .inputfield').each(function(index){
					inputElements[index] = $(this);
				});
			}
			
			for(var i=0;i<inputElements.length;i++) {
				var error_msg=0;
				
				
				
				if(!inputElements[i].hasClass('required') && inputElements[i].val()==='') {
					//this is not an error
				}
				else if(inputElements[i].hasClass('required') && inputElements[i].val()==='') {
					error_msg=1;
					globalError++;
				}
				else if(inputElements[i].hasClass('zipregex')) {
					if(!zipRegex.test(inputElements[i].val())) {
						error_msg=1;
						globalError++;
					}
				}
				else if(inputElements[i].hasClass('addressregex')) {
					if(!addressRegex.test(inputElements[i].val())) {
						error_msg=1;
						globalError++;
					}
				}
				else if(inputElements[i].hasClass('phoneregex')) {
					if(!phoneRegex.test(inputElements[i].val())) {
						error_msg=1;
						globalError++;
					}
				}
				else if(inputElements[i].hasClass('emailregex')) {
					if(!emailRegex.test(inputElements[i].val())) {
						error_msg=1;
						globalError++;
					}
				}
				else if(inputElements[i].hasClass('userregex')) {
					if(!userRegex.test(inputElements[i].val())) {
						error_msg=1;
						globalError++;
					}
				}
				else if(inputElements[i].hasClass('passregex')) {
					if(!passRegex.test(inputElements[i].val())) {
						error_msg=1;
						globalError++;
					}
				}
				else if(!generalRegex.test(inputElements[i].val())) {
					error_msg=1;
					globalError++;
				}
				
				if(error_msg>0) {
					$('#'+inputElements[i].attr('name')+'-status').css({ 'color' : '#CC0000' }).text(' Not Validated!');
				} else {
					$('#'+inputElements[i].attr('name')+'-status').css({ 'color' : '#00CC00' }).text(' Ok!');
				}
			}
			
			if(globalError==0 && typeof submitForm !== 'undefined') {
				return true;
			} else {
				return false;
			}
		}
		";
		
		echo "
		$().ready(function() {
			$('#wp_basalcart_checkout_form input').keyup(function(e) {
				wp_basalcart_validate_form($(this));
			});
			
			$('#wp_basalcart_checkout_form').on('submit', function() { 
				if(!wp_basalcart_validate_form('',true)) {
					return false;
				}
			});
			
			$('#maincontent').on({
			mouseenter: function() {
				var thisParentId = $(this).parents('div').attr('id');
				var currentIndex = parseInt($(this).index());
				while(currentIndex>=0) {
					$('#'+thisParentId+' li.product-rating-can-vote').eq(currentIndex).addClass('product-rating-hightlight');
					currentIndex--;
				}
				
			},
			mouseleave: function() {
				$('.product-rating-star').removeClass('product-rating-hightlight');
			},
			click: function() {
				var currentIndex = parseInt($(this).index()+1);
				var currentId = parseInt($(this).parents('div').attr('id').replace('wp_basalcart_product_rating_id_',''));
				wp_basalcart_ajax_call(currentId,'wp_basalcart_product_rating',currentIndex);
			}}, '.product-rating-can-vote');
			
		});
		";
		
		exit();
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_call_response(){
		$this->wp_basalcart_generate_js_header();
		
		if(isset($_POST['id']) && !empty($_POST['id'])) {
			$id = (int)$_POST['id'];
			if(isset($_POST['method']) && !empty($_POST['method'])) {
				$method = (string)$_POST['method'];
				if($_POST['parameter'] == 'true') { $parameter = true; }
				else if($_POST['parameter'] == 'false') { $parameter = false; }
				else { $parameter = $_POST['parameter']; }
				switch($method) {
					case 'wp_basalcart_add_to_cart':
						$this->wp_basalcart_add_product_to_cart($id);
						$this->wp_basalcart_get_cart_content();
						break;
					case 'wp_basalcart_remove_from_cart':
						$this->wp_basalcart_remove_product_from_cart($id);
						$this->wp_basalcart_get_cart_content();
						break;
					case 'wp_basalcart_processed_order':
						global $wpdb;
						if($parameter) {
							$checkbox = '1';
						} else {
							$checkbox = '0';
						}
						$wpdb->update(
							$wpdb->prefix.'basalcart_orders',
							array('processed'=>$checkbox),
							array('ID'=>$_POST['id']),
							array('%d')
						);
						break;
					case 'wp_basalcart_product_rating':
						global $wpdb;
						
						$sql = 'SELECT ipnum FROM '.$wpdb->prefix.'basalcart_product_rating WHERE productid='.$id;
						$ips = $wpdb->get_var($wpdb->prepare($sql));
						$ips = maybe_unserialize($ips);
						if(!is_array($ips)) {
							$ips = array();
						}
						$ips[] = $_SERVER['REMOTE_ADDR'];
						$ips = array_unique($ips);
						$ips = maybe_serialize($ips);
						$sql = 'INSERT INTO '.$wpdb->prefix.'basalcart_product_rating (ipnum,productid,rated,times,rating) VALUES (\''.$ips.'\','.$id.','.$parameter.',1,'.$parameter.') ON DUPLICATE KEY UPDATE ipnum=\''.$ips.'\',rated=rated+'.$parameter.',times=times+1,rating=ROUND((rated+'.$parameter.')/(times+1))';
						$wpdb->query($wpdb->prepare($sql));
						
						$this->wp_basalcart_get_product_rating_bar($id,true);
						break;
				}
			} 
		}
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_get_product_rating_bar($id,$ajaxcall=false){
		global $wpdb;
		global $post;
		
		if(!isset($id)) {
			$id = $post->ID;
		} else {
			$id = (int)$id;
		}
		$output = '';
		$sql = 'SELECT * FROM '.$wpdb->prefix.'basalcart_product_rating WHERE productid='.$id.' LIMIT 1';
		$results = $wpdb->get_results($sql, ARRAY_A);
		$output .= '<ul class="product-rating">';
		if(is_array($results) && !empty($results)) {
			foreach($results as $result) {
				$enable_voting=true;
				$ip_array = maybe_unserialize($result['ipnum']);
				if(is_array($ip_array)) {
					if(in_array($_SERVER['REMOTE_ADDR'],$ip_array)) { $enable_voting=false; }
				} else {
					$pos = strpos($ip_array,$_SERVER['REMOTE_ADDR']);
					if($pos !== false) { $enable_voting=false; }
				}
				$rating = $result['rating'];
				for($i=0;$i<5;$i++) {
					$output .= '<li class="product-rating-star';
					if($rating>$i) { $output .= ' product-rating-selected'; }
					if($enable_voting) { $output .= ' product-rating-can-vote'; }
					$output .= '" style="width:20px;height:22px;"';
					$output .= '><img src="'.get_bloginfo('template_directory').'/images/rating-star.png" alt="" /></li>';
				}
			}
		} else {
			for($i=0;$i<5;$i++) {
				$output .= '<li class="product-rating-star product-rating-can-vote" style="width:20px;height:22px;"><img src="'.get_bloginfo('template_directory').'/images/rating-star.png" alt="" /></li>';
			}
		}
		$output .= '</ul>';
		if($ajaxcall) {
			echo '$("#wp_basalcart_product_rating_id_'.$id.'").html("'.addslashes($output).'")';
			exit();
		} else {
			return $output;
		}
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function wp_basalcart_get_cart_content($ajax_call=true){
		$number_of_products = 0;
		
		if(!empty($_SESSION['wp_basalcart_cart'])) {
			$output = '';
			$jsoutput = '';
			$totalprice = 0;
			$output .= '<table>';
			foreach($_SESSION['wp_basalcart_cart'] as $product_in_cart) {
				$get_all_metabox_values = maybe_unserialize(get_post_meta($product_in_cart["ID"],'wp_basalcart_metadata', true));
				$output .= '<tr><td><span class="nolink">'.$product_in_cart["post_title"].'</span></td><td><span class="nolink">&yen;'.$get_all_metabox_values["price"].' x '.$product_in_cart["amount"].'</span></td><td><span class="nolink">&yen;'.((int)$get_all_metabox_values["price"]*(int)$product_in_cart["amount"]).'</span></td><td><span class="nolink narrower"><a href="javascript:void(0)" onclick="wp_basalcart_ajax_call('.(int)$product_in_cart["ID"].',\'wp_basalcart_add_to_cart\')" class="buttons">+</a></span></td><td><span class="nolink narrower"><a href="javascript:void(0)" onclick="wp_basalcart_ajax_call('.(int)$product_in_cart["ID"].',\'wp_basalcart_remove_from_cart\')" class="buttons">-</a></span></td></tr>';
				$totalprice = $totalprice + ((int)$get_all_metabox_values["price"]*(int)$product_in_cart["amount"]);
				$number_of_products = $number_of_products + (int)$product_in_cart["amount"];
			}
			$output .= '<tr class="total-price-row"><td><span class="nolink">&nbsp;</span></td><td><span class="nolink">&nbsp;</span></td><td><span class="nolink">&yen;'.$totalprice.'</span></td><td colspan="2"><a href="'.$this->wp_basalcart_get_link_to_checkout(1).'">Checkout ></a></td></tr>';
			$output .= '</table>';
		}
		
		if($ajax_call) {
			$jsoutput = 'var linkBoxWidth = $("#shopping-cart-title").outerWidth(true);';
			$jsoutput .= '$("#shopping-cart-title > span").animate({ "left" : "-"+linkBoxWidth+"px" }, { duration:300, queue:false, complete:function(){';
			$jsoutput .= '$("#shopping-cart-title > span").html("Shopping Cart ('.$number_of_products.')").css({ "left" : linkBoxWidth+"px" });';
			$jsoutput .= '$("#shopping-cart-title > span").animate({ "left" : 0 }, { duration:300, queue:false, complete:function(){ }});';
			$jsoutput .= '}});';
		}
		
		
		if(!empty($output) && count($_SESSION['wp_basalcart_cart'])>=1) {
			if($ajax_call) {
				echo '$("#shopping-cart-container").html("'.addslashes($output).'");';
				echo $jsoutput;
				exit();
			} else {
				return '<a id="shopping-cart-title" href="javascript:void(0)" class="overflowhidden"><span>Shopping Cart ('.$number_of_products.')</span></a><ul><li id="shopping-cart-container">'.$output.'</li></ul>';
			}
		} else {
			$output = '<table><tr><td><span class="nolink">Your Cart is Empty</span></td></tr></table>';
			if($ajax_call) {
				echo '$("#shopping-cart-container").html("'.addslashes($output).'");';
				echo $jsoutput;
				exit();
			} else {
				return '<a id="shopping-cart-title" href="javascript:void(0)" class="overflowhidden"><span>Shopping Cart ('.$number_of_products.')</span></a><ul><li id="shopping-cart-container">'.$output.'</li></ul>';
			}
		}
	}
}

if(!isset($_GET['wp_basalcart_output_js_file']) && ($_GET['wp_basalcart_output_js_file'] != 'true')):


if(!class_exists('WP_List_Table')){
	require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///
///
///  ORDER PAGE
///
///
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

class wp_basalcart_admin_order_page extends WP_List_Table {
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function __construct(){
		global $status, $page;
		
		parent::__construct(array(
			'singular' => 'order',
			'plural' => 'orders',
			'ajax' => false
		));
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
    function column_default($item, $column_name){
        global $wpdb;
		
		switch($column_name){
			case 'id':
				$actions = array(
					'delete' => sprintf('<a href="?page=%s&action=%s&order=%s&checkbox[0]=%s">Delete</a>',$_REQUEST['page'],'delete',$item['id'],$item['id'])
				);
				return sprintf('%1$s %2$s',
					$item['id'],
					$this->row_actions($actions)
				);
			
			case 'products':
				$output = '';
				$products = maybe_unserialize($item[$column_name]);
				if(is_array($products)) {
					foreach($products as $product) {
						$sql = 'SELECT post_title FROM '.$wpdb->prefix.'posts WHERE ID='.$product['id'];
						$product_name = $wpdb->get_var($wpdb->prepare($sql));
						$output .= '<a href="'.get_bloginfo('home').'/wp-admin/post.php?post='.$product['id'].'&amp;action=edit">'.$product_name.'</a>, ';
					}
				}
				return substr($output,0,-2);
				
			case 'totalprice':
				return "&yen;".$item[$column_name];
				
			case 'userid':
				$output = array();
				$sql = "SELECT id,firstname,lastname FROM ".$wpdb->prefix."basalcart_customers WHERE id=".$item[$column_name]." LIMIT 1";
				$customer_names = $wpdb->get_results($sql, ARRAY_A);
				if(is_array($customer_names)) {
					foreach($customer_names as $customer_name) {
						$output = '<a href="'.get_bloginfo('home').'/wp-admin/admin.php?page=wp_basalcart_admin_customer_page&show_customers_for='.$item['userid'].'">'.$customer_name['firstname'].' '.$customer_name['lastname'].'</a>';
					}
				}
				return $output;
			
			case 'date':
				return $item[$column_name];
			
			case 'processed':
				if(!$item[$column_name]) {
					$output = '<input type="checkbox" name="wp_basalcart_processed_order" value="1" onclick="wp_basalcart_ajax_call('.$item["id"].',this.name,this.checked)" />';
				} else {
					$output = '<input type="checkbox" name="wp_basalcart_processed_order" value="0" onclick="wp_basalcart_ajax_call('.$item["id"].',this.name,this.checked)" checked="checked" />';
				}
				return $output;
				
		}
    }
    
    
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function column_cb($item){
        return sprintf(
            '<input type="checkbox" name="checkbox[]" value="%1$d" />',
            $item['id']
        );
    }
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
    function get_columns(){
        $columns = array(
			'cb' => '<input type="checkbox" />',
			'id' => 'Order',
			'products' => 'Products',
			'totalprice' => 'Price',
			'userid' => 'Customer',
			'date' => 'Date',
			'processed' => 'Processed'
        );
        return $columns;
    }
    
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function get_sortable_columns() {
        $sortable_columns = array(
			'id' => array('id',false),
			'totalprice' => array('totalprice',false),
			'date' => array('date',true),
			'processed' => array('processed',false)
		);
		return $sortable_columns;
	}
    
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function get_bulk_actions() {
		$actions = array(
			'delete' => 'Delete'
		);
		return $actions;
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function process_bulk_action() {
		global $wpdb;
		if('delete' === $this->current_action()) {
			parse_str($_SERVER['QUERY_STRING'], $output);
			foreach($output['checkbox'] as $k=>$v) {
				$wpdb->update(
					$wpdb->prefix.'basalcart_orders',
					array('deleted'=>1),
					array('ID'=>$v),
					array('%d')
				);
			}
		}
	}
    
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
    function prepare_items() {
        global $wpdb;
		$per_page = 10;
        
		$columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        
        $this->_column_headers = array($columns, $hidden, $sortable);
		$this->process_bulk_action();
		$current_page = $this->get_pagenum();
		
		parse_str($_SERVER['QUERY_STRING'], $output);
		
		if(!empty($output['show_orders_for'])) {
			$sql = "SELECT * FROM ".$wpdb->prefix."basalcart_orders WHERE userid=".$output['show_orders_for']." AND deleted=0";
		}
		elseif(!empty($output['orderby']) && !empty($output['order'])) {
			$sql = "SELECT * FROM ".$wpdb->prefix."basalcart_orders WHERE deleted=0 ORDER BY ".$output['orderby']." ".strtoupper($output['order']);
		} else {
			$sql = "SELECT * FROM ".$wpdb->prefix."basalcart_orders WHERE deleted=0";
		}
		$data = $wpdb->get_results($sql, ARRAY_A);
		
		if(is_array($data)) {
			$total_items = count($data);
			$data = array_slice($data,(($current_page-1)*$per_page),$per_page);
			$this->items = $data;
		}
		
		$this->set_pagination_args(array(
			'total_items' => $total_items,
			'per_page' => $per_page,
			'total_pages' => ceil($total_items/$per_page)
		));
	}
}

function wp_basalcart_add_order_menu_page(){
	add_menu_page('Orders', 'Orders', 'activate_plugins', 'wp_basalcart_admin_order_page', 'wp_basalcart_order_page_content', plugins_url('images/admin_side_orange.jpg', __FILE__), 27);
}

add_action('admin_menu', 'wp_basalcart_add_order_menu_page');

function wp_basalcart_order_page_content(){	
    $wp_basalcart_order_page_table = new wp_basalcart_admin_order_page();
    $wp_basalcart_order_page_table->prepare_items();
?>
    <div class="wrap">
        <div id="icon-users" class="icon32" style="background:transparent url('<?php echo plugins_url('images/admin_top_orange.jpg', __FILE__);?>') no-repeat top left;"><br /></div>
        <h2>Orders</h2>
        <div style="background:#ECECEC;border:1px solid #CCC;padding:0 10px;margin-top:5px;border-radius:5px;-moz-border-radius:5px;-webkit-border-radius:5px;">
            <?php
				parse_str($_SERVER['QUERY_STRING'], $output);
				if(!empty($output['show_orders_for'])) {
					echo '<p>Showing results for Order: <tt>'.$output['show_orders_for'].'</tt></p>';
				} else {
					echo '<p>Showing all Orders</p>';
				}
			?>
        </div>
        <form id="order-filter" method="get">
            <input type="hidden" name="page" value="<?php echo $_REQUEST['page'];?>" />
            <?php $wp_basalcart_order_page_table->display();?>
        </form>
    </div>
    <?php
}


////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///
///
///  CUSTOMER PAGE
///
///
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
class wp_basalcart_admin_customer_page extends WP_List_Table {
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function __construct(){
		global $status, $page;
		
		parent::__construct(array(
			'singular' => 'customer',
			'plural' => 'customers',
			'ajax' => false
		));
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
    function column_default($item, $column_name){
		switch($column_name){
			case 'firstname':
				return $item['firstname']." ".$item['lastname'];
			case 'address':
				return $item['zipcode']." ".$item['address']." ".$item['detailedaddress'];
			case 'phonenumber':
			case 'email':
				return $item[$column_name];
			case 'purchasetimes':
				$output = '<a href="'.get_bloginfo('home').'/wp-admin/admin.php?page=wp_basalcart_admin_order_page&show_orders_for='.$item['id'].'">'.$item[$column_name].'</a>';
				return $output;
		}
    }
        
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
    function get_columns(){
        $columns = array(
			'firstname' => 'Name',
			'lastname' => 'Lastname',
			'zipcode' => 'Zipcode',
			'address' => 'Address',
			'detailedaddress' => 'Detailed Address',
			'phonenumber' => 'Phonenumber',
			'email' => 'Email',
			'purchasetimes' => 'Purchases',
			'user_login' => 'Username',
        );
        return $columns;
    }
    
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function get_sortable_columns() {
        $sortable_columns = array(
			'firstname' => array('firstname',true),
			'address' => array('address',false),
			'phonenumber' => array('phonenumber',false),
			'email' => array('email',false),
			'purchasetimes' => array('purchasetimes',false),
			'user_login' => array('user_login',false)
		);
		return $sortable_columns;
	}
    
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
    function prepare_items() {
        global $wpdb;
		$per_page = 10;
        
		$columns = $this->get_columns();
        $hidden = array('lastname','zipcode','detailedaddress','user_login');
        $sortable = $this->get_sortable_columns();
        
        $this->_column_headers = array($columns, $hidden, $sortable);
		$current_page = $this->get_pagenum();
		
		parse_str($_SERVER['QUERY_STRING'], $output);
		
		if(!empty($output['show_customers_for'])) {
			$sql = "SELECT * FROM ".$wpdb->prefix."basalcart_customers WHERE id=".$output['show_customers_for'];
		}
		elseif(!empty($output['orderby']) && !empty($output['order'])) {
			$sql = "SELECT * FROM ".$wpdb->prefix."basalcart_customers ORDER BY ".$output['orderby']." ".strtoupper($output['order']);
		} else {
			$sql = "SELECT * FROM ".$wpdb->prefix."basalcart_customers";
		}
		$data = $wpdb->get_results($sql, ARRAY_A);
		
		if(is_array($data)) {
			$total_items = count($data);
			$data = array_slice($data,(($current_page-1)*$per_page),$per_page);
			$this->items = $data;
		}
		
		$this->set_pagination_args(array(
			'total_items' => $total_items,
			'per_page' => $per_page,
			'total_pages' => ceil($total_items/$per_page)
		));
	}
}


function wp_basalcart_add_customer_menu_page(){
	add_menu_page('Customers', 'Customers', 'activate_plugins', 'wp_basalcart_admin_customer_page', 'wp_basalcart_customer_page_content', plugins_url('images/admin_side_orange.jpg', __FILE__), 29);
}

add_action('admin_menu', 'wp_basalcart_add_customer_menu_page');

function wp_basalcart_customer_page_content(){
	$wp_basalcart_customer_page_table = new wp_basalcart_admin_customer_page();
    $wp_basalcart_customer_page_table->prepare_items();
?>
    <div class="wrap">
        <div id="icon-users" class="icon32" style="background:transparent url('<?php echo plugins_url('images/admin_top_orange.jpg', __FILE__);?>') no-repeat top left;"><br /></div>
        <h2>Customers</h2>
        <div style="background:#ECECEC;border:1px solid #CCC;padding:0 10px;margin-top:5px;border-radius:5px;-moz-border-radius:5px;-webkit-border-radius:5px;">
            <?php
				parse_str($_SERVER['QUERY_STRING'], $output);
				if(!empty($output['show_customers_for'])) {
					echo '<p>Showing results for: <tt>Guest'.$output['show_orders_for'].'</tt></p>';
				} else {
					echo '<p>Showing all Customers</p>';
				}
			?>
        </div>
        <form id="order-filter" method="get">
            <input type="hidden" name="page" value="<?php echo $_REQUEST['page'];?>" />
            <?php $wp_basalcart_customer_page_table->display();?>
        </form>
    </div>
    <?php
}


	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	///
	///
	///  CROSS SALES PAGE
	///
	///
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
class wp_basalcart_admin_cross_sales_page extends WP_List_Table {
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function __construct(){
		global $status, $page;
		
		parent::__construct(array(
			'singular' => 'crosssale',
			'plural' => 'crosssales',
			'ajax' => false
		));
	}
	
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
    function column_default($item, $column_name){
		global $wpdb;
		switch($column_name){
			case 'productid':
			case 'alsobought':
				$sql = 'SELECT post_title FROM '.$wpdb->prefix.'posts WHERE ID='.$item[$column_name];
				$product_name = $wpdb->get_var($wpdb->prepare($sql));
				$output = '<a href="'.get_bloginfo('home').'/wp-admin/post.php?post='.$item[$column_name].'&amp;action=edit">'.$product_name.'</a>';
				return $output;
			case 'amount':
				return $item[$column_name];
		}
    }
        
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
    function get_columns(){
        $columns = array(
			'productid' => 'Product',
			'alsobought' => 'Cross-sales',
			'amount' => 'Amount',
		);
		return $columns;
    }
    
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
	function get_sortable_columns() {
        $sortable_columns = array(
			'productid' => array('productid',true),
			'alsobought' => array('alsobought',false),
			'amount' => array('amount',false),
		);
		return $sortable_columns;
	}
    
	////////////////////////////////////////////////
	///
	///
	///
	////////////////////////////////////////////////
	
    function prepare_items() {
        global $wpdb;
		$per_page = 10;
        
		$columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        
        $this->_column_headers = array($columns, $hidden, $sortable);
		$current_page = $this->get_pagenum();
		
		parse_str($_SERVER['QUERY_STRING'], $output);
		
		if(!empty($output['orderby']) && !empty($output['order'])) {
			$sql = "SELECT * FROM ".$wpdb->prefix."basalcart_also_bought ORDER BY ".$output['orderby']." ".strtoupper($output['order']);
		} else {
			$sql = "SELECT * FROM ".$wpdb->prefix."basalcart_also_bought";
		}
		$data = $wpdb->get_results($sql, ARRAY_A);
		
		if(is_array($data)) {
			$total_items = count($data);
			$data = array_slice($data,(($current_page-1)*$per_page),$per_page);
			$this->items = $data;
		}
		
		$this->set_pagination_args(array(
			'total_items' => $total_items,
			'per_page' => $per_page,
			'total_pages' => ceil($total_items/$per_page)
		));
	}
}

function wp_basalcart_add_cross_sales_menu_page(){
	add_menu_page('Cross-sales', 'Cross-sales', 'activate_plugins', 'wp_basalcart_admin_cross_sales_page', 'wp_basalcart_cross_sales_page_content', plugins_url('images/admin_side_orange.jpg', __FILE__), 31);
}

add_action('admin_menu', 'wp_basalcart_add_cross_sales_menu_page');

function wp_basalcart_cross_sales_page_content(){	
    $wp_basalcart_cross_sales_page_table = new wp_basalcart_admin_cross_sales_page();
    $wp_basalcart_cross_sales_page_table->prepare_items();
?>
    <div class="wrap">
        <div id="icon-users" class="icon32" style="background:transparent url('<?php echo plugins_url('images/admin_top_orange.jpg', __FILE__);?>') no-repeat top left;"><br /></div>
        <h2>Cross-sales</h2>
        <div style="background:#ECECEC;border:1px solid #CCC;padding:0 10px;margin-top:5px;border-radius:5px;-moz-border-radius:5px;-webkit-border-radius:5px;">
            <p>Showing all Cross-sales</p>
        </div>
        <form id="order-filter" method="get">
            <input type="hidden" name="page" value="<?php echo $_REQUEST['page'];?>" />
            <?php $wp_basalcart_cross_sales_page_table->display();?>
        </form>
    </div>
    <?php
}


endif;

$wp_basalcart = new wp_basalcart();

register_activation_hook(__FILE__, array('wp_basalcart', 'wp_basalcart_initialize_plugin'));

?>