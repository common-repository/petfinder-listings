<?php
/*
Plugin Name: List Petfinder Pets
Plugin URI: https://unboxinteractive.com/petfinder-plugin-email-list/
Description: The List Petfinder Pets plugin takes advantage of the Petfinder API to list Shelter Pets on your website.
Version: 1.1
Author: Bridget Wessel
Author URI: https://unboxinteractive.com/
License: GPLv2
*/

/*  Copyright 2022 Bridget Wessel  (email : bridget@unboxinteractive.com)

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

/********** Add default styles ************/

include( dirname(__FILE__) . '/featuredpet-widget.php' );

class Petfinder_Listings{

	private $petf_options;
	private $possible_animals;
	private $possible_ages;

	private $token_error_msg;

    public function __construct()    {
		//add defaults to an array
		$petf_options_default = array(
			'apikey_v2'				 => '',
			'apisecret_v2'			 => '',
			'shelter_id'             => 'default',
			'large_image'            => 'pn',
			'sort_by'                => 'newest',
			'hide_pet_full_description_link' => '0',
			'powered_by'             => 'Yes',
			'debug'					 => '0',
			'breed'                  => '',
			'count'                  => 75,
			'page'                   => 1,
			'animal'                 => '',
			'include_info'           => 'yes',
			'css_class'              => 'pets',
			'status'                 => 'adoptable'			
		);

		$this->token_error_msg = self::get_token_error_msg();

		$this->possible_animals = array('Dog', 'Cat', 'Rabbit', 'Small & Furry', 'Horse', 'Bird', 'Scales', 'Fins & Other', 'Barnyard');

		$this->possible_ages = array('baby', 'young', 'adult', 'senior');

		//add settings to database if not already set
		add_option('Petfinder-Listings', $petf_options_default);

		$this->petf_options = get_option('Petfinder-Listings');

		//Add new default item that might not be in options
		if( !array_key_exists( 'hide_pet_full_description_link', $this->petf_options ) ){
			$this->petf_options['hide_pet_full_description_link'] = '0';
			update_option('Petfinder-Listings', $this->petf_options);
		}

		/* to catch any saved options when option was set incorrectly v 1.0.13 */
		if( !is_array($this->petf_options) ){
			update_option('Petfinder-Listings', $petf_options_default);
			$this->petf_options = get_option('Petfinder-Listings');
		}

		add_action ('widgets_init', array($this, 'petf_add_featured_pet_widget') );

		add_action('wp_enqueue_scripts', array($this, 'petf_front_scripts'));

		add_action('admin_menu', array( $this, 'petf_admin_page') );

        // Add Settings to Plugin Menu
        $pluginName = plugin_basename( __FILE__ );

        add_filter( 'plugin_action_links_' . $pluginName, array( $this, 'petf_pluginActions') );

        add_shortcode('shelter_list', array( $this, 'petf_shelter_list'));
		add_shortcode('get_pet', array( $this, 'petf_get_pet'));		
	}
	
	function petf_add_featured_pet_widget (){
		return register_widget('Petfinder_Listings_Featured_Pet');
	}
	
    function petf_front_scripts(){
		wp_enqueue_script('petf-js', plugins_url( 'petfinder.js', __FILE__ ), null, null, true);

		wp_enqueue_style('petf-listings-style', plugins_url( 'petfinder.css', __FILE__ ), null, null);
	}
	
	function petf_admin_page() {
		add_options_page('List Petfinder Pets Plugin Settings', 'List Petfinder Pets', 'manage_options', 'petf', array($this, 'petf_options_page') );
	}

    function petf_pluginActions( $links ) {
        $settings_link =
			'<a href="' . esc_url( get_admin_url( null, 'options-general.php?page=petf' ) ) .'">' .
			__('Settings', 'petf') . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    //write settings page
    function petf_options_page(){
        if (isset($_POST['save_changes']) && check_admin_referer('petfinder-listings-update_settings') ) {
            $this->petf_options['apikey_v2']     = sanitize_text_field( trim($_POST['apikey_v2']) );
            $this->petf_options['apisecret_v2']  = sanitize_text_field( trim( $_POST['apisecret_v2']) );
            $this->petf_options['shelter_id']    = sanitize_text_field( trim($_POST['shelter_id']) );
            $this->petf_options['large_image']   = sanitize_text_field( trim($_POST['large_image']) );
            $this->petf_options['sort_by']       = sanitize_text_field( trim($_POST['sort_by']) );
			$this->petf_options['hide_pet_full_description_link'] = $_POST['hide_pet_full_description_link'] == '1' ? '1' : '0';
			$this->petf_options['powered_by']    = $_POST['powered_by'] == 'Yes' ? 'Yes' : 'No';
			$this->petf_options['debug']		 = $_POST['debug'] == '1' ? '1' : '0';
			
            update_option('Petfinder-Listings', $this->petf_options);

			_e("<div class=\"error\">Your changes have been saved successfully!</div>", 'petf');
        } ?>
	<div class="wrap">

	<h2>List Petfinder Pets Settings</h2>

	<form name="petfinder-options" action="options-general.php?page=petf" method="post">
		<?php
		if ( function_exists( 'wp_nonce_field' ) ) {
		    wp_nonce_field( 'petfinder-listings-update_settings' );
		} ?>

		<table class="form-table" style="width: 100%">
			<tr>
				<td colspan="2">
					<p>Get your Petfinder v2.0 API Key and Secret from your Petfinder account. You can create a free Petfinder account <a href="https://www.petfinder.com/user/register/" target="_blank">here</a>. Once you have an account go <a href="https://www.petfinder.com/developers/" target="_blank">here</a> to generate a Petfinder <strong>API v2.0</strong> Key and Secret.</p>
				
					<p>Once you have an API Key and Secret, you can find the information again in your 'Account Info' > 'Developer Settings' or on <a href="https://www.petfinder.com/user/developer-settings/" target="_blank">this page</a> when logged into your Petfinder account.</p>
 
					<p><strong>Please note, the Petfinder API V2.0 only provides a short excerpt of your pet's description.</strong> Users have successfully asked Petfinder to return the full pet description for their account.</p>
				</td>
			</tr>
			<tr valign="top">
			<th scope="row" style="width: 50%"><label for="apikey_v2">Your Petfinder API Key <strong>v2.0</strong> (go <a href="https://www.petfinder.com/developers/" target="_blank">here</a> to get one)</label></th>
			<td style="width: 50%"><input type="text" id="apikey_v2" name="apikey_v2" value="<?php echo esc_attr($this->petf_options["apikey_v2"]); ?>" /></td>
			</tr>

			<tr valign="top">
			<th scope="row"><label for="apisecret_v2">Your Petfinder API Secret <strong>v2.0</strong></label></th>
			<td><input type="text" id="apisecret_v2" name="apisecret_v2" value="<?php echo esc_attr($this->petf_options["apisecret_v2"]); ?>" /></td>
			</tr>

			<tr valign="top">
			<th scope="row"><label for="shelter_id">Shelter ID</label></th>
			<td><input type="text" id="shelter_id" name="shelter_id" value="<?php echo esc_attr($this->petf_options["shelter_id"]); ?>" /></td>
			</tr>

			<tr valign="top">
			<th scope="row"><label for="large_image">Large Image Size</label></th>
			<td><select id="large_image" name="large_image">				
				<option value="pn" <?php echo $this->petf_options["large_image"] == "pn" ? "selected='selected'" : "" ?>>up 300px wide</option>
				<option value="x" <?php echo $this->petf_options["large_image"] == "x" ? "selected='selected'" : "" ?>>up to 600px</option>
			</select></td>
			</tr>

			<tr valign="top">
				<th scope="row"><label for="sort_by">Sort Pets By</label></th>
				<td><select id="sort_by" name="sort_by">
						<option value="newest" <?php echo $this->petf_options["sort_by"] == "newest" ? "selected='selected'" : "" ?>>Newest</option>
						<option value="last_updated" <?php echo $this->petf_options["sort_by"] == "last_updated" ? "selected='selected'" : "" ?>>Last Updated</option>
						<option value="name" <?php echo $this->petf_options["sort_by"] == "name" ? "selected='selected'" : "" ?>>Pet Name</option>
					</select></td>
			</tr>

			<tr>
				<th scope="row">Hide "View full description »" link after each pet's description. You might do this if you've asked Petfinder to return your pets' full descriptions instead of just a short excerpt. (API V2.0 only)</th>
				<td><label><input type="radio" value="1" name="hide_pet_full_description_link" <?php echo ( $this->petf_options["hide_pet_full_description_link"] == "1" )? "checked=\"checked\"" : "" ?>>Yes</label> <label><input type="radio" value="0" name="hide_pet_full_description_link" <?php echo ($this->petf_options["hide_pet_full_description_link"] == "0")? "checked=\"checked\"" : "" ?>>No</label></td>
			</tr>

			<tr>
				<th scope="row">Include Powered by Petfinder at bottom of page. Petfinder provides a great, free service for shelters and it is highly recommended you leave this on your Petfinder pages.</th>
				<td><label><input type="radio" value="Yes" name="powered_by" <?php echo ( $this->petf_options["powered_by"] == "Yes" )? "checked=\"checked\"" : "" ?>>Yes</label> <label><input type="radio" value="No" name="powered_by" <?php echo ($this->petf_options["powered_by"] == "No")? "checked=\"checked\"" : "" ?>>No</label></td>
			</tr>

			<tr>
				<th colspan="2"><p>After saving, create a page with the shortcode [shelter_list] in the content. View this page to see your listings.</p>
					<p>You can also add the following options to your shortcode<br />[shelter_list shelter_id="WI185" breed="Italian Greyhound" count=75 page=2 animal="Dog" include_info="no" css_class="cats" status="adoptable" sort_by="newest" age="baby"] </p></th>
			</tr>

			<tr>
				<th scope="row"><label for="debug">Debugging: This will output the response from petfinder if no pets are returned.</label></th>
				<td><select id="debug" name="debug">
					<option value="0" <?php echo $this->petf_options["debug"] == "0" ? "selected='selected'" : "" ?>>Off</option>
					<option value="1" <?php echo $this->petf_options["debug"] == "1" ? "selected='selected'" : "" ?>>On</option>					
				</select></td>
			</tr>

		</table>

		<p class="submit">
		<input type="hidden" name="save_changes" value="1" />
		<input type="submit" class="button-primary" value="<?php _e('Save Changes', 'petf') ?>" />
		</p>

	</form>
	</div>

	<?php
    }    // end function petf_options_page
	
	function petf_sort_by_last_updated_v2($petA, $petB){
        if ( (string)$petA["status_changed_at"] == (string)$petB["status_changed_at"] ) {
            return 0;
        } else {
            if ( (string)$petA["status_changed_at"] < (string)$petB["status_changed_at"] ) {
                return 1;
            } else {
                return -1;
            }
        }
    }
	
	function petf_sort_by_name_V2($petA, $petB){
        if ( (string)$petA["name"] == (string)$petB["name"] ) {
            return 0;
        } else {
            if ( (string)$petA["name"] < (string)$petB["name"] ) {
                return -1;
            } else {
                return 1;
            }
        }
    }

    function petf_sort_by_newest_V2($petA, $petB){
        if ( (string)$petA["published_at"] == (string)$petB["published_at"] ) {
            return 0;
        } else {
            if ( (string)$petA["published_at"] < (string)$petB["published_at"] ) {
                return 1;
            } else {
                return -1;
            }
        }
    }

    /*** Using shortcode shelter_list grab all animals for this shelter. ***/
    function petf_shelter_list( $atts ){	
		$access_token = self::get_access_token_V2();
		if ($access_token) { 
			$atts = shortcode_atts( array(
				'shelter_id'             => $this->petf_options['shelter_id'],
				'breed'                  => '',
				'count'                  => 75,
				'page'                   => 1,
				'animal'                 => '',
				'include_info'           => 'yes',
				'css_class'              => 'pets',
				'status'                 => 'adoptable',
				'sort_by'                => $this->petf_options['sort_by'],
				'age'					 => ''
			), $atts, 'shelter_list' );

		    //now get pets
		    $args = array( 
				'timeout'      => 120, 
				'headers'      => array('Authorization' => 'Bearer ' . $access_token )
			);

		    $querystr = '?organization=' . urlencode( trim($atts['shelter_id']) ) . '&limit=' . intval( $atts['count'] ) . '&page=' . intval( $atts['page'] );

		    if( $atts['breed'] != '' ) {
				$breed = str_replace('&amp;', '&', ucwords( trim( $atts['breed'] ) ) );				
				$querystr .= '&breed=' . urlencode($breed);
		    }

			$status = 'adoptable';
		    switch($atts['status']){ //always send a status
				case "A":
				case "H":
				case "P" :
					$status = 'adoptable';
					break;
				case "X":
					$status = 'adopted';
				break;
				case "found":
				case "adoptable":
				case "adopted":
					$status = $atts['status'];
					break; 
			}
			$querystr .= '&status=' . urlencode( trim($status) );

			if($atts['age'] != ''){
				//age has fixed possible options. Check if in list and if so, send parameter
				if ( in_array( $atts['age'], $this->possible_ages ) ) {
					$querystr .= '&age=' . urlencode($atts['age']);
				}
			}
		    
		    if ( $atts['animal'] != '') {
				$animal = str_replace('&amp;', '&', $atts['animal'] );	
				//animal has fixed possible options. Check if in list and if so, send parameter
				if ( in_array( $animal, $this->possible_animals ) ) {
					$querystr .= '&type=' . urlencode($animal);
				}
			}
			
			//echo $querystr;

		    $response = wp_safe_remote_get( "https://api.petfinder.com/v2/animals/" . $querystr, $args);
			

		    if ( is_wp_error( $response ) ) {
		        $error_message = esc_html($response->get_error_message());
		        return '<p>' . ( sprintf( __("Something went wrong when retrieving informaton from Petfinder: %s", 'petf'), $error_message) ) . '</p>';
		    } else {
		        $output_str = "";
		        $retdata       = json_decode( wp_remote_retrieve_body( $response ), true );
		        if ( array_key_exists('animals', $retdata) && count($retdata['animals']) > 0 ) {

					$pets = $retdata['animals'];
					
					$output_str .= "<div class=\"" . esc_attr($atts['css_class']) . "\">";

					switch ($atts['sort_by']) {
						case "last_updated":
							uasort($pets, array($this, 'petf_sort_by_last_updated_V2') );
							break;
						case "name":
							uasort($pets, array($this, 'petf_sort_by_name_V2') );
							break;
						case "newest":
							uasort($pets, array($this, 'petf_sort_by_newest_V2') );
							break;
					}
					
					foreach ($pets as $pet) {
		                $output_str .= $this->single_pet_info_v2($pet, $atts['include_info']);
					}

					if ($this->petf_options['powered_by'] == "Yes") {
						$output_str .= "<div class=\"powered_by\"><p>Powered by <a href=\"https://www.petfinder.com\" target=\"_blank\">Petfinder.com</a></p></div>";
					}
					$output_str .= "</div>"; //close .$css_class
		        } else {
					if($this->petf_options['debug'] == '1'){
						$output_str .= esc_html( json_encode( $retdata ) );
					}else{
					    $output_str .= "<p>" . __('No pets available at this time.', 'petf') . "</p>";
					}
				}				
		        return $output_str; //output of shortcode so displayed on site
		    }
		}else{
            return $this->token_error_msg; //output of shortcode so displayed on site
        }
    }

    /** Using shortcode get_pet grab one pet from petfinder.
     * Available Options: pet_id *required*, include_info, css_class.  ***/
    function petf_get_pet( $atts ) {

        $atts = shortcode_atts( array(
			'pet_id'                 => 0,
			'include_info'           => 'yes',
			'css_class'              => 'pets'
		), $atts, 'get_pet' );

        $output_str = "";
        if (is_numeric($atts['pet_id']) && $atts['pet_id'] > 0 ) {
			$access_token = self::get_access_token_V2(); //check if using Petfinder API v2.0 
			if ($access_token) { //on v2
			    //now get pets
			    $args = array( 
					'timeout'      => 120, 
					'headers'      => array('Authorization' => 'Bearer ' . $access_token )
				);
				$response = wp_safe_remote_get( "https://api.petfinder.com/v2/animals/" . intval($atts['pet_id']), $args);
				if ( is_wp_error( $response ) ) {
					$error_message = esc_html( $response->get_error_message() );
					$output_str .= '<p>' . sprintf( __("Something went wrong when retrieving informaton from Petfinder: %s", 'petf'), $error_message ) . '</p>';
				} else {
					$retdata = json_decode( wp_remote_retrieve_body( $response ), true );
					if ( array_key_exists('animal', $retdata) && count($retdata['animal']) > 0 ) {

						$output_str .= "<div class=\"" . esc_attr($atts['css_class']) . "\">";
						
						$output_str .= $this->single_pet_info_v2($retdata['animal'], $atts['include_info'] );

						$output_str .= "</div>"; //close .$css_class
				    } else {
						$output_str .= __("<p>This pet is no longer available.</p>", 'petf');
					}
				}

			}else{
				return $this->token_error_msg;
			}
		} else {
			$output_str .= __("<p>Invalid Pet ID supplied.</p>", 'petf');
		}
        return $output_str; 
    }
	
	/************ Helper Functions **************/

	public static function get_clean_html_args(){
		//Allowed HTML in description - will remove scripts and styles, but keep basic formatting  -- NOTE: wrapping description in <p> tag so need to remove paragraph, headings, etc. tags
		$allowed_html = array(
			'a' => array(
				'href' => array(),
				'title' => array(),
				'target' => array()
			),
			'br' => array(),
			'em' => array(),
			'strong' => array(),
			'b' => array(),
			'em' => array(),
			'iframe' => array(
				'loading' => array(),
				'title' => array(),
				'src' => array(),
				'allow' => array(),
				'allowfullscreen' => array(),
				'style' => array(),
				'frameborder' => array()
			)
		);

		return $allowed_html;
	}

	public static function get_token_error_msg(){
		return __("<p>Not able to create a Petfinder V2 access token. Please make sure your API Key and Secret are set correctly on the plugin's settings page.</p>", 'petf');
	}

	public static function get_access_token_V2(){
		$petf_options = get_option('Petfinder-Listings');
		if( array_key_exists("apikey_v2", $petf_options) && 
			array_key_exists('apisecret_v2', $petf_options) && 
			trim($petf_options["apikey_v2"]) != '' && 
			trim($petf_options["apisecret_v2"]) != ''){
			    $tokenargs = array(
				'grant_type'    => 'client_credentials',
				'client_id'     => urlencode(trim($petf_options["apikey_v2"])),
				'client_secret' => urlencode(trim($petf_options["apisecret_v2"]))
			);

			//get Petfinder Token so can make a request
			$response = wp_safe_remote_post( "https://api.petfinder.com/v2/oauth2/token", array('timeout' => 120, 'body' => $tokenargs) );
		
			if ( is_wp_error( $response ) ) {
				$error_message = esc_html( $response->get_error_message() );
				echo "<p>Could not get access token from Petfinder: {$error_message}</p>";
			} else {
				$api_response = json_decode( wp_remote_retrieve_body( $response ), true );
				$access_token = $api_response['access_token'];
				return $access_token;
			}
		}
		return null;
	}

	function petf_get_photos_V2($petimages, $petid, $petname){
		$firsttime = true;
		$output_str = "";
		foreach($petimages as $pho){
			$esc_large = esc_url($pho['large']);
			if( $this->petf_options["large_image"] == 'pn'){
				$esc_large = esc_url($pho['medium']);
			}
			if($firsttime){
				$output_str .= "<img class=\"petfinder-big-img\" id=\"img_". intval($petid) . "\"  src=\"" . $esc_large . "\" alt=\"" . esc_attr($petname) . "\">";

				$firsttime = false;
				$output_str .= "<div class=\"petfinder-thumbnails\">";
			}

			$output_str .= "<img class=\"petfinder-thumbnail\" onclick=\"switchbigimg('img_" . intval($petid) . "', '" . $esc_large . "');return false;\" src=\"" . esc_url($pho['small']) . "\" alt=\"" . esc_attr($petname) . "\">";

		}

		if( !$firsttime ){
			//not first time so there are thumbnails to wrap up in a div.  Closing petfinder-thumbnails
			$output_str .= "</div>";
		}
		return $output_str; 
	}

	public function get_pet_info_v2($pet){
		$output_str= "<ul class=\"pet-options\">";
		$icons = "";
		$firsttime = true;
		$breeds = array();
		foreach( $pet['breeds'] as $key => $value ){
			switch($key){
				case 'mixed':
					if( $value ){
						$breeds[] = "Mixed";
					}
					break;
				case 'unknown':
					if($value){
						$breeds[] = "Unknown";
					}
					break;
				default:
					if($value){
						$breeds[] = esc_html($value);
					}
					break;
			}		
		}
		if( count($breeds) > 0){
			$output_str .= "<li class=\"breeds\">";
			$output_str .= implode(', ', $breeds);
			$output_str .= "</li>";
		}

		foreach( $pet['environment'] as $key => $value ){
			switch($key){
				case "cats":
					if(!$value && !is_null($value)){
						$icons .= "<img src=\"https://www.petfinder.com/images/search/no-cat.gif\" width=\"36\" height=\"21\" alt=\"Prefers home without cats\" />";
					}
					break;
				case "dogs":
					if(!$value && !is_null($value)){
						$icons .= "<img src=\"https://www.petfinder.com/images/search/no-dogs.gif\" width=\"41\" height=\"21\" alt=\"Prefers home without dogs\" />";
					}
					break;
				case "children":
					if(!$value && !is_null($value)){
						$icons .= "<img src=\"https://www.petfinder.com/images/search/no-kids.gif\" width=\"34\" height=\"21\" alt=\"Prefers home without small kids\" />";
					}
					break;
			}
		}
		foreach( $pet['attributes'] as $key => $value){
			switch($key){
				case "special_needs":
					if($value){
						$icons .= "<img src=\"https://www.petfinder.com/images/search/spec_needs.gif\" width=\"18\" height=\"20\" alt=\"Special Needs\" title=\"Special Needs\" />";
					}
					break;
				case "spayed_neutered":
					if($value){
						$output_str .= "<li class=\"altered\">Spayed/Neutered</li>";
					}
					break;
				case "shots_current":
					if($value){
						$output_str .= "<li class=\"hasShots\">Vaccinations up to date</li>";
					}
					break;
				case "house_trained":
					if($value){
						$output_str .= "<li class=\"housebroken\">Housebroken</li>";
					}
					break;
				case "declawed":
					if($value){
						$output_str .= "<li class=\"declawed\">Declawed</li>";
					}
					break;
			}
		}
		if($icons != ""){ //$icons does not contain any content not esc_html already or built here
			$output_str .= "<li class=\"icon-options\">" . $icons . "</li>";
		}
		$output_str .= "</ul>";

		return $output_str;
	}

	function single_pet_info_v2($pet, $include_info){
		$type = preg_replace("/[^a-z]/", "", strtolower($pet['type'])); //clean up pet type for class name
						
		$output_str = "<div class=\"dog " . esc_attr($type) . "\">
		<div class=\"name\"><a id=\"" . intval($pet['id']) . "\">". esc_html($pet['name']) . "</a></div>";

		$has_images = false;

		if (count($pet['photos']) > 0) {
			$has_images = true;
			$output_str .= '<div class="images">';
			$output_str .= $this->petf_get_photos_V2($pet['photos'], $pet['id'], $pet['name']);
		}

		if ($include_info == "yes") {
			$output_str .= $this->get_pet_info_V2($pet);
		}

		if($has_images){
			$output_str .= '</div>'; //close .images
		}

		$description = str_replace('&amp;#', '&#', $pet["description"]);
		
		//User Filter added so special characters can be removed from description 
		$description = apply_filters( 'petf_replace_description', $description );

		$allowed_html = self::get_clean_html_args();

		$output_str .= "<div class=\"description\"><p>" . wp_kses( $description, $allowed_html );
		
		if ($this->petf_options['hide_pet_full_description_link'] == "0") {
		    $output_str .= " <a href=\"" . esc_url($pet['url']) . "\" target=\"_blank\">View full description &#187;</a>";
		}
		$output_str .="</p></div>";
		
		$featured = array();
		if(isset($pet['age'])){
			$featured[] = $pet['age'];
		}
		if( isset($pet['gender'])){
			$featured[] = $pet['gender'];
		}
		if( isset( $pet['size'])){
			$featured[] = $pet['size'];
		}
		if( count($featured) > 0 ){ 
			$output_str .="<div class=\"features\">" . esc_html( implode(', ', $featured)) . "</div>";
		}

		$output_str .= "</div>"; //close .dog

		$output_str .= "<div style=\"clear: both; \"></div>";
		return $output_str;
	}
}

new Petfinder_Listings();