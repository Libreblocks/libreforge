<?php
/**
 * Front-end view of an item template, used by the Elementor editor preview.
 * A sample item becomes the current object so dynamic widgets show real data.
 *
 * @package LibreBlocks\Forge
 */

defined( 'ABSPATH' ) || exit;

$libreforge_templates = libreblocks()->get( 'forge.templates' );
$libreforge_sample    = $libreforge_templates->sample_object( get_queried_object_id() );

get_header();

echo '<div class="libreforge-template-preview" style="max-width:480px;margin:40px auto;">';

if ( $libreforge_sample ) {
	libreblocks()->context()->push( $libreforge_sample );
}

while ( have_posts() ) {
	the_post();
	the_content();
}

if ( $libreforge_sample ) {
	libreblocks()->context()->pop();
}

echo '</div>';

get_footer();
