<?php
if ( class_exists( 'IP_Geo_Block_API' ) ) :

/**
 * URL and Path for Maxmind GeoLite database
 *
 */
define( 'IP_GEO_BLOCK_MAXMIND_IPV4_DAT', 'GeoIP.dat' );
define( 'IP_GEO_BLOCK_MAXMIND_IPV6_DAT', 'GeoIPv6.dat' );
define( 'IP_GEO_BLOCK_MAXMIND_IPV4_ZIP', 'http://geolite.maxmind.com/download/geoip/database/GeoLiteCountry/GeoIP.dat.gz' );
define( 'IP_GEO_BLOCK_MAXMIND_IPV6_ZIP', 'http://geolite.maxmind.com/download/geoip/database/GeoIPv6.dat.gz' );

/**
 * Class for Maxmind
 *
 * URL         : http://dev.maxmind.com/geoip/legacy/geolite/
 * Term of use : http://dev.maxmind.com/geoip/legacy/geolite/#License
 * Licence fee : Creative Commons Attribution-ShareAlike 3.0 Unported License
 * Input type  : IP address (IPv4, IPv6)
 * Output type : array
 */
class IP_Geo_Block_API_Maxmind extends IP_Geo_Block_API {

	private function location_country( $record ) {
		return array( 'countryCode' => $record );
	}

	private function location_city( $record ) {
		return array(
			'countryCode' => $record->country_code,
			'latitude'    => $record->latitude,
			'longitude'   => $record->longitude,
		);
	}

	public function get_location( $ip, $args = array() ) {
		require_once( 'geoip.inc' );

		// setup database file and function
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) )
			$file = $this->get_db_dir() . IP_GEO_BLOCK_MAXMIND_IPV4_DAT;
		elseif ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) )
			$file = $this->get_db_dir() . IP_GEO_BLOCK_MAXMIND_IPV6_DAT;
		else
			return array( 'errorMessage' => 'illegal format' );

		// open database and fetch data
		if ( ! file_exists( $file ) || null == ( $geo = geoip_open( $file, GEOIP_STANDARD ) ) )
			return FALSE;

		switch ( $geo->databaseType ) {
		  case GEOIP_COUNTRY_EDITION:
			$res = $this->location_country( geoip_country_code_by_addr( $geo, $ip ) );
			break;
		  case GEOIP_COUNTRY_EDITION_V6:
			$res = $this->location_country( geoip_country_code_by_addr_v6( $geo, $ip ) );
			break;
		  case GEOIP_CITY_EDITION_REV1:
			require_once( 'geoipcity.inc' );
			$res = $this->location_city( geoip_record_by_addr( $geo, $ip ) );
			break;
		  case GEOIP_CITY_EDITION_REV1_V6:
			require_once( 'geoipcity.inc' );
			$res = $this->location_city( geoip_record_by_addr_v6( $geo, $ip ) );
			break;
		  default:
			$res = array( 'errorMessage' => 'unknown database type' );
		}

		geoip_close( $geo );
		return $res;
	}

	public function get_db_dir() {
		return trailingslashit( apply_filters(
			IP_Geo_Block::PLUGIN_SLUG . '-maxmind-dir', dirname( __FILE__ )
		) );
	}

	public function download( &$db, $args ) {
		require_once( IP_GEO_BLOCK_PATH . 'includes/download.php' );

		$dir = $this->get_db_dir();

		$res['ipv4'] = ip_geo_block_download_zip(
			apply_filters( IP_Geo_Block::PLUGIN_SLUG . '-maxmind-zip-ipv4', IP_GEO_BLOCK_MAXMIND_IPV4_ZIP ),
			$args,
			$dir . IP_GEO_BLOCK_MAXMIND_IPV4_DAT,
			$db['ipv4_last']
		);

		$db['ipv4_path'] = ! empty( $res['ipv4']['filename'] ) ? $res['ipv4']['filename'] : NULL;
		$db['ipv4_last'] = ! empty( $res['ipv4']['modified'] ) ? $res['ipv4']['modified'] : 0;

		$res['ipv6'] = ip_geo_block_download_zip(
			apply_filters( IP_Geo_Block::PLUGIN_SLUG . '-maxmind-zip-ipv6', IP_GEO_BLOCK_MAXMIND_IPV6_ZIP ),
			$args,
			$dir . IP_GEO_BLOCK_MAXMIND_IPV6_DAT,
			$db['ipv6_last']
		);

		$db['ipv6_path'] = ! empty( $res['ipv6']['filename'] ) ? $res['ipv6']['filename'] : NULL;
		$db['ipv6_last'] = ! empty( $res['ipv6']['modified'] ) ? $res['ipv6']['modified'] : 0;

		return $res;
	}

	public function show_info() {
		echo 'This product includes GeoLite data created by MaxMind, available from <a class="ip-geo-block-link" href="http://www.maxmind.com" rel=noreferrer target=_blank>http://www.maxmind.com</a>.';
	}

	public function add_settings_field( $field, $section, $option_slug, $option_name, $options, $callback, $str_path, $str_last ) {
		$dir = $this->get_db_dir();

		add_settings_field(
			$option_name . "_${field}_ipv4",
			"$field $str_path (IPv4)",
			$callback,
			$option_slug,
			$section,
			array(
				'type' => 'text',
				'option' => $option_name,
				'field' => $field,
				'sub-field' => 'ipv4_path',
				'value' => $dir . IP_GEO_BLOCK_MAXMIND_IPV4_DAT,
				'disabled' => TRUE,
				'after' => '<br /><p id="ip_geo_block_' . $field . '_ipv4" style="margin-left: 0.2em">' .
				sprintf( $str_last, ip_geo_block_localdate( $options[ $field ]['ipv4_last'] ) ) . '</p>',
			)
		);

		add_settings_field(
			$option_name . "_${field}_ipv6",
			"$field $str_path (IPv6)",
			$callback,
			$option_slug,
			$section,
			array(
				'type' => 'text',
				'option' => $option_name,
				'field' => $field,
				'sub-field' => 'ipv6_path',
				'value' => $dir . IP_GEO_BLOCK_MAXMIND_IPV6_DAT,
				'disabled' => TRUE,
				'after' => '<br /><p id="ip_geo_block_' . $field . '_ipv6" style="margin-left: 0.2em">' .
				sprintf( $str_last, ip_geo_block_localdate( $options[ $field ]['ipv6_last'] ) ) . '</p>',
			)
		);
	}
}

/**
 * Register API
 *
 */
IP_Geo_Block_Provider::register_addon( array(
	'Maxmind' => array(
		'key'  => NULL,
		'type' => 'IPv4, IPv6 / free, need an attribution link',
		'link' => '<a class="ip-geo-block-link" href="http://dev.maxmind.com/geoip/legacy/geolite/" title="GeoLite Free Downloadable Databases &laquo; Maxmind Developer Site" rel=noreferrer target=_blank>http://www.maxmind.com</a>&nbsp;(IPv4, IPv6 / free, need an attribution link)',
	),
) );

endif;
?>