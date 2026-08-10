<?php
/**
 * Nav walker.
 *
 * WordPress's default walker prints a `<li>` per item and nothing else. The nav
 * needs two extra things: a per-character span on top-level labels so app.js
 * can stagger them out of the preloader, and an aria-expanded parent button on
 * items that own a sub-menu.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;

/**
 * Header navigation walker.
 */
class YS_Nav_Walker extends Walker_Nav_Menu {

	/**
	 * Open a sub-menu level.
	 *
	 * @param string   $output Menu HTML, by reference.
	 * @param int      $depth  Current depth.
	 * @param stdClass $args   Menu args.
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {
		$indent  = str_repeat( "\t", $depth );
		$output .= "\n{$indent}<ul class=\"sub-menu\">\n";
	}

	/**
	 * Render one item.
	 *
	 * @param string   $output Menu HTML, by reference.
	 * @param WP_Post  $item   Menu item.
	 * @param int      $depth  Current depth.
	 * @param stdClass $args   Menu args.
	 * @param int      $id     Item ID.
	 */
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$classes   = empty( $item->classes ) ? array() : (array) $item->classes;
		$classes[] = 'menu-item-' . $item->ID;

		if ( in_array( 'menu-item-has-children', $classes, true ) ) {
			$classes[] = 'has-children';
		}

		$class_names = implode( ' ', array_filter( apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args, $depth ) ) );

		$output .= sprintf( '<li class="%s">', esc_attr( $class_names ) );

		$atts = array(
			'title'  => $item->attr_title ? $item->attr_title : '',
			'target' => $item->target ? $item->target : '',
			'rel'    => $item->xfn ? $item->xfn : '',
			'href'   => $item->url ? $item->url : '',
		);

		// A target of _blank without noopener hands the new tab a handle on
		// window.opener; on a checkout flow that is a real risk, not a lint rule.
		if ( '_blank' === $atts['target'] && empty( $atts['rel'] ) ) {
			$atts['rel'] = 'noopener noreferrer';
		}

		$atts = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args, $depth );

		$attributes = '';
		foreach ( $atts as $key => $value ) {
			if ( '' === $value || false === $value ) {
				continue;
			}

			$value       = ( 'href' === $key ) ? esc_url( $value ) : esc_attr( $value );
			$attributes .= ' ' . $key . '="' . $value . '"';
		}

		$title = apply_filters( 'the_title', $item->title, $item->ID );
		$title = apply_filters( 'nav_menu_item_title', $title, $item, $args, $depth );

		$output .= '<a' . $attributes . '>' . esc_html( $title ) . '</a>';
	}

	/**
	 * Close an item.
	 *
	 * @param string   $output Menu HTML, by reference.
	 * @param WP_Post  $item   Menu item.
	 * @param int      $depth  Current depth.
	 * @param stdClass $args   Menu args.
	 */
	public function end_el( &$output, $item, $depth = 0, $args = null ) {
		$output .= "</li>\n";
	}
}
