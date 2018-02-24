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

			add_filter( 'tribe_events_timezone_choice', array( $this, 'wp_timezone_choice_wo_manual_offsets' ), 10, 2 );
		}

		/**
		 * Filter out the `<optgroup label="Manual Offsets">` from the list of
		 * allowed timezone choices.
		 *
		 * @see tribe_events_timezone_choice()
		 * @see wp_timezone_choice()
		 *
		 * @return string
		 */
		public function wp_timezone_choice_wo_manual_offsets( $timezone_choices, $selected_zone ) {
			// explode() removes the delimiter so we have to add it back for allowed timezones.
			$delimiter = '<optgroup';

			$optgroups = explode( $delimiter, $timezone_choices );

			$allowed_timezones = '';

			foreach ( $optgroups as $optgroup ) {
				// If in the "Manual Offsets" optgroup, skip it... but we search by the option `value` instead of the `optgroup` to avoid translations.
				if (
					empty( $optgroup ) // the first array item is empty
					|| false !== strpos( $optgroup, 'value="UTC-12"' )
				) {
					continue;
				} else {
					// If not the "Manual Offsets" optgroup, include it as-is, adding back in the delimiter.
					$allowed_timezones .= sprintf( '%s%s', $delimiter, $optgroup );
				}
			}

			return $allowed_timezones;
		}

	} // end class
} // end if class_exists check
