<?php
/**
 * Customiser settings.
 *
 * Everything a store owner is likely to want to change without editing a file:
 * the hero copy, the accent ramp, the marquee, the trust points, and the two
 * motion switches.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register panels, sections and controls.
 *
 * @param WP_Customize_Manager $wp_customize Customiser.
 */
function ys_store_customize_register( $wp_customize ) {
	$wp_customize->get_setting( 'blogname' )->transport        = 'postMessage';
	$wp_customize->get_setting( 'blogdescription' )->transport = 'postMessage';

	$wp_customize->add_panel(
		'ys_store',
		array(
			'title'    => __( 'YS Store', 'ys-store' ),
			'priority' => 20,
		)
	);

	/* ---- Hero ------------------------------------------------------------ */

	$wp_customize->add_section(
		'ys_hero',
		array(
			'title' => __( 'Hero', 'ys-store' ),
			'panel' => 'ys_store',
		)
	);

	$hero_fields = array(
		'ys_hero_eyebrow'   => array(
			'label'   => __( 'Eyebrow', 'ys-store' ),
			'default' => __( 'New season · shipping worldwide', 'ys-store' ),
		),
		'ys_hero_title'     => array(
			'label'   => __( 'Headline', 'ys-store' ),
			'default' => __( 'Built for people who move fast', 'ys-store' ),
			'type'    => 'textarea',
		),
		'ys_hero_text'      => array(
			'label'   => __( 'Supporting line', 'ys-store' ),
			'default' => __( 'A curated catalogue, honest pricing, and delivery that arrives when we said it would.', 'ys-store' ),
			'type'    => 'textarea',
		),
		'ys_hero_cta_text'  => array(
			'label'   => __( 'Button label', 'ys-store' ),
			'default' => __( 'Shop the catalogue', 'ys-store' ),
		),
		'ys_hero_cta_url'   => array(
			'label'   => __( 'Button URL', 'ys-store' ),
			'default' => '',
			'type'    => 'url',
		),
		'ys_hero_cta2_text' => array(
			'label'   => __( 'Secondary button label', 'ys-store' ),
			'default' => __( 'Best sellers', 'ys-store' ),
		),
		'ys_hero_cta2_url'  => array(
			'label'   => __( 'Secondary button URL', 'ys-store' ),
			'default' => '',
			'type'    => 'url',
		),
	);

	foreach ( $hero_fields as $id => $field ) {
		$type = $field['type'] ?? 'text';

		$wp_customize->add_setting(
			$id,
			array(
				'default'           => $field['default'],
				'sanitize_callback' => 'url' === $type ? 'esc_url_raw' : 'wp_kses_post',
				'transport'         => 'postMessage',
			)
		);

		$wp_customize->add_control(
			$id,
			array(
				'label'   => $field['label'],
				'section' => 'ys_hero',
				'type'    => $type,
			)
		);
	}

	/* ---- Colour ---------------------------------------------------------- */

	$wp_customize->add_section(
		'ys_colour',
		array(
			'title'       => __( 'Colour', 'ys-store' ),
			'panel'       => 'ys_store',
			'description' => __( 'These three stops make the gradient used by every accent, button and heading on the store.', 'ys-store' ),
		)
	);

	$ramp = array(
		'ys_colour_1' => array( __( 'Gradient start', 'ys-store' ), '#00e5ff' ),
		'ys_colour_2' => array( __( 'Gradient middle', 'ys-store' ), '#8b5cf6' ),
		'ys_colour_3' => array( __( 'Gradient end', 'ys-store' ), '#ff3d81' ),
	);

	foreach ( $ramp as $id => list( $label, $default ) ) {
		$wp_customize->add_setting(
			$id,
			array(
				'default'           => $default,
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Color_Control( $wp_customize, $id, array(
				'label'   => $label,
				'section' => 'ys_colour',
			) )
		);
	}

	/* ---- Shop ------------------------------------------------------------ */

	$wp_customize->add_section(
		'ys_shop',
		array(
			'title' => __( 'Shop', 'ys-store' ),
			'panel' => 'ys_store',
		)
	);

	$wp_customize->add_setting(
		'ys_shop_columns',
		array(
			'default'           => 4,
			'sanitize_callback' => 'absint',
		)
	);

	$wp_customize->add_control(
		'ys_shop_columns',
		array(
			'label'       => __( 'Products per row', 'ys-store' ),
			'section'     => 'ys_shop',
			'type'        => 'number',
			'input_attrs' => array(
				'min' => 2,
				'max' => 5,
			),
		)
	);

	$wp_customize->add_setting(
		'ys_products_per_page',
		array(
			'default'           => 12,
			'sanitize_callback' => 'absint',
		)
	);

	$wp_customize->add_control(
		'ys_products_per_page',
		array(
			'label'       => __( 'Products per page', 'ys-store' ),
			'section'     => 'ys_shop',
			'type'        => 'number',
			'input_attrs' => array(
				'min' => 4,
				'max' => 48,
			),
		)
	);

	$wp_customize->add_setting(
		'ys_open_drawer_on_add',
		array(
			'default'           => true,
			'sanitize_callback' => 'ys_store_sanitize_checkbox',
		)
	);

	$wp_customize->add_control(
		'ys_open_drawer_on_add',
		array(
			'label'       => __( 'Open the cart drawer when something is added', 'ys-store' ),
			'description' => __( 'Off shows a small confirmation toast instead, which suits stores where customers usually add several items in a row.', 'ys-store' ),
			'section'     => 'ys_shop',
			'type'        => 'checkbox',
		)
	);

	$trust = array(
		'ys_trust_1' => __( 'Free delivery over the free-shipping threshold', 'ys-store' ),
		'ys_trust_2' => __( '30-day returns, no questions', 'ys-store' ),
		'ys_trust_3' => __( '2-year warranty on every item', 'ys-store' ),
	);

	$n = 1;
	foreach ( $trust as $id => $default ) {
		$wp_customize->add_setting(
			$id,
			array(
				'default'           => $default,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		$wp_customize->add_control(
			$id,
			array(
				/* translators: %d: line number. */
				'label'   => sprintf( __( 'Product page reassurance line %d', 'ys-store' ), $n ),
				'section' => 'ys_shop',
				'type'    => 'text',
			)
		);

		$n++;
	}

	/* ---- Motion ---------------------------------------------------------- */

	$wp_customize->add_section(
		'ys_motion',
		array(
			'title'       => __( 'Motion', 'ys-store' ),
			'panel'       => 'ys_store',
			'description' => __( 'Anyone whose system asks for reduced motion gets a still page regardless of these settings.', 'ys-store' ),
		)
	);

	$motion = array(
		'ys_preloader' => array( __( 'Show the intro animation', 'ys-store' ), true ),
		'ys_cursor'    => array( __( 'Custom cursor on desktop', 'ys-store' ), true ),
		'ys_marquee'   => array( __( 'Scrolling promise strip', 'ys-store' ), true ),
	);

	foreach ( $motion as $id => list( $label, $default ) ) {
		$wp_customize->add_setting(
			$id,
			array(
				'default'           => $default,
				'sanitize_callback' => 'ys_store_sanitize_checkbox',
			)
		);

		$wp_customize->add_control(
			$id,
			array(
				'label'   => $label,
				'section' => 'ys_motion',
				'type'    => 'checkbox',
			)
		);
	}

	$wp_customize->add_setting(
		'ys_marquee_text',
		array(
			'default'           => __( 'Free shipping over the threshold, 30-day returns, Tracked delivery, Human support', 'ys-store' ),
			'sanitize_callback' => 'sanitize_text_field',
		)
	);

	$wp_customize->add_control(
		'ys_marquee_text',
		array(
			'label'       => __( 'Promise strip phrases', 'ys-store' ),
			'description' => __( 'Comma separated.', 'ys-store' ),
			'section'     => 'ys_motion',
			'type'        => 'textarea',
		)
	);
}
add_action( 'customize_register', 'ys_store_customize_register' );

/**
 * @param mixed $checked Raw value.
 * @return bool
 */
function ys_store_sanitize_checkbox( $checked ) {
	return ( isset( $checked ) && true === (bool) $checked );
}

/**
 * Print the Customiser's colour choices as an override for the token layer.
 *
 * Only the three ramp stops are overridable. Everything derived from them —
 * the gradient, the glow, the focus ring — is defined in terms of the
 * variables, so setting the stops is enough to retheme the whole store.
 */
function ys_store_customizer_css() {
	$c1 = get_theme_mod( 'ys_colour_1', '#00e5ff' );
	$c2 = get_theme_mod( 'ys_colour_2', '#8b5cf6' );
	$c3 = get_theme_mod( 'ys_colour_3', '#ff3d81' );

	// Nothing to print when the store is running the defaults.
	if ( '#00e5ff' === $c1 && '#8b5cf6' === $c2 && '#ff3d81' === $c3 ) {
		return;
	}

	printf(
		'<style id="ys-customizer">:root{--cyan:%1$s;--violet:%2$s;--pink:%3$s;--accent:%1$s;--glow:0 0 80px -18px %1$s;}</style>',
		esc_attr( sanitize_hex_color( $c1 ) ),
		esc_attr( sanitize_hex_color( $c2 ) ),
		esc_attr( sanitize_hex_color( $c3 ) )
	);
}
add_action( 'wp_head', 'ys_store_customizer_css', 20 );

/**
 * Live preview for the text settings, so the Customiser does not reload the
 * whole frame on every keystroke.
 */
function ys_store_customize_preview_js() {
	wp_enqueue_script(
		'ys-customize-preview',
		YS_STORE_URI . 'assets/js/customize-preview.js',
		array( 'customize-preview' ),
		YS_STORE_VERSION,
		true
	);
}
add_action( 'customize_preview_init', 'ys_store_customize_preview_js' );
