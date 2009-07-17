<?php
/*
Plugin Name: Post Google Map
Plugin URI: http://webdevstudios.com/support/wordpress-plugins/
Description: Plugin allows posts to be linked to specific addresses and coordinates and display plotted on a Google Map.  Map shows plots for each post with filter options and preview when hovered. <a href="options-general.php?page=post-google-map/post-google-map.php">Plugin Settings</a> |
Version: 1.3.2
Author: WebDevStudios.com
Author URI: http://webdevstudios.com
*/

$gmp_version = "1.3.2";
//hook for adding admin menus
add_action('admin_menu', 'gmp_menu');

//hook for post/page custom meta box
add_action('admin_menu', 'gmp_meta_box_add');

//save/update post/page custom fields from meta box
$gmp_submit = $_POST["gmp_submit"];
If (isset($gmp_submit) && !empty($gmp_submit)) {
	post_meta_tags();
}

//add_action('edit_post', 'post_meta_tags');
//add_action('publish_post', 'post_meta_tags');
//add_action('save_post', 'post_meta_tags');
//add_action('edit_page_form', 'post_meta_tags');

//hook for loading widget
add_action('plugins_loaded', 'gmp_widget_init');

//call function to delete an address
If ($_GET['deladdy'] != "" && $isdeleted != true) {
	$deladdy = $_GET['deladdy'];
	del_gmp_address($deladdy);
}

function getWords($text, $limit)
{
	$array = explode(" ", strip_tags($text), $limit+1);

	if (count($array) > $limit)
	{
		unset($array[$limit]);
	}

	return implode(" ", $array);
}

function scrub_data($content){
	$content = strip_tags($content);
	$search = array("\r\n", "\n", "\r");
	$replace = '';
	$content = str_replace($search, $replace, $content);
	$content = str_replace("'", "\'", $content);
	return $content;
}
function gmp_widget_init() {
	//widget code
		if ( !function_exists('register_sidebar_widget') )
			return;

		function gmp_widget() {
			global $wpdb;
			global $post;
			$key = get_option('post_gmp_params');
			$imgpath = WP_PLUGIN_URL . '/post-google-map/markers/';

			$gmp_map_type = get_option('post_gmp_map_type');
			$gmp_cats = get_option('post_gmp_cats');
			$gmp_wordpress_loop = get_option('gmp_wordpress_loop');
			$gmp_categories = get_option('gmp_categories');
			$gmp_hide = get_option('gmp_hide');
			$gmp_marker_max = get_option('gmp_marker_max');
			$gmp_marker_order = get_option('gmp_marker_order');

			//print out default categories
			$CT="<div class='map_cats'>";
			$cat=$_GET["cat"];
			$iCat_ID=$_GET["map_cat_id"];
			if (($iCat_ID=="") && ($cat!="")){
				$iCat_ID=$cat;
			}
			if ($gmp_cats!= ""){
				$Cat_Names = explode(",", $gmp_cats);
				for($i = 0; $i < count($Cat_Names); $i++){
					$Cat_ID = $Cat_Names[$i];
					$Cat = get_cat_name($Cat_ID);
					if (!is_single() && $iCat_ID==""){
						//$iCat_ID=$Cat_ID;
					}
					if ($i!=0){
						$CT.=" | ";
					}
					$CT.="<a ";
					if ($Cat_ID==$iCat_ID){
						$CT.="style='font-weight:bold;'";
					}
					$CT.="href=".add_query_arg("map_cat_id", $Cat_ID).">".$Cat."</a>";
				}
			}
			$CT.="</div>";
			$map="var map = new GMap2(document.getElementById('map'));";
			$map.="map.setCenter(new GLatLng(0,0),0);";
			$map.="map.setUIToDefault();";
			$map.="map.setMapType(".$gmp_map_type.");";
			$map.="var bounds = new GLatLngBounds(); ";

			//if viewing a single post don't load plots by categories
			$x=1;
			if ($iCat_ID==""){
				$y=0;
				if (have_posts()) :
					while (have_posts()) : the_post();
						include("get_map.php");
					endwhile;
				endif;
			}
			if ($iCat_ID!="" || $y==0){
				$recentPosts = new WP_Query();
					$recentPosts->query('showposts='.$gmp_marker_max.'&cat='.$iCat_ID);
					while ($recentPosts->have_posts()) : $recentPosts->the_post();
						include("get_map.php");
					endwhile;
			}

			$JS.="map.setZoom(map.getBoundsZoomLevel(bounds));";
			$JS.="map.setCenter(bounds.getCenter());";
			$JS.="map.zoomOut(); ";

			$JS = $map.$JS;
			echo $CT;
			?>
			<script src="http://maps.google.com/maps?file=api&v=1&key=<?php echo $key; ?>" type="text/javascript"></script>
			<body onUnload="GUnload()">
			<div id="map" style="width:100%;height:200px;"></div>
			<div id="map-info"></div>
			<script type="text/javascript">
				<?php echo $JS; ?>
				var info = document.getElementById('map-info');
                info.innerHTML = '<?php echo $Default_HTML; ?>';
			</script>
			<?php
		}

		if ( function_exists('wp_register_sidebar_widget') ) // fix for wordpress 2.2.1
		  wp_register_sidebar_widget(sanitize_title('Post Google Map' ), 'Post Google Map', 'gmp_widget', array(), 1);
		else
		  register_sidebar_widget('Post Google Map', 'gmp_widget', 1);

}

function gmp_meta_box_add() {
	// Check whether the 2.5 function add_meta_box exists, and if it doesn't use 2.3 functions.
	if ( function_exists('add_meta_box') ) {
		add_meta_box('gmp','Post Google Map','gmp','post');
		add_meta_box('gmp','Post Google Map','gmp','page');
	} else {
		//add_action('dbx_post_advanced', array($aiosp, 'add_meta_tags_textinput'));
		//add_action('dbx_page_advanced', array($aiosp, 'add_meta_tags_textinput'));
	}
}

function del_gmp_address($deladdy) {
	//delete address from a post/page
	If (is_numeric($deladdy)) {
		$id = $_GET['post'];
		$gmp_arr = get_post_meta($id, 'gmp_arr', false);
		If (is_array($gmp_arr)) {
			delete_post_meta($id, 'gmp_arr');
			unset($gmp_arr[$deladdy]);
			for ($row = 0; $row <= count($gmp_arr); $row++)
			{
				If (is_array($gmp_arr[$row])) {
					add_post_meta($id, 'gmp_arr', $gmp_arr[$row]);
				}
			}
			//echo "<div id=message class=updated fade>Address deleted successfully.</div>";
			$isdeleted = true;
		}
	}
}

function post_meta_tags() {
	global $wpdb, $alreadyran;
	$gmp_id = $_POST["gmp_id"];

	//if post not created yet create it
	if ($gmp_id==0){
		$title=$_POST["post_title"];
		$sql = "SELECT ID FROM ".$wpdb->prefix."posts order by ID desc LIMIT 1";
		$rs = mysql_query($sql);
		if ($rs) {
			while ($r = mysql_fetch_assoc($rs)) {
				$gmp_id=$r['ID'];
			}
		}
	}

	//save the form data from the post/page meta box
	if (isset($gmp_id) && !empty($gmp_id) && $alreadyran != "1") {
		$id = $gmp_id;
		$alreadyran = "1";

		//get post data
		$gmp_long = $_POST["gmp_long"];
		$gmp_lat = $_POST["gmp_lat"];
		$gmp_address1 = $_POST["gmp_address1"];
		$gmp_address2 = $_POST["gmp_address2"];
		$gmp_city = $_POST["gmp_city"];
		$gmp_state = $_POST["gmp_state"];
		$gmp_zip = $_POST["gmp_zip"];
		$gmp_marker = $_POST["gmp_marker"];
		$gmp_title = $_POST["gmp_title"];
		$gmp_description = $_POST["gmp_description"];
		$gmp_desc_show = $_POST["gmp_desc_show"];

		//get long & lat BRM
		if (isset($gmp_long) && !empty($gmp_long) && isset($gmp_lat) && !empty($gmp_lat)) {
		}elseif (isset($gmp_address1) && !empty($gmp_address1)){
			$key = get_option('post_gmp_params');
			$addressarr = array($gmp_address1, $gmp_city, $gmp_state, $gmp_zip);
			$address = IMPLODE(",", $addressarr);
			$iaddress = "http://maps.google.com/maps/geo?q=".urlencode($address)."&output=csv&key=".$key."";
			//$csv = file_get_contents($iaddress);

			//use the WordPress HTTP API to call the Google Maps API and get coordinates
			$csv = wp_remote_get($iaddress);
			$csv = $csv["body"];

			$csvSplit = split(",", $csv);
			$status = $csvSplit[0];

			$lat = $csvSplit[2];
			$lng = $csvSplit[3];
			if (strcmp($status, "200") == 0){
				// successful
				$lat = $csvSplit[2];
				$lng = $csvSplit[3];
			}
			$gmp_long=$lat;
			$gmp_lat=$lng;
		}

		//create an array from the post data and long/lat from Google
		$gmp_arr=array(
			"gmp_long"=>$gmp_long,
			"gmp_lat"=>$gmp_lat,
			"gmp_address1"=>$gmp_address1,
			"gmp_address2"=>$gmp_address2,
			"gmp_city"=>$gmp_city,
			"gmp_state"=>$gmp_state,
			"gmp_zip"=>$gmp_zip,
			"gmp_marker"=>$gmp_marker,
			"gmp_title"=>$gmp_title,
			"gmp_description"=>$gmp_description,
			"gmp_desc_show"=>$gmp_desc_show,
			);

		//save address array as option gmp_arr
		add_post_meta($id, 'gmp_arr', $gmp_arr);
		//echo "<div id=message class=updated fade>Address added successfully.</div>";
	}

}

function gmp() {

	global $post;

	$post_id = $post;
	if (is_object($post_id)){
		$post_id = $post_id->ID;
	}

	$gmp_api_key = get_option('post_gmp_params');

	$gmp_arr = get_post_meta($post_id, 'gmp_arr', false);

	$imgpath = WP_PLUGIN_URL . '/post-google-map/markers/';

	?>
	<form method="post">
		<input value="<?php echo $post_id; ?>" type="hidden" name="gmp_id" />
        <?php If ($gmp_api_key == "") { ?>
            <div class="error">
                <p>
                    <strong>
                    Google Maps API key has not been saved.
                    <a href="<?php echo admin_url( 'options-general.php?page=post-google-map.php' ); ?>">Enter Google Maps API Key</a>
                    to enable post mapping.
                    </strong>
                </p>
            </div>
        <?php } ?>
        <div style="padding-bottom:10px;">Current Saved Addresses:</div>
        <table cellspacing="0" cellpadding="3" width="100%" style="margin-bottom:20px">
        	<tr>
            	<td colspan="2"></td>
                <td><strong>Address 1</strong></td>
                <td><strong>Address 2</strong></td>
                <td><strong>City</strong></td>
                <td><strong>State</strong></td>
                <td><strong>Zip</strong></td>
            </tr>
            <?php
			If (is_array($gmp_arr)) {
				$bgc=="";
				for ($row = 0; $row < count($gmp_arr); $row++)
				{
				if($bgc==""){
					$bgc="#eeeeee";
				}else{\
					$bgc="";
				}
				?>
                    <tr style="background:<?php echo $bgc;?> !important;" bgcolor="<?php echo $bgc;?>">
                        <td><a title="Delete Address" href="<?php echo add_query_arg ("deladdy", $row); ?>"><img width="15px" border="0" src="<?php echo WP_PLUGIN_URL . '/post-google-map/delete.png';?>"></a></td>
                    	<td><img width="25px" src="<?php echo $imgpath.$gmp_arr[$row]["gmp_marker"]; ?>"></td>
                        <td><?php echo $gmp_arr[$row]["gmp_address1"]; ?></td>
                        <td><?php echo $gmp_arr[$row]["gmp_address2"]; ?></td>
                        <td><?php echo $gmp_arr[$row]["gmp_city"]; ?></td>
                        <td><?php echo $gmp_arr[$row]["gmp_state"]; ?></td>
                        <td><?php echo $gmp_arr[$row]["gmp_zip"]; ?></td>
                   	</tr>
                    <tr style="background:<?php echo $bgc;?> !important;" bgcolor="<?php echo $bgc;?>">
                    	<td colspan="2"></td>
                        <td colspan="5">
                        	<?php echo $gmp_arr[$row]["gmp_title"];
                            if ($gmp_arr[$row]["gmp_description"]!=""){
                            	echo " - ";
							}
							echo $gmp_arr[$row]["gmp_description"]; ?>
                        </td>
                    </tr>
            	<?php
				}
			}Else{
				?><tr><td colspan="6" align="center"><i>no addresses saved</i></td></tr><?php
			}
				?>
        </table>
		<div style="padding-bottom:10px;">Enter an address or coordinates to plot this post/page on a Google Map.  You can enter multiple addresses</div>
		<table style="margin-bottom:20px">
            <tr>
            <th style="text-align:right;" colspan="2">
            </th>
            </tr>
            <tr>
            <th scope="row" style="text-align:right;"><?php _e('Marker') ?></th>
            <td>
            	<select name="gmp_marker">
                	<?php
					$dir = $_SERVER["DOCUMENT_ROOT"].'/wp-content/plugins/post-google-map/markers/';
					$x=0;
					if (is_dir($dir)){
						if ($handle = opendir($dir)) {
							while (false !== ($file = readdir($handle))) {
								if ($file<>"."&&$file<>".."){
									$x=1;
									echo "<option value='".$file."' style='background: url(".$imgpath.$file.")no-repeat;text-indent: 30px;height:25px;'>".$file;
								}
							}
							closedir($handle);
						}
					}
					?>
                </select>
            </td>
            </tr>
            <tr>
            <th scope="row" style="text-align:right;"><?php _e('Title') ?></th>
            <td><input value="" type="text" name="gmp_title" size="25" tabindex=91 />*If blank will use post title.</td>
            </tr>
            <tr>
            <th valign="top" scope="row" style="text-align:right;"><?php _e('Description') ?></th>
            <td><textarea name="gmp_description" style="width:300px;" tabindex=92 ></textarea><br>
            <input checked type="checkbox" name="gmp_desc_show"> Use excerpt or first ten words of post if excerpt is blank.
            </td>
            </tr>
            <tr>
            <th scope="row" style="text-align:right;"><?php _e('Address 1') ?></th>
            <td><input value="" type="text" name="gmp_address1" size="25" tabindex=93 /></td>
            </tr>
            <tr>
            <th scope="row" style="text-align:right;"><?php _e('Address 2') ?></th>
            <td><input value="" type="text" name="gmp_address2" size="25" tabindex=94 /></td>
            </tr>
            <tr>
            <th scope="row" style="text-align:right;"><?php _e('City') ?></th>
            <td><input value="" type="text" name="gmp_city" size="25" tabindex=95 /></td>
            </tr>
            <tr>
            <th scope="row" style="text-align:right;"><?php _e('State') ?></th>
            <td><input value="" type="text" name="gmp_state" size="15" tabindex=96 /></td>
            </tr>
            <tr>
            <th scope="row" style="text-align:right;"><?php _e('Zip Code') ?></th>
            <td><input value="" type="text" name="gmp_zip" size="10" tabindex=97 /></td>
            </tr>
            <tr>
            	<th scope="row" style="text-align:right;"></th>
            	<td>OR</td>
            </tr>
            <tr>
            <th scope="row" style="text-align:right;"><?php _e('Longitude') ?></th>
            <td><input value="" type="text" name="gmp_long" size="20" tabindex=98 /></td>
            </tr>
            <tr>
            <th scope="row" style="text-align:right;"><?php _e('Latitude') ?></th>
            <td><input value="" type="text" name="gmp_lat" size="20" tabindex=99 /></td>
            </tr>
            <tr>
            <th scope="row"></th>
            <td>
            	<div class="submit">
                	<input type="submit" name="gmp_submit" value="Add Address" tabindex=100  /><br>*Post title must exist to save address
            	</div>
            </td>
            </tr>
		</table>
        </form>
	<?php
}

function gmp_menu() {
  add_options_page('Post Google Map Options', 'Post Google Map', 8, __FILE__, 'gmp_options');
}

//Function to save the plugin settings
function update_options()
{
	check_admin_referer('gmp_check');
	$options = $_POST['google_api_key'];
	update_option('post_gmp_params', $options);
	update_option('post_gmp_cats', $_POST['gmp_cats'] );
	update_option('post_gmp_map_type', $_POST['gmp_map_type'] );
	update_option('gmp_wordpress_loop', $_POST['gmp_wordpress_loop'] );
	update_option('gmp_categories', $_POST['gmp_categories'] );
	update_option('gmp_hide', $_POST['gmp_hide'] );
	update_option('gmp_marker_max', $_POST['gmp_marker_max'] );
	update_option('gmp_marker_order', $_POST['gmp_marker_order'] );
} # update_options()


function gmp_options() {
		# Acknowledge update

		if ( isset($_POST['update_gmp_options'])
			&& $_POST['update_gmp_options']
			)
		{
			update_options();

			echo "<div class=\"updated\">\n"
				. "<p>"
					. "<strong>"
					. __('Settings saved.')
					. "</strong>"
				. "</p>\n"
				. "</div>\n";
		}

	$options = get_option('post_gmp_params');
	$options_cats = get_option('post_gmp_cats');
	$options_map_type = get_option('post_gmp_map_type');
	$gmp_wordpress_loop = get_option('gmp_wordpress_loop');
	$gmp_categories = get_option('gmp_categories');
	$gmp_hide = get_option('gmp_hide');
	$gmp_marker_max = get_option('gmp_marker_max');
	$gmp_marker_order = get_option('gmp_marker_order');

	echo '<div class="wrap">';
	echo '<h2>' . __('Post Google Map Settings') . '</h2>';
	echo 'You must have a Google Maps API Key for this plugin to work.  ';
	echo '<form method="post" action="">';
	if ( function_exists('wp_nonce_field') ) wp_nonce_field('gmp_check');
	echo '<input type="hidden" name="update_gmp_options" value="1">';
	echo '<p><b>Google API Key</b>&nbsp;';
	echo '<input type="text"'
		. ' name="google_api_key" id="google_api[api_key]" size="100"'
		. ( $options['api_key'] ? 'value="'.$options.'"' : '' )
		. ' />';
	echo '<p>You can obtain a free Google Maps API Key here: <a href="http://code.google.com/apis/maps/signup.html" target="_blank">http://code.google.com/apis/maps/signup.html</a></p>';
	?>
	<b>Default Map Settings</b>
	<table>
	<tr>
	<td align=right>Map Type:</td>
	<td>
		<select name="gmp_map_type">
			<option value="G_NORMAL_MAP" <?php If ($options_map_type == "G_NORMAL_MAP") { echo "SELECTED"; }?>>Map
			<option value="G_SATELLITE_MAP" <?php If ($options_map_type == "G_SATELLITE_MAP") { echo "SELECTED"; }?>>Satellite
			<option value="G_HYBRID_MAP" <?php If ($options_map_type == "G_HYBRID_MAP") { echo "SELECTED"; }?>>Hybrid
			<option value="G_DEFAULT_MAP_TYPES" <?php If ($options_map_type == "G_DEFAULT_MAP_TYPES") { echo "SELECTED"; }?>>Terrain
            <option value="G_PHYSICAL_MAP" <?php If ($options_map_type == "G_PHYSICAL_MAP") { echo "SELECTED"; }?>>Physical
		</select>
	</td>
	</tr>
    <!--<tr>
		<td valign="top" align=right>Marker View:</td>
		<td>
        	<input type="checkbox" name="gmp_wordpress_loop" value="1" <?php if ($gmp_wordpress_loop==1){ ?>checked<?php } ?>>WordPress Loop
			<input type="checkbox" name="gmp_categories" value="1" <?php if ($gmp_categories==1){ ?>checked<?php } ?>>Categories<br>*if both are checked map will show categories if loop is empty.
		</td>
	</tr>
	<tr>
				<td align="right"></td>
				<td>
					<input type="checkbox" name="gmp_hide" value="1" <?php if ($gmp_hide==1){ ?>checked<?php } ?>>Hide map if ever empty.
				</td>
	</tr>-->
    <tr>
			<td align="right" valign="top">Category Tabs:</td>
			<td valign="top">
				<input type="text" name="gmp_cats" value="<?php echo get_option('post_gmp_cats'); ?>">*Category IDs(ie 1,2,3)
			</td>
	</tr>

	<tr>
			<td align=right>Marker Plot Max:</td>
			<td>
				<select name="gmp_marker_max">
					<?php for ($x = 0; $x <50;){
						$x=$x+5;
						?>
						<option value='<?php echo $x?>' <?php if ($gmp_marker_max==$x){ ?>selected<?php } ?>><?php echo $x?>
                        <?php
                    }
					?>


				</select>
                *per page load
			</td>
	</tr>
	<!--<tr>
		<td align="right">Marker Order:</td>
		<td>
			<?php echo $gmp_marker_order;?>
            <input type="radio" name="gmp_marker_order" value="Newest" checked>Newest
			<input type="radio" name="gmp_marker_order" value="Random" <?php if ($gmp_marker_order=="Random"){ ?>checked<?php } ?>>Random
		</td>
	</tr>-->

	</table>

	<?php
	echo '<p class="submit">'
	. '<input type="submit"'
		. ' value="' . attribute_escape(__('Save Changes')) . '"'
		. ' />'
	. '</p></form>';
	echo '<p>For support please visit our <a href="http://webdevstudios.com/support/wordpress-plugins/" target="_blank">WordPress Plugins Support page</a> | Version 1.3.2 by <a href="http://webdevstudios.com/" title="WordPress Development and Design" target="_blank">WebDevStudios.com</a> | <a href="http://twitter.com/webdevstudios" target="_blank">Twitter</a></p>';
	echo '</div>';
}

function gmp_get_post_image($post_id, $width=0, $height=0) {
	$dimensions = "";
	$post_id = explode(",", $post_id);
	foreach ($post_id as $id) {
		$my_query = new WP_Query('p='.$id);
		while ($my_query->have_posts()) : $my_query->the_post();
			$attargs = array(
			'numberposts' => 1,
			'order' => 'ASC',
			'orderby' => 'menu_order',
			'post_type' => 'attachment',
			'post_mime_type' => 'image',
			'post_parent' => $id);
			$attachments = get_children($attargs);
			if ($attachments) {
				foreach ($attachments as $attachment) {
					If ($width != "full" && $width != "medium" && $width != "thumb" && $width != "large") {
						return wp_get_attachment_image($attachment->ID, array($width, $height), false);
					}Else{
						return wp_get_attachment_image($attachment->ID, $width, false);
					}
				}
			}
		endwhile;
	}
}
?>