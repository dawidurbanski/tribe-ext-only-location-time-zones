<?php
/**
 * Plugin Name:     The Events Calendar Extension: Only City-Based Timezones for Events
 * Description:     Only allow events to have city-based timezones. Manual UTC/GMT offsets cause more problems than they solve, particularly when it comes to Daylight Savings Time (DST).
 * Version:         1.0.0
 * Extension Class: Tribe__Extension__Allow_Only_City_Based_Timezones
 * Author:          Modern Tribe, Inc.
 * Author URI:      http://m.tri.be/1971
 * License:         GPL version 3 or any later version
 * License URI:     https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:     tribe-ext-only-city-based-timezones
 *
 *     This plugin is free software: you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License as published by
 *     the Free Software Foundation, either version 3 of the License, or
 *     any later version.
 *
 *     This plugin is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *     GNU General Public License for more details.
 */

// Do not load unless Tribe Common is fully loaded and our class does not yet exist.
if (
	class_exists( 'Tribe__Extension' )
	&& ! class_exists( 'Tribe__Extension__Allow_Only_City_Based_Timezones' )
) {
	/**
	 * Extension main class, class begins loading on init() function.
	 */
	class Tribe__Extension__Allow_Only_City_Based_Timezones extends Tribe__Extension {
		/**
		 * Setup the Extension's properties.
		 *
		 * This always executes even if the required plugins are not present.
		 */
		public function construct() {
			// readme.txt states WP 4.7.0+ is required because that's when wp_timezone_choice() added the `$locale` parameter
			// tribe_events_timezone_choice() didn't exist until TEC 4.6.5
			$this->add_required_plugin( 'Tribe__Events__Main', '4.6.5' );
			$this->set_url( 'https://theeventscalendar.com/extensions/allow-only-city-based-timezones-for-events/' );
		}

		/**
		 * Extension initialization and hooks.
		 */
		public function init() {
			// Load plugin textdomain
			load_plugin_textdomain( 'tribe-ext-only-city-based-timezones', false, basename( dirname( __FILE__ ) ) . '/languages/' );

			add_filter( 'tribe_events_timezone_choice', array( $this, 'wp_timezone_choice_wo_manual_offsets' ), 10, 3 );
		}

		/**
		 * Build the <optgroup> list of timezone choices, including "UTC" and
		 * all city-based timezones, excluding all manual offsets like "UTC+1".
		 *
		 * This is a copy of wp_timezone_choice() with the UTC/GMT manual offset part of
		 * it removed and our own text domain added.
		 *
		 * @see wp_timezone_choice()
		 * @see tribe_events_timezone_choice()
		 *
		 * @return string
		 */
		public function wp_timezone_choice_wo_manual_offsets( $timezone_choices, $selected_zone, $locale ) {
			static $mo_loaded = false, $locale_loaded = null;

			$continents = array( 'Africa', 'America', 'Antarctica', 'Arctic', 'Asia', 'Atlantic', 'Australia', 'Europe', 'Indian', 'Pacific');

			// Load translations for continents and cities.
			if ( ! $mo_loaded || $locale !== $locale_loaded ) {
				$locale_loaded = $locale ? $locale : get_locale();
				$mofile = WP_LANG_DIR . '/continents-cities-' . $locale_loaded . '.mo';
				unload_textdomain( 'continents-cities' );
				load_textdomain( 'continents-cities', $mofile );
				$mo_loaded = true;
			}

			$zonen = array();
			foreach ( timezone_identifiers_list() as $zone ) {
				$zone = explode( '/', $zone );
				if ( !in_array( $zone[0], $continents ) ) {
					continue;
				}

				// This determines what gets set and translated - we don't translate Etc/* strings here, they are done later
				$exists = array(
					0 => ( isset( $zone[0] ) && $zone[0] ),
					1 => ( isset( $zone[1] ) && $zone[1] ),
					2 => ( isset( $zone[2] ) && $zone[2] ),
				);
				$exists[3] = ( $exists[0] && 'Etc' !== $zone[0] );
				$exists[4] = ( $exists[1] && $exists[3] );
				$exists[5] = ( $exists[2] && $exists[3] );

				$zonen[] = array(
					'continent'   => ( $exists[0] ? $zone[0] : '' ),
					'city'        => ( $exists[1] ? $zone[1] : '' ),
					'subcity'     => ( $exists[2] ? $zone[2] : '' ),
					't_continent' => ( $exists[3] ? translate( str_replace( '_', ' ', $zone[0] ), 'continents-cities' ) : '' ),
					't_city'      => ( $exists[4] ? translate( str_replace( '_', ' ', $zone[1] ), 'continents-cities' ) : '' ),
					't_subcity'   => ( $exists[5] ? translate( str_replace( '_', ' ', $zone[2] ), 'continents-cities' ) : '' )
				);
			}
			usort( $zonen, '_wp_timezone_choice_usort_callback' );

			$structure = array();

			if ( empty( $selected_zone ) ) {
				$structure[] = '<option selected="selected" value="">' . __( 'Select a city', 'tribe-ext-only-city-based-timezones' ) . '</option>';
			}

			foreach ( $zonen as $key => $zone ) {
				// Build value in an array to join later
				$value = array( $zone['continent'] );

				if ( empty( $zone['city'] ) ) {
					// It's at the continent level (generally won't happen)
					$display = $zone['t_continent'];
				} else {
					// It's inside a continent group

					// Continent optgroup
					if ( !isset( $zonen[$key - 1] ) || $zonen[$key - 1]['continent'] !== $zone['continent'] ) {
						$label = $zone['t_continent'];
						$structure[] = '<optgroup label="'. esc_attr( $label ) .'">';
					}

					// Add the city to the value
					$value[] = $zone['city'];

					$display = $zone['t_city'];
					if ( !empty( $zone['subcity'] ) ) {
						// Add the subcity to the value
						$value[] = $zone['subcity'];
						$display .= ' - ' . $zone['t_subcity'];
					}
				}

				// Build the value
				$value = join( '/', $value );
				$selected = '';
				if ( $value === $selected_zone ) {
					$selected = 'selected="selected" ';
				}
				$structure[] = '<option ' . $selected . 'value="' . esc_attr( $value ) . '">' . esc_html( $display ) . "</option>";

				// Close continent optgroup
				if ( !empty( $zone['city'] ) && ( !isset($zonen[$key + 1]) || (isset( $zonen[$key + 1] ) && $zonen[$key + 1]['continent'] !== $zone['continent']) ) ) {
					$structure[] = '</optgroup>';
				}
			}

			// Do UTC
			$structure[] = '<optgroup label="'. esc_attr__( 'UTC', 'tribe-ext-only-city-based-timezones' ) .'">';
			$selected = '';
			if ( 'UTC' === $selected_zone )
				$selected = 'selected="selected" ';
			$structure[] = '<option ' . $selected . 'value="' . esc_attr( 'UTC' ) . '">' . __( 'UTC', 'tribe-ext-only-city-based-timezones' ) . '</option>';
			$structure[] = '</optgroup>';

			// Do !!! NOT !!! do manual UTC offsets, unlike wp_timezone_choice()

			return join( "\n", $structure );
		}

	} // end class
} // end if class_exists check
