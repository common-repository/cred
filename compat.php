<?php
define('CRED_URL', 'https://yourcred.com');
define('CRED_SERVER', 'http://api.yourcred.com');
define('CRED_UPLOAD_DIR', 'cred');

/* ========= PHP Version compatability stuff ========== */

/* DateTime.add() only added in PHP 5.3 */
function cred_add_date($givendate,$sec=0,$hour=0,$day=0,$mth=0,$yr=0) {
	$cd = strtotime($givendate);
	$newdate = date('Y-m-d h:i:s', mktime(date('h',$cd)+$hour,
	date('i',$cd), date('s',$cd)+$sec, date('m',$cd)+$mth,
	date('d',$cd)+$day, date('Y',$cd)+$yr));
	return $newdate;
}

/* Get the url to the cred upload directory for the post */
function cred_get_upload_url($post_id) {
	$upath = wp_upload_dir();
	$cred_dir = CRED_UPLOAD_DIR.'/'.$post_id;
	$cred_upload_url = $upath['baseurl'].'/'.$cred_dir;
	return $cred_upload_url;
}

/* Get the path to the cred upload directory for the post */
function cred_get_upload_path($post_id) {
	//Declaring cred upload variables
	$upath = wp_upload_dir();
	$cred_upload_base = $upath['basedir'];
	$cred_dir = CRED_UPLOAD_DIR.'/'.$post_id;

	return $cred_upload_base.'/'.$cred_dir;
}
?>
