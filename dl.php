<?php
function auth_redirect() {}; // stop the login prompt
session_start();
if (!isset($_SESSION['ABSPATH'])) {
	// we need the ABSPATH to continue
	echo "Invalid request";
	return;
}
session_write_close(); // may be needed for performance reasons

// Now we can load the required code
require_once($_SESSION['ABSPATH'].'wp-load.php');
require_once(ABSPATH.'/wp-admin/admin.php');
require_once("functions.php");
require_once("compat.php");

// Check that user has purchased this content 
$post_id = $_GET['post_id'];
$post->ID = $post_id;
if (cred_is_paywalled($post)) {
	echo "Please log in using your Cred account to view this content. Visit <a href='".CRED_URL.
         "'>" . CRED_URL . "</a> for more information.";
	return;
}

// Get the file details
$count1  = $_GET['file'];
$dir = cred_get_upload_path($post_id)."/";
$attachments = get_post_meta($post_id, '_cred_attachments');
$file0 = $attachments[$count1]['_cred_upload_original'];
$file = $attachments[$count1]['_cred_upload_name'];

if ((isset($file))&&(file_exists($dir.$file))) {
   // Stream the file back to the browser
	header("Content-Transfer-Encoding: Binary");
	header("Content-Length: ".filesize($dir.$file));
	//header('Content-Type: ' . cred_mime_type($dir.$file));
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename="' . $file0 . '"');
	readfile("$dir$file");
	return;
} else {
	// Cannot find this file
	echo "No file selected";
}


/* Return file mime type based on extension. */
function cred_mime_type( $file ) {
	$ftype = 'application/octet-stream';
	$finfo = @finfo_open(FILEINFO_MIME);
	if ($finfo !== FALSE) {
		$fres = @finfo_file($finfo, $file);
		if ( ($fres !== FALSE)
		&& is_string($fres)
		&& (strlen($fres)>0)) {
			$ftype = $fres;
		}
		@finfo_close($finfo);
	}
	 
	return $ftype;
}
?>