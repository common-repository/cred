<?php
session_start();
if (!isset($_SESSION['ABSPATH'])) {
	echo "Invalid request";
	return;
}

require_once($_SESSION['ABSPATH'].'wp-load.php');
require_once(ABSPATH.'/wp-admin/admin.php');
require_once('compat.php');

/* Delete a file and meta data by index */
function delete_file_by_index($post_id, $index) {
   $arr = get_post_meta($post_id, '_cred_attachments', false);
   $file_path = cred_get_upload_path($post_id).'/'.$arr[$index]['_cred_upload_name'];
   unlink($file_path); // delete the physical file

   del_post_meta($post_id, '_cred_attachments', $index);
}

/* Delete post meta by index */
function del_post_meta($post_id, $key, $index) {
   $arr = get_post_meta($post_id, $key);
   $idx = 0;
   foreach ($arr as $val) {
      if ($idx == $index) {
         delete_post_meta($post_id, $key, $val);
      }
      $idx = $idx + 1;
   }
}

/* Delete an upload by name */
function delete_by_name($post_id, $filename) {
   $arr = get_post_meta($post_id, '_cred_attachments');
   $idx = 0;
   foreach ($arr as $val) {
      if (strcasecmp($val['_cred_upload_original'], $filename) == 0) {
         delete_file_by_index($post_id, $idx);
      }
      $idx = $idx + 1;
   }  
}

function upload() {
	$post_id = $_GET['post_id'];
	if (!current_user_can('upload_files') || !current_user_can( 'edit_post', $post_id ))
	wp_die(__('You do not have permission to upload files to this post.'));
	
	// check if user is uploading a file
	$upload = null;
	if ($_FILES != null) {
		$cred_upload_name = basename($_FILES['credupload']['name']);
		$cred_upload_temp = $_FILES['credupload']['tmp_name'];
		$cred_upload_size = $_FILES['credupload']['size'];
	
		// check if upload name already exists, if so delete it
		delete_by_name($post_id, $cred_upload_name);
		
		//$cred_upload_name = strtolower($cred_upload_name) ;
		list($p1, $ext) = split('[.]', $cred_upload_name);
		$ran = rand();
		$ran2 = $ran.".";
		$new_name = $ran2.$ext;
	
		if (!file_exists($cred_upload_path)) {
			$cred_upload_path_base = cred_get_upload_path('');
			if (!file_exists($cred_upload_path_base))	{
				mkdir("$cred_upload_path_base", 0777);
			}
	
	      $cred_upload_path = cred_get_upload_path($post_id);
			if (!file_exists($cred_upload_path)) {
			 mkdir("$cred_upload_path", 0777);
	      }
		}
	
		$new_path = cred_get_upload_path($post_id).'/'.$new_name;
		$new_uri = cred_get_upload_url($post_id).'/'.$new_name;
		$cnt = 0;
		if(move_uploaded_file($cred_upload_temp, $new_path)) {
			$filedata = array(
			 "_cred_upload_path" => $new_uri,
			 "_cred_upload_name" => $new_name,
			 "_cred_upload_original" => $cred_upload_name,
			 "_cred_upload_size" => $cred_upload_size
			);
			
			add_post_meta($post_id, '_cred_attachments', $filedata) or
			 update_post_meta($post_id, '_cred_attachments', $filedata);
			$upload = true;
		} else {
			$upload = false;
		}
	}
	
	// check if user is deleting a file
	if (isset($_POST['del_index'])) {
	   $cnt1 = $_POST['del_index'];
		//echo "Delete file ".$_POST['del_index'];
	   delete_file_by_index($post_id, $cnt1);
	}
?>
<div class="wrap">
	Please note that changes here go live immediately (unless this post is not published).
	Uploading a file with a duplicate filename will overwrite the previously uploaded file.
	<br/><br/>
	<b>Upload new attachment</b>
	<form method="post" action="" name="submit"
		enctype="multipart/form-data"><input type="file" name="credupload"> <input
		type="submit" name="submit" value="Upload"><br>
<?php
	if ($upload != null) {
		if ($upload) {
			echo "<b>Upload successful</b>";
		} else {
			echo "<b>Upload failed</b>";
		}
	}
?></form>
	<br />
	<b>Current file attachments</b>
	<table class="widefat" cellspacing="0" id="all-plugins-table">
		<thead>
			<th>File #</th>
			<th>File name</th>
			<th>File Size</th>
			<th>&nbsp;</th>
		</thead>
<?php
	   $attachments = get_post_meta($post_id, '_cred_attachments');
		if($attachments) {
			$cnt1 = 0;
			foreach ($attachments as $filedata) {
				$cnt2 = $cnt1 + 1;
	
				echo "<tr>";
				echo "<td><a href='".$filedata['_cred_upload_path']."' target='_blank'>File ".$cnt2."</a></td>";
				echo "<td><a href=".WP_PLUGIN_URL."/cred/dl.php?post_id=".$post_id."&file=".$cnt1." target="."_blank".">".$filedata['_cred_upload_original']."</a></td>";
				echo "<td>".HumanReadableFilesize($filedata['_cred_upload_size']) . "</td>";
				echo "<td><input type='button' name='del' value='Delete' onclick='delFile(".$cnt1.")'></td>";
				echo "</tr>";
				
				$cnt1 = $cnt1 + 1;
			}
		} else {
			echo "<tr><td colspan='10'>No file attachments</td></tr>";
		}
?>
	</table>
	
	<form method="post" action="" name="delForm"><input type="hidden"
		name="del_index" value="" /></form>
	
	<script>
	   function delFile(fileIndex) {
		   if (!confirm("Are you sure?")) return;
		   
		   var theForm = document.forms['delForm'];
		   theForm['del_index'].value = fileIndex;
		   theForm.submit();
	   }
	</script>
</div>   	
<?php 
}

wp_iframe(upload);
?>