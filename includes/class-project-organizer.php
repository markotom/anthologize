<?php

if ( !class_exists( 'Anthologize_Project_Organizer' ) ) :

class Anthologize_Project_Organizer {

	var $project_id;

	/**
	 * The project organizer. Git 'er done
	 */
	function anthologize_project_organizer ( $project_id ) {

		$this->project_id = $project_id;

		$project = get_post( $project_id );

		$this->project_name = $project->post_title;

	}

	function display() {

		if ( isset( $_POST['new_item'] ) )
			$this->add_item_to_part( $_POST['item_id'], $_POST['part_id'] );

		if ( isset( $_POST['new_part'] ) )
			$this->add_new_part( $_POST['new_part_name'] );

		if ( isset( $_GET['move_up'] ) )
			$this->move_up( $_GET['move_up'] );

		if ( isset( $_GET['move_down'] ) )
			$this->move_down( $_GET['move_down'] );

		if ( isset( $_GET['remove'] ) )
			$this->remove_item( $_GET['remove'] );

		?>
		<div class="wrap">

			<h2><?php echo $this->project_name ?></h2>

			<?php $this->list_existing_parts() ?>

			<h3>New Parts</h3>
			<p>Wanna create a new part? You know you do.</p>
			<form action="" method="post">
				<input type="text" name="new_part_name" />
				<input type="submit" name="new_part" value="New Part" />
			</form>


			<br /><br />
			<p>See the *actual* project at <a href="http://mynameinklingon.org">mynameinklingon.org</a></p>

		</div>
		<?php

	}

	function add_item_to_part( $item_id, $part_id ) {
		global $wpdb;

		if ( !(int)$last_item = get_post_meta( $part_id, 'last_item', true ) )
			$last_item = 0;

		$last_item++;
		$post = get_post( $item_id );

		$args = array(
		  'menu_order' => $last_item,
		  'comment_status' => $post->comment_status,
		  'ping_status' => $post->ping_status,
		  'pinged' => $post->pinged,
		  'post_author' => $post->post_author,
		  'post_content' => $post->post_content,
		  'post_date' => $post->post_date,
		  'post_date_gmt' => $post->post_date_gmt,
		  'post_excerpt' => $post->post_excerpt,
		  'post_parent' => $part_id,
		  'post_password' => $post->post_password,
		  'post_status' => $post->post_status, // todo: yes?
		  'post_title' => $post->post_title,
		  'post_type' => 'library_items',
		  'to_ping' => $post->to_ping, // todo: tags and categories
		);

		$imported_item_id = wp_insert_post( $args );

		// Author data
		$user = get_userdata( $post->post_author );
		$author_name = $user->display_name;
		$author_name_array = array( $author_name );

		update_post_meta( $imported_item_id, 'author_name', $author_name );
		update_post_meta( $imported_item_id, 'author_name_array', $author_name_array );

		// Store the menu order of the last item to enable easy moving later on
		update_post_meta( $part_id, 'last_item', $last_item );
	}

	function add_new_part( $part_name ) {
		if ( !(int)$last_item = get_post_meta( $this->project_id, 'last_item', true ) )
			$last_item = 0;

		$last_item++;

		$args = array(
		  'post_title' => $part_name,
		  'post_type' => 'parts',
		  'post_status' => 'publish',
		  'post_parent' => $this->project_id
		);

		$part_id = wp_insert_post( $args );

		// Store the menu order of the last item to enable easy moving later on
		update_post_meta( $this->project, 'last_item', $last_item );
	}

	function list_existing_parts() {

		//echo 'post_type=parts&order=ASC&post_parent=' . $this->project_id; die();

		query_posts( 'post_type=parts&order=ASC&orderby=menu_order&post_parent=' . $this->project_id );

		if ( have_posts() ) {
			while ( have_posts() ) {
				the_post();

				$part_id = get_the_ID();

				?>
					<div class="part" id="part-<?php echo $part_id ?>">
						<h3><a href="admin.php?page=anthologize&action=edit&project_id=<?php echo $this->project_id ?>&move_up=<?php echo $part_id ?>">&uarr;</a> <a href="admin.php?page=anthologize&action=edit&project_id=<?php echo $this->project_id ?>&move_down=<?php echo $part_id ?>">&darr;</a> <?php the_title() ?> <small><a href="admin.php?page=anthologize&action=edit&project_id=<?php echo $this->project_id ?>&remove=<?php the_ID() ?>" class="remove"><?php _e( 'Remove', 'anthologize' ) ?></a></small></h3>

						<?php $this->get_part_items( $part_id ) ?>

						<form action="" method="post">
							<select name="item_id">
								<?php $this->get_posts_as_option_list( $part_id ) ?>
							</select>
							<input type="submit" name="new_item" value="Add Item" />
							<input type="hidden" name="part_id" value="<?php echo $part_id ?>" />
						</form>
					</div>



				<?php
			}
		} else {
			echo "no";
		}

		wp_reset_query();
	}

	function get_posts_as_option_list( $part_id ) {
		global $wpdb;

		$items = get_post_meta( $part_id, 'items', true );

		$item_query = new WP_Query( 'post_type=items&post_parent=' . $part_id );

//		print_r($item_query->query());

		$sql = "SELECT id, post_title FROM wp_posts WHERE post_type = 'page' OR post_type = 'post' OR post_type = 'imported_items'";
		$ids = $wpdb->get_results($sql);

		$counter = 0;
		foreach( $ids as $id ) {
			if ( in_array( $id->id, $items ) || array_key_exists( $id->id, $items ) ) // Todo: adjust so that it references parent stuff
				continue;

			echo '<option value="' . $id->id . '">' . $id->post_title . '</option>';
			$counter++;
		}

		if ( !$counter )
			echo '<option disabled="disabled">Sorry, no content to add</option>';

	}


	function get_part_items( $part_id ) {
		$items = get_post_meta( $part_id, 'items', true );

		//echo "<pre>";
		//print_r($items); die();
		//if ( empty( $items ) )
		//	return;

		$args = array(
			'post_parent' => $part_id,
			'post_type' => 'library_items',
			'posts_per_page' => -1,
			'orderby' => 'menu_order',
			'order' => ASC
		);

		$items_query = new WP_Query( $args );
		$items_query->query();

		if ( $items_query->have_posts() ) {

			echo "<ol>";

			while ( $items_query->have_posts() ) : $items_query->the_post();

				$this->display_item();

			endwhile;

			echo "</ol>";

		}

	}

	function move_up( $id ) {
		global $wpdb;

		$post = get_post( $id );
		$my_menu_order = $post->menu_order;

		$little_brother = 0;
		$minus = 0;

		while ( !$big_brother ) {
			$minus++;

			// Find the big brother
			$big_brother_q = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_parent = %d AND menu_order = %d LIMIT 1", $post->post_parent, $my_menu_order-$minus );

			$bb = $wpdb->get_results( $big_brother_q, ARRAY_N );
			$big_brother = $bb[0][0];
		}

		// Downgrade the big brother
		$big_brother_q = $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET menu_order = %d WHERE ID = %d", $my_menu_order, $big_brother ) );

		// Upgrade self
		$little_brother_q = $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET menu_order = %d WHERE ID = %d", $my_menu_order-$minus, $id ) );
	}

	function move_down( $id ) {
		global $wpdb;

		$post = get_post( $id );
		$my_menu_order = $post->menu_order;

		$little_brother = 0;
		$plus = 0;

		while ( !$little_brother ) {
			$plus++;

			// Find the little brother
			$little_brother_q = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_parent = %d AND menu_order = %d LIMIT 1", $post->post_parent, $my_menu_order+$plus );

			$lb = $wpdb->get_results( $little_brother_q, ARRAY_N );
			$little_brother = $lb[0][0];
		}

		// Upgrade the little brother
		$little_brother_q = $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET menu_order = %d WHERE ID = %d", $my_menu_order, $little_brother ) );

		// Downgrade self
		$big_brother_q = $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET menu_order = %d WHERE ID = %d", $my_menu_order+$plus, $id ) );
	}

	function remove_item( $id ) {
		$post = get_post( $id );


		// Git ridda the post
		wp_delete_post( $id );
	}

	function display_item() {
		global $post;

	?>

		<li> <?php echo $author_name ?>
			<input type="checkbox" />

			<a href="admin.php?page=anthologize&action=edit&project_id=<?php echo $this->project_id ?>&move_up=<?php the_ID() ?>">&uarr;</a> <a href="admin.php?page=anthologize&action=edit&project_id=<?php echo $this->project_id ?>&move_down=<?php the_ID() ?>">&darr;</a>

			<?php the_title() ?> - <a href="post.php?post=<?php the_ID() ?>&action=edit"><?php _e( 'Edit', 'anthologize' ) ?></a> <a href="admin.php?page=anthologize&action=edit&project_id=<?php echo $this->project_id ?>&remove=<?php the_ID() ?>" class="confirm"><?php _e( 'Remove', 'anthologize' ) ?></a>
		</li>
	<?php
	}

}

endif;

?>