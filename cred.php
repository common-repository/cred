<?php
/*
 Plugin Name: Cred Paywall
 Plugin URI: http://www.yourcred.com/plugins/wordpress
 Description: This plugin allows you to generate revenue from your blog by integrating to the Cred micro-payment system. This effectively puts a paywall on your site for content you choose to be paywalled. Cred is a micro-payment system tailored specifically for content. From news, audio, video and music as well as any virtual content we provide a payment method for that content. Instead of the user having to tediously enter their credit card details into every site they buy content on, they rather enter it once and redeem wherever the Cred system is available. Visit http://www.yourcred.com for more information. 
 Version: 1.0
 Author: YourCred.com
 Author URI: http://www.yourcred.com/
 */

define('CRED_VERSION', '1.0');
require_once("functions.php");
require_once("compat.php");

/**
 * This function executes on every page, before it is rendered
 */
function cred_init() {
	global $cred_api_key; // not actually used

	// Store the ABSPATH variable in session scope for use in our popups
	session_start();
	if (!isset($_SESSION['ABSPATH'])) {
		$_SESSION['ABSPATH'] = ABSPATH;
	}
   session_write_close();
	
   // check for API key
	add_action('admin_menu', 'cred_config_page');
	cred_admin_warnings();

	$user = wp_get_current_user();
   $cred_user_id = get_usermeta($user->ID, 'cred_id');
	
	// check for a login request
	if ( $_POST['cred_login'] != null ) {
		if ( $user == null || $user->ID == 0 ) {
			$user = cred_user_login( $_POST['username'], $_POST['usercode'] );
			if (is_wp_error($user)) {
				echo "<script>alert('Authentication error: " . $user->get_error_message() . "'); window.cred_auth_error=true;</script>";
			}
		}
	}

   if ($cred_user_id != null) {
	   // check for subscription request
	   if ( $_POST['cred_subscription_uri'] != null ) {
	      $api_call = "/api/user/subscribe/".
	      $cred_user_id.".json?usercode=".$_POST['token'].
	              "&api_key=".get_option("cred_api_key").
	              "&uri=".$_POST['cred_subscription_uri'];
	      $output = cred_get_contents($api_call);
	      if ($output == FALSE) $post->post_excerpt .= "<script>" .
	           "alert('Unable to connect to Cred server. Please try again later.');</script>";
	      $obj = json_decode($output, true);
	      if ($obj['error']) {
	         /*if ($obj['exception'] == "User does not have enough cred") {
	          $obj['exception'] .= ". <a href='". CRED_URL . "/user' target='_blank'>Click here to buy more cred</a>";
	          }*/
	         echo "<script>cred.error('Unable to redeem: ".$obj['exception'] . "');</script>";
	      } else {
	         // reload subscriptions
	         cred_get_user_subscriptions(get_usermeta($user->ID, 'cred_id'), $user->ID);
	
	         return $content;
	      }
	   }
	
	   // check for subscription expiry
	   $expiry_string = get_usermeta($user->ID, 'cred_next_expiry');
	   if ( $expiry_string != null ) {
	   	$expiry = strtotime($expiry_string);
         //var_dump($expiry - strtotime("now"));
	   	if ($expiry - strtotime("now") <= 0) {
            // reload subscriptions
            cred_get_user_subscriptions(get_usermeta($user->ID, 'cred_id'), $user->ID);	   		
	   	}
	   }   	
   }
}
add_action('init', 'cred_init');

function cred_admin_warnings() {
	global $cred_api_key;

	if ( !get_option('cred_api_key') && !$cred_api_key && !isset($_POST['submit']) ) {
		function cred_warning() {
			echo "
			<div id='cred-warning' class='updated fade'>
			<p><strong>".__('Cred is almost ready.')."</strong> 
			".sprintf(__('You must <a href="%1$s">enter your YourCred.com API key</a> for it to work.'), 
			   "plugins.php?page=cred-key-config")."
			</p></div>
			";
		}
		add_action('admin_notices', 'cred_warning');
		return;
	}
}

function cred_config_page() {
	if ( function_exists('add_submenu_page') )
	add_submenu_page('plugins.php', __('Cred Configuration'), __('Cred Configuration'), 'manage_options', 'cred-key-config', 'cred_conf');

}

function cred_conf() {
	global $cred_api_key;
	global $cred_logo_size;
	if ( isset($_POST['submit']) ) {
		if ( function_exists('current_user_can') && !current_user_can('manage_options') )
		die(__('Cheatin&#8217; uh?'));

		//$key = preg_replace( '/[^a-h0-9]/i', '', $_POST['key'] );
		$key = $_POST['key'];
		if ( empty($key) ) {
			delete_option('cred_api_key');
		} else {
			add_option('cred_api_key', $key);
			update_option('cred_api_key', $key);
		}
		$cred_api_key = $key;
		
		//mvw
		$LSize = $_POST['LogoSize'];
		add_option('cred_logo_size',$LSize);
		update_option('cred_logo_size',$LSize);
		$cred_logo_size = $LSize;
	}

?>

   <?php if ( !empty($_POST['submit'] ) ) : ?>
		<div id="message" class="updated fade">
		<p><strong><?php _e('Options saved. You may now add new posts or edit old posts and select Cred Options to suit.') ?></strong></p>

	</div>

	<?php endif; ?>
   <link rel="stylesheet"
      href="<?php echo WP_PLUGIN_URL ?>/cred/cred.css" type="text/css"
      media="screen" />
      
	<div class="wrap">
	<h2><?php _e('Cred Configuration'); ?></h2>
	<div class="narrow">
	<form action="" method="post" name="cred-conf" id="cred-conf" style="margin: auto; width: 400px;">
	<h3><label for="key"><?php _e('YourCred.com API Key'); ?></label></h3>
	<p><input id="key" name="key" type="text" size="40" maxlength="50"
		value="<?php echo $_GET['ak'] == null ? get_option('cred_api_key') : $_GET['ak']; ?>"
		style="font-family: 'Courier New', Courier, mono; font-size: 1.5em;" />	   
		<div class="info" style="font-size: 1.2em;">
	      <a href="<?php _e(CRED_URL); ?>/welcome/provider_signup_form">Sign up for an API key</a> if you have not already done so, it is free!
	      If you already have a Cred account, <a href="<?php _e(CRED_URL); ?>/login">sign in</a> to access your API key at the bottom of your dashboard page.
		</div>
	</p> <br />
	
<!-- MVW - Logo Size Selectbox--> 
	<h4><label for="logosize"><?php _e('Cred Logo Size'); ?></label></h4>
	<p>
    
	<select name="LogoSize" onchange="previewLogo(this)">
      <option value="credLogoSmall" <?php echo (get_option('cred_logo_size') == 'credLogoSmall' ? 'selected' : '') ?>>Small  16x16</option>
      <option value="credLogoMedium" <?php echo (get_option('cred_logo_size') == 'credLogoMedium' ? 'selected' : '') ?>>Medium 24x24</option>
      <option value="credLogoLarge" <?php echo (get_option('cred_logo_size') == 'credLogoLarge' ? 'selected' : '') ?>>Large 32x32</option>
    </select>
    
      <div class='<?php echo get_option('cred_logo_size') ?>' id='cred_preview'> 
         Click here to redeem for 10 cred
      </div>
	</p>
	

	
	<p class="submit"><input type="submit" name="submit"
		value="<?php _e('Update options &raquo;'); ?>" /></p>
	</form>
	</div>
	</div>

   <script type="text/javascript">
      function previewLogo(selectBox) {
          var logoSize = selectBox.options[selectBox.selectedIndex].value;
          var div = document.getElementById("cred_preview");
          div.className = logoSize;
      }
   </script>

<?php
} // cred_conf()

/* Use the admin_menu action to define the custom boxes */
add_action('admin_menu', 'cred_add_custom_box');
/* Adds a custom section to the "advanced" Post and Page edit screens */
function cred_add_custom_box() {

	if( function_exists( 'add_meta_box' )) {
		add_meta_box( 'cred_sectionid', __( 'Cred Options', 'cred_textdomain' ),
                'cred_inner_custom_box', 'post', 'advanced' );
		add_meta_box( 'cred_sectionid', __( 'Cred Options', 'cred_textdomain' ),
                'cred_inner_custom_box', 'page', 'advanced' );
	} else {
		add_action('dbx_post_advanced', 'cred_old_custom_box' );
		add_action('dbx_page_advanced', 'cred_old_custom_box' );
	}
}

/* Use the save_post action to do something with the data entered */
add_action('save_post', 'cred_save_postdata');
/* When the post is saved, saves our custom data */
function cred_save_postdata( $post_id ) {
   // verify this came from the our screen and with proper authorization,
   // because save_post can be triggered at other times

   if ( !wp_verify_nonce( $_POST['cred_noncename'], plugin_basename(__FILE__) )) {
      return $post_id;
   }

   // verify if this is an auto save routine. If it is our form has not been submitted, so we dont want
   // to do anything
   if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
      return $post_id;

   // Check permissions
   if ( 'page' == $_POST['post_type'] ) {
      if ( !current_user_can( 'edit_page', $post_id ) )
      return $post_id;
   } else {
      if ( !current_user_can( 'edit_post', $post_id ) )
      return $post_id;
   }

   // OK, we're authenticated: we need to find and save the data
   $paywall = $_POST['_cred_paywall'] or $paywall = "0";
   add_post_meta($post_id, '_cred_paywall', $paywall, true) or 
      update_post_meta($post_id, '_cred_paywall', $paywall);

   $price = $_POST['_cred_price'] or $price = '0';
   if($paywall != '0') {
     //mvw - whole number validation 
      if((is_numeric($price)) && ($price >= 0) && ($price <= 100)) {
          add_post_meta($post_id, '_cred_price', $price, true) or 
            update_post_meta($post_id, '_cred_price', $price);
      } else {   
          wp_die( __('Cred Price should be between (and including) 0 and 100. Please '.
            '<a href="javascript:history.go(-1);">click here</a> to go back'),'Cred Price Validation Failed'  );
      }
   }
   
   return $post_id;
}

/* Prints the inner fields for the custom post/page section */
function cred_inner_custom_box( $post ) {
	
   // Use nonce for verification
	echo '<input type="hidden" name="cred_noncename" id="cred_noncename" value="' . 
	  wp_create_nonce( plugin_basename(__FILE__) ) . '" />';

	// rename old metadata fields
   cred_rename_post_metadata( $post );
   
	// The actual fields for data entry
	$is_paid = cred_is_paywall_flagged( $post ) ? "checked" : "";
    ?>
    
<table border="0">
	<tr>
		<td></td>
		<td><input type="checkbox" name="_cred_paywall" value="1"
		<?php echo($is_paid) ?>> Make this post paid-for content</td>
	</tr>
	<tr>
		<td><b>Price in cred:</b></td>
		<td><input type="text" name="_cred_price" size="3" maxlength="3" 
			value="<?php echo(get_post_meta($post->ID, '_cred_price', true)) ?>" />
		</td>
	</tr>
    <tr>
     <td valign="top"><b>Cred attachments</b></td>
     <td>
<?php
      if ($post->ID > 0) { 
?>	
         <a href="<?php echo WP_PLUGIN_URL ?>/cred/upload.php?post_id=<?php 
         echo $post->ID ?>&flash=0&TB_iframe=true"  id="add_cred_media" 
         class="thickbox" title='Manage Cred Attachments' onclick="return false;"
         >Manage Cred attachments</a>
         <br/><br/>
         Use Cred attachments to upload your multimedia files securely. Wordpress attachments are not secure and 
         may be viewed by anyone with the correct URL, while Cred attachments require the user to be
         logged in, and have purchased the content first, in order to be able to download the file(s). 		
<?php
      } else {
?>
         Save a draft or publish this post in order to manage Cred attachments.
<?php    	
      }
?>	
     </td>
   </tr>
</table>

  <?php  

}

/* Prints the edit form for pre-WordPress 2.5 post/page */
function cred_old_custom_box( $post ) {

	echo '<div class="dbx-b-ox-wrapper">' . "\n";
	echo '<fieldset id="cred_fieldsetid" class="dbx-box">' . "\n";
	echo '<div class="dbx-h-andle-wrapper"><h3 class="dbx-handle">' .
	__( 'Cred Options', 'cred_textdomain' ) . "</h3></div>";

	echo '<div class="dbx-c-ontent-wrapper"><div class="dbx-content">';

	// output editing form

	cred_inner_custom_box( $post );

	// end wrapper

	echo "</div></div></fieldset></div>\n";
}

add_action('delete_post', 'cred_delete_post');
/**
 * When a post is deleted, clean up all it's cred attachment files 
 */
function cred_delete_post($post_id) {
	$files = get_post_meta($post_id, '_cred_upload_name');
	if($files) {
		$upath = wp_upload_dir();
		$cnt1 = 0;
		foreach($files as $file) {			
         $file_path = cred_get_upload_path($post_id).'/'.$files[$cnt1];
         unlink($file_path); // delete the physical file
         $cnt1 = $cnt1 + 1;			
		}
		
		// remove the post directory
		rmdir(cred_get_upload_path($post_id));
	} 
}

//add_filter("wp_head", "cred_head");
/**
 * Add our stylesheets and javascript to the head. Some themes stop wp_head function from working,
 * so instead, we will load this as and when needed (e.g. cred_content_filter)
 */
function cred_head() {
   global $cred_loaded;

   if ($cred_loaded) return;
   $cred_loaded = true;
   
	$logged_in = false;
	$user = wp_get_current_user();
	if ($user->ID > 0) {
		$logged_in = true;
      $cred_user = get_usermeta($user->ID, 'cred_user');
	}
?>
	<link rel="stylesheet"
		href="<?php echo WP_PLUGIN_URL ?>/cred/cred.css" type="text/css"
		media="screen" />
	<script
		type="text/javascript"
		src="<?php echo WP_PLUGIN_URL ?>/cred/jsr_class.js"></script>
	<script
		type="text/javascript" src="<?php echo WP_PLUGIN_URL ?>/cred/center.js"></script>
	<script
		type="text/javascript" src="<?php echo WP_PLUGIN_URL ?>/cred/cred.js"></script>
	<script type="text/javascript">
	   	cred.init('<?php echo CRED_URL ?>', '<?php echo get_option("cred_api_key") ?>', <?php 
		       echo $logged_in ? 'true' : 'false' ?>, '<?php echo WP_PLUGIN_URL ?>', <?php 
		       echo $cred_user ? 'true' : 'false' ?>);
	</script>
<?php
}

//add_filter( "the_excerpt", "cred_filter_excerpt" );
/**
 * Filter the content from the user unless already purchased. Provide a link for users to login
 * or purchase the content otherwise.
 * @param $content
 * @return unknown_type
 */

/* Disabled for now

function cred_filter_excerpt( $excerpt ) {
   global $post;
   
   $is_paywalled = cred_is_paywalled( $post );
   if (is_wp_error($is_paywalled)) {
      return "<script>cred.error('" . $is_paywalled->get_error_message() . "');</script>";   	
   }   
   
   if ($is_paywalled) {
	  	return $excerpt . "<br clear='all'/><br clear='all'/>" .
	     "<div class='credLogoSmall' id='cred_".$post->ID."' onclick='cred.purchase(this, \"?p=" .
      $post->ID . "\", \"" . urlencode($post->post_title) . "\", " . get_post_meta($post->ID, '_cred_price', true) . 
      ")'> Click here to redeem for " .
      get_post_meta($post->ID, '_cred_price', true) . " cred</div>";
   }
   
   return $excerpt;
}	 */

add_filter( "the_content", "cred_filter_content" );
/**
 * Filter the content from the user unless already purchased. Provide a link for users to login
 * or purchase the content otherwise.
 * @param $content
 * @return unknown_type
 */

function cred_filter_content( $content ) {
   global $post;
   
   if (cred_is_paywall_flagged($post)) {
   	// this is paywall content, so load up our js and css files
   	cred_head();
   }
   
	$is_paywalled = cred_is_paywalled( $post );

   if (is_wp_error($is_paywalled)) {
      // paywalled content display
      $LSize = get_option('cred_logo_size') or $LSize = "credLogoSmall"; 
      $price = get_post_meta($post->ID, '_cred_price', true);
      if ($price == null) $price = "0";
      
      $errorMessage = $is_paywalled->get_error_message();
      if ($errorMessage == "User does not have enough cred") {
	      $content = $post->post_excerpt . "<br clear='all'/><br clear='all'/>" .
	        "<div class='".$LSize."' id='cred_" .
	           $post->ID . "' onclick='window.open(\"" . CRED_URL . "/user\")'> Click here to purchase more cred" .
	        "</div>" . "<br clear='all'/><br clear='all'/>" .
	        "<script>cred.error('You do not have enough cred in your Cred account');</script>";
	      return $content;	           
      } else {
      	$post->post_excerpt = $post->post_excerpt . "<script>cred.error('" . $errorMessage . "');</script>";
      	$is_paywalled = true;      	
      }   	
   }
    
   if ($is_paywalled) {
   	// paywalled content display
      $LSize = get_option('cred_logo_size') or $LSize = "credLogoSmall"; 
      $price = get_post_meta($post->ID, '_cred_price', true);
      if ($price == null) $price = "0";
      
	   $content = $post->post_excerpt . "<br clear='all'/><br clear='all'/>" .
	     "<div class='".$LSize."' id='cred_" .
		     $post->ID . "' onclick='cred.purchase(this, \"?p=" .
		     $post->ID . "\", \"" . urlencode($post->post_title) . "\", " . 
		     $price . 
	     ")'> Click here to redeem for " . $price . " cred" .
		  "</div>" . "<br clear='all'/><br clear='all'/>";
   } else {
      $content = cred_add_attachments( $post, $content);            	
   }
   
   return $content;
}


//MVW  -- Add title filter and its function
//add_filter( "the_title", "cred_filter_title" );
function cred_filter_title( $title ){
	global $post;
   global $cred_title_icon_small;
   global $cred_title_icon_medium;
   global $cred_title_icon_large;

   if (cred_is_paywall_flagged($post)) {
      $cred_title_icon_small =  "<img src='".WP_PLUGIN_URL."/cred/icon_cred_16x16.png' width='16' height='16' alt='Cr'/>";   	
      $cred_title_icon_medium =  "<img src='".WP_PLUGIN_URL."/cred/icon_cred_24x24.png' width='24' height='24' alt='Cr'/>";    
      $cred_title_icon_large =  "<img src='".WP_PLUGIN_URL."/cred/icon_cred_32x32.png' width='32' height='32' alt='Cr'/>";    
   } else {
      $cred_title_icon_small =  "";    
      $cred_title_icon_medium =  "";    
      $cred_title_icon_large =  "";       	
   }
   
	return $title;
}

add_filter("wp_footer", "cred_footer");
// Additional divs/javascript
function cred_footer() {
	$user = wp_get_current_user();
	?>
<!-- Redeem Cred: Start -->
<div class="credRedeem" id="redeemCred" style="display: none">
<form name="credRedeemForm">
<fieldset class="redeemCred"><legend>Redeem Cred</legend> You are about
to redeem an item for <strong><span id="redeemCredValue"></span></strong>
cred. Are you sure?<br />
	<?php
	$subs = get_usermeta($user->ID, 'cred_provider_subscriptions');
	if (is_array($subs) && $subs[0] != null) {
		echo "<br/>The following subscriptions are also available for this website, select one to ".
                     	  "subscribe rather than redeem the single item:<br/><br/>";

		foreach($subs as $s) {
			echo '<input type="radio" name="cred_subscription_uri" value="'.
			$s["subscription_uri"].'"/>'.$s["name"].', '.
			$s["duration"].' - '.$s["cred_cost"].' cred<br/>';
		}

		echo '<input type="radio" name="cred_subscription_uri" value="" checked="checked"/>Not right now, thanks<br/><br/>';
	}
	?> <input type="button" value="OK"
	onclick="cred._doPurchase();document.getElementById('redeemCred').style.display='none';">
<input type="button" value="Cancel"
	onClick="document.getElementById('redeemCred').style.display='none'"></fieldset>
</form>
</div>
<div class="credRedeem" id="credMessage" style="display: none">
<fieldset class="redeemCred"><legend>Cred</legend> <img
	src="<?php echo WP_PLUGIN_URL ?>/cred/loading.gif" alt="Loading" /> <span
	id="credMessageText"></span></fieldset>
</div>
<!-- Redeem Cred: End -->
	<?php
}

/**
 * Do not allow paid content into the RSS feed
 */
add_filter( "the_content_rss", "cred_filter_rss_content" );
function cred_filter_rss_content( $content ) {
	global $post;

	if ( cred_is_paywall_flagged( $post ) ) {
		return $post->post_excerpt . "\r\n<br/><br/>View the full content on the website.";
	} else {
		return $content;
	}
}
?>
