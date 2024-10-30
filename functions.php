<?php
/**
 * Reusable functions
 */


/* Rename our old metadata keys to hide them from custom box */
function cred_rename_post_metadata( $post ) {
   $paywall = get_post_meta($post->ID, 'cred_paywall', true); 
   if ($paywall == "1") {
      add_post_meta($post->ID, '_cred_paywall', $paywall, true) or update_post_meta($post->ID, '_cred_paywall', $paywall);
      delete_post_meta($post->ID, 'cred_paywall');
   }
   
   $price = get_post_meta($post->ID, 'cred_price', true); 
   if (!empty($price)) 
   {
       add_post_meta($post->ID, '_cred_price', $price, true) or update_post_meta($post->ID, '_cred_price', $price);
        delete_post_meta($post->ID, 'cred_price');
   }  
            
   
}

// Our own version of file_get_contents without the security restrictions
function cred_get_contents($url,$include_headers=false,$post=false) {
   $url = parse_url(CRED_SERVER . $url);

   if (!isset($url['port'])) {
      if ($url['scheme'] == 'http') { $url['port']=80; }
      elseif ($url['scheme'] == 'https') { $url['port']=443; }
   }
   $url['query']=isset($url['query'])?$url['query']:'';

   $url['protocol']=$url['scheme'].'://';
   $eol="\r\n";

   $headers =  "POST ".$url['protocol'].$url['host'].$url['path']." HTTP/1.0".$eol.
                "Host: ".$url['host'].$eol.
                "Referer: ".$url['protocol'].$url['host'].$url['path'].$eol.
                "Content-Type: application/x-www-form-urlencoded".$eol.
                "Content-Length: ".strlen($url['query']).$eol.
   $eol.$url['query'];

   $fp = @fsockopen($url['host'], $url['port'], $errno, $errstr, 30);
   if($fp) {
      if ($post) fputs($fp, $headers);
      else fputs($fp, "GET " . $url['path'] . "?" . $url['query'] . " HTTP/1.1\r\n".
                     "Host: " . $url['host'] . "\r\nConnection: Close\r\n\r\n");
      $result = '';
      while($fp && !feof($fp)) { $result .= fgets($fp, 128); }
      fclose($fp);
      if (!$include_headers) {
         //removes headers
         $pattern="/^.*\r\n\r\n/s";
         $result=preg_replace($pattern,'',$result);
      }

      return $result;
   } else {
      return false;
   }
}

/**
 * Tests post to see if it's marked as paywalled
 */
function cred_is_paywall_flagged( $post ) {
   cred_rename_post_metadata( $post ); 
   
   // Cred paywalled content
   if ( get_post_meta($post->ID, '_cred_paywall', true) == "1") {
      return true;
   }
   
   return false;
}

/**
 * Function to test if the specified post is paywalled or not for the current user
 * @return unknown_type
 */
function cred_is_paywalled( $post ) {

   // Content authors can view their content without purchase
   if ( current_user_can( 'edit_post', $post->ID ) ) {
      return false;
   }
   
   // Non-paywalled content
   if ( !cred_is_paywall_flagged( $post ) ) {
      return false;
   }   

   // check if the logged in user is a cred user
   $user = wp_get_current_user();
   $cred_user = get_usermeta($user->ID, 'cred_user');
   if ($cred_user) {
      // check if user has already purchased this content or subscribed
      $purchased = false;

      // get the user subscriptions
      $subs = (array)get_usermeta($user->ID, 'cred_subscriptions');
      if (!$purchased && in_array("/full", $subs)) {
         $purchased = true;
      }

      // get the user purchases
      if (!$purchased) {
         $p = (array)get_usermeta($user->ID, 'cred_purchases');
         if (in_array("?p=".$post->ID, $p)) {
            $purchased = true;
         }
      }

      if ($purchased) return false;

      // check for a purchase request
      if ( ("?p=".$post->ID) == $_POST['cred_purchase_post_uri'] ) {
         $user = wp_get_current_user();
         $cred_user_id = get_usermeta($user->ID, 'cred_id');

         $api_call = "/api/user/purchase/".
         $cred_user_id.".json?usercode=".$_POST['token'].
                "&api_key=".get_option("cred_api_key").
                "&uri=".urlencode("?p=").$post->ID.
             "&title=".urlencode($post->post_title).
              "&creds=".get_post_meta($post->ID, '_cred_price', true);
         $output = cred_get_contents($api_call);
         if ($output == FALSE) return new WP_Error('cred_error', _('Unable to connect to Cred server. Please try again later.'));
         $obj = json_decode($output, true);
         if ($obj['error']) {
            /*if ($obj['exception'] == "User does not have enough cred") {
             $obj['exception'] .= ". <a href='". CRED_URL . "/user' target='_blank'>Click here to buy more cred</a>";
             }*/
            return new WP_Error("cred_error", _($obj['exception']));
         } else {
            // add purchase to user's purchased list
            $p = (array)get_usermeta($user->ID, 'cred_purchases');
            array_push($p, "?p=".$post->ID);
            update_usermeta($user->ID, 'cred_purchases', $p);
               
            return false;
         }
      }
   }
   
   return true;
}

/**
 * Adds attachment links to the specified content for the specified post. Does not
 * check for paywall permissions, so do those first!
 * @param $post
 * @param $content
 * @return unknown_type
 */
function cred_add_attachments( $post, $content ) {
   $ret_content = $content;
   
   // check for Cred attachments
   $attachments = get_post_meta($post->ID, '_cred_attachments'); 
   if($attachments) {
      $ret_content = $ret_content . 
        "<fieldset class='cred_attachment'><legend>Download media</legend><p>Click on the link(s) below:</p>";

      $cnt1 = 0;
      foreach ($attachments as $filedata) { 
         // One last sanity check
         if ($filedata['_cred_upload_size'] > 0) {            
            $file_url = WP_PLUGIN_URL."/cred/dl.php?post_id=".$post->ID."&file=".$cnt1;
            $ret_content = $ret_content . "<li><a href='".$file_url."'>". 
               $filedata['_cred_upload_original']."</a> (".HumanReadableFilesize($filedata['_cred_upload_size']).")"; 
         } 

         $cnt1 = $cnt1 + 1;
      }
      
      $ret_content = $ret_content . "</fieldset>";
   }
	
   return $ret_content;
}

/* set up callback for authentication */
function cred_auth_start() {
   $loc = $_GET['loc'];

   $uri = explode('#',$loc);
   $url = $uri[0];

   $_SESSION['cred_auth_callback'] = $url;
}

/* Log the user into their YourCred.com account */
function cred_user_login( $username, $password ) {
   try {
      $output = cred_get_contents("/api/user/login/".
      $username.".json?usercode=".$password."&api_key=".get_option("cred_api_key"));
      if ($output == FALSE) throw new Exception("Unable to connect to authentication server for login. Please try again later.");
      $obj = (array)json_decode($output);
      $udata = (array)$obj['user'];
      if ($obj['error']) {
         return new WP_Error('authentication_failed', __('Cred authentication failed.'));
      }
   } catch (Exception $e) {
      return new WP_Error('error', __($e->getMessage()));
      //return new WP_Error('authentication_failed', __('Cred authentication failed.'.$e->getMessage()));
   }

   $wpusername = cred_username_to_wpusername($username);
   // update user data
   $userdata = array(
          'user_pass' => wp_generate_password(),
          'user_login' => $wpusername,
          'first_name' => $udata["firstname"],
          'last_name' => $udata["surname"],
          'display_name' => $udata["firstname"]." ".$udata["surname"],
          'user_url' => CRED_URL . "/users/".$username,
          'user_email' =>  ""
   );

   define( 'WP_IMPORTING', 'true' ); // For wordpress 3.0, skip dup email check
   $user = get_userdatabylogin($wpusername);
   if ( $user == null || is_wp_error($user) ) {
      // first time logging in from this site
      $user = new WP_User($wpusername);
      if(!function_exists('wp_insert_user'))
      {
         include_once( ABSPATH . WPINC . '/registration.php' );
      }

      $wpuid = wp_insert_user($userdata);
      if ( is_wp_error($wpuid) ) {
         wp_die($wpuid->get_error_message());
      }
   } else {
      if(!function_exists('wp_update_user'))
      {
         include_once( ABSPATH . WPINC . '/registration.php' );
      }
      $wpuid = $user->ID;
      $userdata["ID"] = $wpuid;
      wp_update_user($userdata);
   }
   
   if($wpuid) {
      delete_usermeta($wpuid, 'cred_user');
      delete_usermeta($wpuid, 'cred_id');
      delete_usermeta($wpuid, 'cred_balance');
      delete_usermeta($wpuid, 'cred_provider_subscriptions');
      
      cred_get_user_purchases($username, $wpuid);
      cred_get_user_subscriptions($username, $wpuid);
      
      $cred_subs = cred_get_contents("/api/provider/subscriptions.json?api_key=".
      get_option("cred_api_key"));
      if ($cred_subs == FALSE) {
         return new WP_Error('error', __("Unable to connect to Cred server for subscriptions. Please try again later."));
      }
      $s = (array)json_decode($cred_subs, true);
      if (is_array($s["subscriptions"])) {
         $cred_subs = $s["subscriptions"];
      } else {
         $cred_subs = array();
      }

      update_usermeta($wpuid, 'cred_user', true);
      update_usermeta($wpuid, 'cred_id', $udata['username']);
      update_usermeta($wpuid, 'cred_balance', "".$udata['cred_balance']);
      update_usermeta($wpuid, 'cred_provider_subscriptions', $cred_subs);

      wp_set_auth_cookie($wpuid, false, true);
      wp_set_current_user($wpuid);
   }

   return $user;
}

/*add_action( "authenticate", "cred_authenticate", 10, 3 );*/
/* change the authenticate mechanism to use YourCred.com authentication */
function cred_authenticate( $user, $username, $password ) {

   if ( $user == null && $username != null ) {
      $username = sanitize_user($username);
      $password = trim($password);

      // check if user is a WP user
      $user = get_userdatabylogin($username);
      if ( $user != null ) return null;

      // User is not a wordpress user, so log them into Cred
      cred_user_login($username, $password);
   }

   return $user;
}


/* Load user content redemptions from Cred server */
function cred_get_user_purchases($username, $wpuid) {
   delete_usermeta($wpuid, 'cred_purchases');
   
   // get the user purchases
   $purchases = cred_get_contents("/api/user/purchases/".
   $username.".json?&api_key=".get_option("cred_api_key"));
   if ($purchases == FALSE) {
      return new WP_Error('error', __("Unable to connect to authentication server for purchases. Please try again later."));
   }
   $p = (array)json_decode($purchases);
   if (is_array($p['purchases'])) {
      $p2 = array_map("cred_purchases_array_map", $p['purchases']);
   } else {
      $p2 = array();
   }

   update_usermeta($wpuid, 'cred_purchases', $p2);
}

/* Load user subscriptions from Cred server */
function cred_get_user_subscriptions($username, $wpuid) {
   delete_usermeta($wpuid, 'cred_subscriptions');
   delete_usermeta($wpuid, 'cred_next_expiry');
   
   // get user subscriptions
   $expires_in_secs = 60*60*24; // set to 1 day by default
   $subs = cred_get_contents("/api/user/subscriptions/".
   $username.".json?&api_key=".get_option("cred_api_key"));
   if ($subs == FALSE)  {
      return new WP_Error('error', __("Unable to connect to authentication server for subscriptions. Please try again later."));
   }
   $s = (array)json_decode($subs);
   if (is_array($s['subscriptions'])) {
      $s2 = array_map("cred_subscriptions_array_map", $s['subscriptions']);
      foreach($s['subscriptions'] as $k=>$v) {
         $av = (array) $v;
         $e = (integer)$av["expires_in_secs"];
         if ($e < $expires_in_secs) {
            $expires_in_secs = $e;
         }
      }
   } else {
      $s2 = array();
   }
   
   // manage expiry time
   $expiry = cred_add_date("now", $expires_in_secs);
   
   update_usermeta($wpuid, 'cred_subscriptions', $s2);
   update_usermeta($wpuid, 'cred_next_expiry', $expiry);
}

/* change the data from the server to a straight array with just the purchased content uri */
function cred_purchases_array_map($purchase) {
   $p = (array) $purchase;
   return (string)$p['uri'];
}

/* change the data from the server to a straight array with just the subscription type uri */
function cred_subscriptions_array_map($sub) {
   $s = (array) $sub;
   return (string)$s['type_uri'];
}

/* change wordpress username to reflect that they are from Cred */
function cred_username_to_wpusername($username) {
   return "cred_" . $username;
}

/**
 * Returns a human readable filesize
 *
 * @author      wesman20 (php.net)
 * @author      Jonas John
 * @version     0.3
 * @link        http://www.jonasjohn.de/snippets/php/readable-filesize.htm
 */
function HumanReadableFilesize($size) {
 
    // Adapted from: http://www.php.net/manual/en/function.filesize.php
 
    $mod = 1024;
 
    $units = explode(' ','B KB MB GB TB PB');
    for ($i = 0; $size > $mod; $i++) {
        $size /= $mod;
    }
 
    return round($size, 2) . ' ' . $units[$i];
}
?>