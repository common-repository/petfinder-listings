<?php
class Petfinder_Listings_Featured_Pet extends WP_Widget {

    private $token_error_msg;

	public function __construct() {
		parent::__construct(
	 		'petfw', // Base ID
             __('List Petfinder Pets: Featured Pet', 'petf'), // Name
			array( 'description' => __( '' ), ) // Args
        );
        
        $this->petf_options = get_option('Petfinder-Listings');

        $this->token_error_msg = Petfinder_Listings::get_token_error_msg();
	}
    

    /*** @see WP_Widget::widget()
     Front End Display **/
    function widget($args, $instance) {
        $title = apply_filters( 'widget_title', $instance['title'] );
        $output_buffer = "";
        $output_buffer .=  $args['before_widget'];
        if ( $title ) {
            $output_buffer .=  $args['before_title'] . $title . $args['after_title'];
        }

        $access_token = Petfinder_Listings::get_access_token_V2(); 
        if ($access_token) {
            //now get pets
            $args2 = array( 
                'timeout'      => 120, 
                'headers'      => array('Authorization' => 'Bearer ' . $access_token )
            );
            $pet_id = $instance["featured_pet_id"];
            $random = false;
            if ( is_numeric($pet_id) && intval($pet_id) > 0 ) {
                $response = wp_safe_remote_get( "https://api.petfinder.com/v2/animals/" . intval($pet_id) , $args2);
            }else{
                //get random ID from all (first 100 limit) animals in shelter
                $shelter_id = $this->petf_options['shelter_id'];
                $querystr = '?organization=' . urlencode( trim($shelter_id) ) . '&limit=100&status=adoptable';
                $response = wp_safe_remote_get( "https://api.petfinder.com/v2/animals/" . $querystr, $args2);
                $random = true;
            }
            
            if ( is_wp_error( $response ) ) {
                $error_message = esc_html($response->get_error_message());
		        $output_buffer .= '<p>' . ( sprintf( __("Something went wrong when retrieving informaton from Petfinder: %s", 'petf'), $error_message) ) . '</p>';
            } else {
                $retdata = json_decode( wp_remote_retrieve_body( $response ), true );

                //get pet - different depending on which data retrieved
                $pet = null;
                if($random){
                    if ( array_key_exists('animals', $retdata) && count($retdata['animals']) > 0 ) {
                        $pets  = $retdata['animals'];
                        $total = count($pets);
                        $spot  = rand ( 1 , $total );
                        $pet   = $pets[$spot - 1];
                    }
                }elseif( array_key_exists('animal', $retdata) && count($retdata['animal']) > 0 ) {
                    $pet = $retdata['animal'];
                }
                if ($pet) {
                    $output_buffer .= "<div id=\"featured_pet\">
                    <div class=\"featured_pet_name\">";
                    if ( $instance['full_list_page'] != "" ) {
                        $output_buffer .= "<a href=\"" . esc_url( $instance['full_list_page'] ) . "#" . esc_attr( intval($pet["id"]) ) . "\">";
                    }
                    $output_buffer .= esc_html( $pet['name'] );
                    if ($instance['full_list_page'] != "") {
                        $output_buffer .= "</a>";
                    }    
                    $output_buffer .= "</div> <!-- close .featured_pet_name -->";
                    if (count($pet['photos']) > 0) {
                        if ($instance['full_list_page'] != "") {
                            $output_buffer .= "<a href=\"" . esc_url( $instance['full_list_page'] ) . "#" . esc_attr( intval($pet["id"]) ) . "\">";
                        }
                        foreach ($pet['photos'] as $pho) {
                            if ( $instance["featured_pet_image"] == 'pn' ) {
                                $output_buffer .= "<img class=\"petfinder-featured\"  alt=\"" . esc_attr( $pet['name'] ) . "\" src=\"" . esc_url( $pho['medium'] ) . "\">";
                            } else {
                                $output_buffer .= "<img class=\"petfinder-featured\"  alt=\"" . esc_attr( $pet['name'] ) . "\" src=\"" . esc_url( $pho['large']) . "\">";
                            }                            
                            break; // just get one photo
                        }
                        if ($instance['full_list_page'] != "") {
                            $output_buffer .= "</a>";
                        }
                    }

                    $description_size = intval( $instance["featured_pet_copy_size"] );
            
                    if ( $description_size > -1 ) {

                        if( $description_size == 0 ) { 
                            $description = str_replace('&amp;#', '&#', $pet["description"]);
                            $description = apply_filters( 'petf_replace_description', $description );

                            $allowed_html = Petfinder_Listings::get_clean_html_args();

                            $output_buffer .= '<p>' . wp_kses( $description, $allowed_html );
                            if ($this->petf_options['hide_pet_full_description_link'] == "0") {
                                $output_buffer .= " <a href=\"" . esc_url( $pet['url'] ) . "\" target=\"_blank\">View full description &#187;</a>";
                            }
                            $output_buffer .= "</p>";
                        }else{
                            $description = wp_trim_words( $pet["description"], $description_size ); //wp_trim_words strips all tags from text
                            $output_buffer .= '<p>' . $description;
                            if ($this->petf_options['hide_pet_full_description_link'] == "0") {
                                $output_buffer .= " <a href=\"" . esc_url( $pet['url'] ) . "\" target=\"_blank\">View full description &#187;</a>";
                            }
                            $output_buffer .= '</p>';
                        }
                    }
                    if ($instance['full_list_page'] != "") {
                        //no copy, but display More link to pet on site
                        $output_buffer .= "<p><a href=\"" . esc_url( $instance['full_list_page'] ) . "#" . intval($pet["id"]) . "\">... See More &gt;</a></p>";
                    }
                    if ($instance["featured_pdf_link"] != "") {
                        $output_buffer .= "<p><a href=\"" . esc_url( $instance["featured_pdf_link"] ) . "\" target=\"_blank\">View Featured Pet PDF</a></p>";
                    }

                    $output_buffer .= "</div> <!-- close #featured_pet -->"; 
                }else{
                    $output_buffer .=  '<p>' . __("No Featured Pet Returned.", 'petfw') . '</p>';
                }
            }
            $output_buffer .= $args['after_widget'];
            echo $output_buffer;
        }else{
            echo $this->token_error_msg;
        }       
    }

    /** @see WP_Widget::update */
    function update( $new_instance, $old_instance ) {
	    $instance = $old_instance;
		$instance['title'] = sanitize_text_field( $new_instance['title'] );
		$instance['featured_pet_id'] = ($new_instance['featured_pet_id'] != '') ? intval( $new_instance['featured_pet_id']) : '';
        $instance['full_list_page'] = esc_url_raw( $new_instance['full_list_page'] );
        $instance['featured_pet_image'] = sanitize_text_field( $new_instance['featured_pet_image'] );
        $instance['featured_pet_copy_size'] = intval( $new_instance['featured_pet_copy_size'] );
		$instance['featured_pdf_link'] = esc_url_raw( $new_instance['featured_pdf_link'] );
		return $instance;
    }

    /** @see WP_Widget::form */
    function form( $instance ) {
        // Defaults
		$instance = wp_parse_args( (array) $instance, array(
			'title' => '',
			'featured_pet_id' => '',
            'full_list_page' => '',
            'featured_pet_image' => '',
            'featured_pet_copy_size' => 0,
            'featured_pdf_link' => ''
		));

        ?>
         <p>
          <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'petfw'); ?></label>
          <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($instance['title']); ?>" />
        </p>
        <p>
          <label for="<?php echo $this->get_field_id('featured_pet_id'); ?>"><?php _e('Featured Pet ID: Get from Petfinder URL after the pet\'s name. (If left blank, will select a random pet.):', 'petfw'); ?></label>
          <input class="widefat" id="<?php echo $this->get_field_id('featured_pet_id'); ?>" name="<?php echo $this->get_field_name('featured_pet_id'); ?>" type="text" value="<?php echo intval($instance['featured_pet_id']); ?>" />
        </p>
        <p>
          <label for="<?php echo $this->get_field_id('full_list_page'); ?>"><?php _e('Your Listing Page URL (Set if you want the featured pet to link to the same pet on your list page):', 'petfw'); ?></label>
          <input class="widefat" id="<?php echo $this->get_field_id('full_list_page'); ?>" name="<?php echo $this->get_field_name('full_list_page'); ?>" type="text" value="<?php echo esc_url($instance['full_list_page']); ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('featured_pet_image')?>"><?php _e('Image Size', 'petfw');?></label>
            <select id="<?php echo $this->get_field_id('featured_pet_image'); ?>" name="<?php echo $this->get_field_name('featured_pet_image'); ?>">
            <option value="pn" <?php echo $instance['featured_pet_image'] == "pn" ? "selected='selected'" : ""?>>up 300px wide</option>
            <option value="x" <?php echo $instance['featured_pet_image'] == "x" ? "selected='selected'" : ""?>>up to 600px</option>
            
        </select>
        </p>

        <p>
            <label for="<?php echo $this->get_field_id('featured_pet_copy_size')?>"><?php _e('Featured Pet Copy Size (enter number of words to display from pet\'s description, 0 to display full description, or -1 to not display description )', 'petfw');?></label>
            <input type="text" id="<?php echo $this->get_field_id('featured_pet_copy_size') ?>" name="<?php echo $this->get_field_name('featured_pet_copy_size') ?>" value="<?php echo intval($instance['featured_pet_copy_size']) ?>" />
        </p>
		<p>
			<label for="<?php echo $this->get_field_id('featured_pdf_link'); ?>"><?php _e('Featured Pet PDF Link (Enter link to a separately uploaded PDF if you want to upload a PDF with more details about featured pet.):', 'petfw'); ?></label>
			<input type="text" id="<?php echo $this->get_field_id('featured_pdf_link') ?>" name="<?php echo $this->get_field_name('featured_pdf_link') ?>" value="<?php echo esc_url($instance['featured_pdf_link']) ?>" />
		</p>
        <?php
    }
}
