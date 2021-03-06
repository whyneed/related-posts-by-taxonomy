<?php
/**
 * Tests for the widget in /includes/widget.php
 */
class KM_RPBT_Cache_Tests extends KM_RPBT_UnitTestCase {

	private $args = null;

	private $plugin;

	function tearDown() {
		// use tearDown for WP < 4.0
		remove_filter( 'related_posts_by_taxonomy_cache', '__return_true' );
	}


	function setup_cache() {
		// Activate cache
		add_filter( 'related_posts_by_taxonomy_cache', '__return_true' );

		$plugin = km_rpbt_plugin();
		if ( $plugin ) {
			$plugin->_setup();
			$this->plugin = $plugin;
		}
	}

	/**
	 * Test if cache is enabled by using the filter.
	 *
	 * @depends KM_RPBT_Functions_Tests::test_km_rpbt_plugin
	 */
	function test_cache_setup() {
		$this->setup_cache();
		$this->assertTrue( class_exists( 'Related_Posts_By_Taxonomy_Cache' )  );
		$this->assertTrue( isset( $this->plugin->cache ) );
		$this->assertTrue( $this->plugin->cache instanceof Related_Posts_By_Taxonomy_Cache );
	}


	/**
	 * Tests if cache filter is set to false (by default).
	 *
	 * @depends KM_RPBT_Functions_Tests::test_km_rpbt_plugin
	 */
	function test_cache_filter() {
		add_filter( 'related_posts_by_taxonomy_cache', array( $this, 'return_bool' ) );
		$plugin = km_rpbt_plugin();
		$plugin->_setup();
		$this->assertFalse( $this->boolean  );
		$this->boolean = null;
	}


	/**
	 * Tests if cache filter display_cache_log is set to false (by default).
	 *
	 * @depends test_cache_setup
	 */
	function test_cache_filter_display_cache_log() {
		$this->setup_cache();
		$this->assertFalse( $this->plugin->cache->cache['display_cache_log']  );
	}


	/**
	 * Test cache.
	 *
	 * @depends test_cache_setup
	 */
	function test_cache_with_shortcode_in_post_content() {
		global $wpdb;

		$this->setup_cache();

		$create_posts = $this->create_posts_with_terms();
		$posts        = $create_posts['posts'];

		// Add a shortcode to cache.
		wp_update_post( array(
				'ID'          => $posts[0],
				'post_content' => '[related_posts_by_tax]',
			)
		);

		// Go to the single post page
		$this->go_to( get_permalink( $posts[0] ) );

		// Cache should be empty.
		$this->assertEmpty( $this->get_cache_meta_key() );

		// Trigger cache.
		ob_start();
		the_post();
		the_content();
		$content = ob_get_clean();

		$meta_key = $this->get_cache_meta_key();

		// Cache should be set for the shortcode in $post[0] content.
		$this->assertNotEmpty( $meta_key );

		// Get related post ids for $post[0] from cache.
		// This also tests if the cache is set for the right post ID
		$cache = get_post_meta( $posts[0], $meta_key, true );

		$cache_ids = array_keys( $cache['ids'] );
		$this->assertNotEmpty( $cache_ids );

		// Get related post ids with function.
		$args = array( 'fields' => 'ids' );
		$taxonomies = array_keys( $this->plugin->taxonomies );
		$related = km_rpbt_related_posts_by_taxonomy( $posts[0], $taxonomies, $args );

		$this->assertEquals( $cache_ids, $related );
	}


	/**
	 * Test manually setting the cache for a post id.
	 *
	 * @depends test_cache_setup
	 */
	function test_manually_cache_related_posts() {
		global $wpdb;

		$this->setup_cache();

		$create_posts = $this->create_posts_with_terms();
		$posts        = $create_posts['posts'];

		$args = array( 'fields' => 'ids' );
		$taxonomies = array( 'post_tag' );
		$related_posts = km_rpbt_cache_related_posts( $posts[1], $taxonomies, $args );

		$meta_key = $this->get_cache_meta_key();

		// Cache should be set for $post[1].
		$this->assertNotEmpty( $meta_key );

		// Get cache for $post[1];
		$cache = get_post_meta( $posts[1], $meta_key, true );
		$cache_ids = array_keys( $cache['ids'] );
		$this->assertNotEmpty( $cache_ids );

		$this->assertEquals( array( $posts[0], $posts[2], $posts[3] ), $cache_ids );

		$related = km_rpbt_related_posts_by_taxonomy( $posts[1], $taxonomies, $args );
		$this->assertEquals( $cache_ids, $related );
	}


	/**
	 * test cache for custom post type and custom taxonomy.
	 *
	 * @depends test_cache_setup
	 */
	function test_custom_post_type_cache() {
		$this->setup_cache();

		register_post_type( 'rel_cpt', array( 'taxonomies' => array( 'post_tag', 'rel_ctax' ) ) );
		register_taxonomy( 'rel_ctax', 'rel_cpt' );

		$posts = $this->create_posts_with_terms( 'rel_cpt', 'post_tag', 'rel_ctax' );
		$posts = $posts['posts'];

		$args =  array( 'post_types' => array( 'rel_cpt' ) );

		// Test with a single taxonomy.
		$taxonomies = array( 'rel_ctax' );

		$rel_post0 = km_rpbt_cache_related_posts( $posts[0], $taxonomies, $args );

		// Check if one related post was found
		$this->assertEquals( 1, count( $rel_post0 ) );

		$this->assertEquals( $posts[1], $rel_post0[0]->ID );

		$args = array_merge( $args, $taxonomies );

		$args =  array(
			'post_id' => $posts[0],
			'post_types' => array( 'rel_cpt' ),
			'taxonomies' => array( 'rel_ctax' ),

		);

		$related_posts = $this->plugin->cache->get_related_posts( $args );
		$this->assertEquals( $posts[1], $related_posts[0]->ID );
	}


	/**
	 * Test manually setting the cache for a post id.
	 *
	 * @depends test_cache_setup
	 */
	function test_flush_cache() {
		global $wpdb;

		$this->setup_cache();

		$create_posts = $this->create_posts_with_terms();
		$posts        = $create_posts['posts'];

		$args = array( 'fields' => 'ids' );
		$taxonomies = array( 'post_tag' );

		// Cache related posts for post 2
		$related_posts = km_rpbt_cache_related_posts( $posts[2], $taxonomies, $args );

		// Cache should be set for $post[2].
		$this->assertNotEmpty( $this->get_cache_meta_key() );

		km_rpbt_flush_cache();

		// Cache should be empty.
		$this->assertEmpty( $this->get_cache_meta_key() );
	}


	/**
	 * Test flushing cache when deleting a post.
	 *
	 * @depends test_cache_setup
	 */
	function test_cache_delete_post() {
		global $wpdb;

		$this->setup_cache();

		$create_posts = $this->create_posts_with_terms();
		$posts        = $create_posts['posts'];

		// Was set to true with create_posts_with_terms()
		$this->plugin->cache->flush_cache = false;

		$args = array( 'fields' => 'ids' );
		$taxonomies = array( 'post_tag' );

		// Cache related posts for post 2
		$related_posts = km_rpbt_cache_related_posts( $posts[2], $taxonomies, $args );

		// Cache should be set for $post[2].
		$this->assertNotEmpty( $this->get_cache_meta_key() );

		wp_delete_post( $posts[2] );

		// $this->plugin->cache->flush_cache should be true
		$this->plugin->cache->shutdown_flush_cache();

		// Cache should be empty.
		$this->assertEmpty( $this->get_cache_meta_key() );
	}


	/**
	 * Test flushing cache when setting a post thumbnail.
	 *
	 * @depends test_cache_setup
	 */
	function disabled_test_cache_set_post_thumbnail() {
		global $wpdb;

		$this->setup_cache();
		$attachment_id = $this->create_image();

		$create_posts = $this->create_posts_with_terms();
		$posts        = $create_posts['posts'];

		// Was set to true with create_posts_with_terms()
		$this->plugin->cache->flush_cache = false;

		$args = array( 'fields' => 'ids' );
		$taxonomies = array( 'post_tag' );

		// Cache related posts for post 2
		$related_posts = km_rpbt_cache_related_posts( $posts[2], $taxonomies, $args );

		// Cache should be set for $post[2].
		$this->assertNotEmpty( $this->get_cache_meta_key() );

		set_post_thumbnail ( $posts[2], $attachment_id );

		// $this->plugin->cache->flush_cache should be true
		$this->plugin->cache->shutdown_flush_cache();

		// Cache should be empty.
		$this->assertEmpty( $this->get_cache_meta_key() );
	}


	/**
	 * Test flushing cache when setting a post thumbnail.
	 *
	 * @depends test_cache_setup
	 */
	function test_cache_delete_term() {
		global $wpdb;

		$this->setup_cache();

		$create_posts = $this->create_posts_with_terms();
		$posts        = $create_posts['posts'];
		$terms        = $create_posts['tax1_terms'];

		// Was set to true with create_posts_with_terms()
		$this->plugin->cache->flush_cache = false;

		$args = array( 'fields' => 'ids' );
		$taxonomies = array( 'post_tag' );

		// Cache related posts for post 2
		$related_posts = km_rpbt_cache_related_posts( $posts[2], $taxonomies, $args );

		// Cache should be set for $post[2].
		$this->assertNotEmpty( $this->get_cache_meta_key() );

		wp_delete_term ( $terms[2], 'post_tag' );

		// $this->plugin->cache->flush_cache should be true
		$this->plugin->cache->shutdown_flush_cache();

		// Cache should be empty.
		$this->assertEmpty( $this->get_cache_meta_key() );
	}

}