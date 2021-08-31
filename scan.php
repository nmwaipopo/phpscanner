<?php
/**************************************************************/
/* Use variable $SCAN_DIRECTORY to set the path of the folder */
/* to scan	 												  */
/**************************************************************/
$THIS_DIRECTORY = __DIR__;
$SCAN_DIRECTORY = '../cforum/';
$OUTPUT_FOLDER = $THIS_DIRECTORY. DIRECTORY_SEPARATOR .'output';
define('OUTPUT_FOLDER',$OUTPUT_FOLDER);

//Maximum time files can stay in the output folder
$lifetime = 60 * 60 * 24 * 7; //set to 24 hours
define('FILE_LIFETIME',$lifetime);

/************************************************************/
/* Output is the directory where JSON files with data will  */
/* be stored. You can create this folder and make sure it's */
/* writable (permssion 0777)								*/
/************************************************************/
if(!is_dir(OUTPUT_FOLDER)){
	@mkdir(OUTPUT_FOLDER,0777);
	@chmod(OUTPUT_FOLDER,0777);
}

/*************************************************************/
/* $exempt_files contains the list of file extensions to be  */
/* exempted from scanning.									 */
/*************************************************************/
$exempt_files = array('jpg','png','jpeg','gif','js','css','scss','svg','wtf','ttf','otf','eot','woff','swp','sample','mp4','avi','mkv','pdf','doc','docx','xls','xlsx','odf','csv');

//Directories not to be scanned
$exempt_directory = array('phpscanner');


$prev_files = $files = array();

		//Get the list of files stored in JSON from previous scan
		$prev_files = prev_files();
		//Open folder and do scan for all allowed files
		$scan_results = open_and_scan($SCAN_DIRECTORY,$prev_files);
		
		//Remove JSON files if total files exceeds MAX_FILES_ALLOWED
		clean_old_files();
		
		//Get the list of files scanned and store them as a JSON file
		$files_array = $scan_results['files'];
		$json = json_encode($files_array,JSON_PRETTY_PRINT);
		$json_file = fopen(OUTPUT_FOLDER.'/json_output_'.date('YmdHis').'.json','a');
		fwrite($json_file,$json);
		fclose($json_file);
		
		//Get list of files modified
		$modified_files = $scan_results['changed'];
		
		//Process scanned files and put them in an array
		$files_array_list = array();
		$files_array_info = array();
		foreach($files_array as $files_info){
			array_push($files_array_list,$files_info['file']);
			$files_array_info[$files_info['file']] = $files_info['info'];
		}
		
		//Check if there are files from previous scan
		if(count($prev_files) > 0){
			$prev_files_list = array();
			$prev_files_info = array();
			
			foreach($prev_files as $file_info){
				array_push($prev_files_list,$file_info['file']);
				$prev_files_info[$file_info['file']] = $file_info['info'];
			}
			//New added files
			//New files are files that are not in the previous scan
			$new_files = '';
			$added_files = array_diff($files_array_list,$prev_files_list);
			foreach($added_files as $file){
				$added_file_info = $files_array_info[$file];
				$info2 = json_encode($added_file_info);
				$info2 = json_decode($info2);
				
				//New files information
				$atime2 = $info2->atime; //Last Access timestamp
				$ctime2 = $info2->ctime; //last inode change timestamp
				$mtime2 = $info2->mtime; //Last modification timestamp
				$uid2 = $info2->uid; //userid of owner
				$guid2 = $info2->gid; //groupid of owner
				$dev2 = $info2->dev; //Device ID
				$ip = access_log_ip($ctime2);
					$new_files .= $file.' was added'."\n";
					$new_files .= 'Possible IP Address(es): '.$ip."\n";
					$new_files .= 'File details:'."\n";
					$new_files .= 'Modified Date : '.date('Y-m-d H:i:s',$mtime2)."\n";
					$new_files .= 'Last Access Date : '.date('Y-m-d H:i:s',$atime2)."\n";
					$new_files .= 'iNode Change Date : '.date('Y-m-d H:i:s',$ctime2)."\n";
					$new_files .= 'User ID : '.$uid2."\n";
					$new_files .= 'Group ID : '.$guid2."\n";
					$new_files .= "=================================================================\n";
			}
			//Deleted Files
			//Deleted files are the files that are not in the new scan
			$removed_files = '';
			$deleted_files = array_diff($prev_files_list,$files_array_list);
			foreach($deleted_files as $file){
				$deleted_file_info = $prev_files_info[$file];
				
				$info2 = json_encode($deleted_file_info);
				$info2 = json_decode($info2);
				$atime2 = $info2->atime; //Last Access timestamp
				$ctime2 = $info2->ctime; //last inode change timestamp
				$mtime2 = $info2->mtime; //Last modification timestamp
				$uid2 = $info2->uid; //userid of owner
				$guid2 = $info2->gid; //groupid of owner
				$dev2 = $info2->dev; //Device ID
					$removed_files .= $file.' has been deleted'."\n";
					$ip = access_log_ip($ctime2);
					$removed_files .= 'Possible IP Address(es) : '.$ip."\n";
					$removed_files .= 'File details:'."\n";
					$removed_files .= 'Modified Date : '.date('Y-m-d H:i:s',$mtime2)."\n";
					$removed_files .= 'Last Access Date : '.date('Y-m-d H:i:s',$atime2)."\n";
					$removed_files .= 'iNode Change Date : '.date('Y-m-d H:i:s',$ctime2)."\n";
					$removed_files .= 'User ID : '.$uid2."\n";
					$removed_files .= 'Group ID : '.$guid2."\n";
					$removed_files .= "=================================================================\n";
			}
		}
		
		/***************************************************************/
		/* Prepare the email message with the list of modified, added  */
		/* and deleted files 										   */
		/***************************************************************/
		$output = '';
		if(!empty($modified_files)){
			$output .= '<h4>Modified Files/Folders</h4>';
			$output .= $modified_files;
			$output .= '<br />';
		}
		if(!empty($new_files)){
			$output .= '<h4>New Files/Folders</h4>';
			$output .= $new_files;
			$output .= '<br />';
		}
		if(!empty($removed_files)){
			$output .= '<h4>Deleted Files/Folders</h4>';
			$output .= $removed_files;
			$output .= '<br />';
		}
		/*
		/* If we have any errors, then send the email;
		*/
		if(!empty($output)){
			$results = str_ireplace('<br />',"\n",$output);
			$output = str_ireplace("\n","<br />",$output);
			$email_body = '<p>Hello,</p>';
			$email_body .= '<p>Site scanning code has found the following results on the last scan: '.date('d M, Y H:i').'</p>';
			$email_body .= $output;
			$email_body .= '<p>&nbsp;</p>';
			$email_body .= '<p>Regards,</p>';
			$email_body .= '<p>Site Scanning Code</p>';
			
			 //Put your domain here, without http,https or www
			$domain = 'pomoconline.com';
			$headers = "MIME-Version: 1.0" . "\r\n";
			$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
			
			//From Email
			$from = "noreply@".$domain;
			$headers .= 'From: Site Scanner <'.$from.'>' . "\r\n";
			
			//Email where the scan results will be sent
			$to = 'spawn256@gmail.com';
			
			//Subject of the email
			$subject='[Important]Site Scanner Results';
			
			$ok = @mail($to, $subject, $email_body, $headers);
			if($ok){
				echo 'Email with output results has been sent';
			}
			echo $results;
		}

/*********************************************************/
/* prev_files() function								 */
/* This is used to check the last of files scanned       */
/* Return: Array of all files from previous scan         */
/*********************************************************/
function prev_files(){
	global $sleep;
	$files_list = array();
	$scan_dir = scandir(OUTPUT_FOLDER, 1);
	$prev_file = isset($scan_dir[0]) ? $scan_dir[0] : '';
	if(empty($prev_file) || $prev_file == '.' || $prev_file == '..'){
		return $files_list;
	}
	$prev_file = OUTPUT_FOLDER.'/'.$prev_file;
	if(file_exists($prev_file)){
		$available_list = $dir_lists = array();
		$file_content = file_get_contents($prev_file);
		$json = json_decode($file_content);
		foreach($json as $file_info){
			$file = $file_info->file;
			if(!is_dir($file)){
				$dir_array = explode('/',$file);
				$dir_count = count($dir_array);
				$dir_list = '';
				for($x = 0; $x < $dir_count - 1; $x++){
					$dir_list .= $dir_array[$x].'/';
				}
				if(!in_array($dir_list,$dir_lists)){
					array_push($dir_lists,$dir_list);
				}
			}
			$info = $file_info->info;
			$dt = array();
			foreach($info as $key=>$val){
				$dt[$key] = $val;
			}
			$data = array(
				'file'=>$file,
				'info'=>$dt
			);
			array_push($files_list,$data);
		}
		if(count($dir_lists) > 0){
			//See if we have directories that are not listed as scanned from previous scan
			foreach($dir_lists as $dir){
				if(array_multi_search($files_list,'file',$dir)){
					continue;
				}
				$dir_stat = stat($dir);
				$dt = array();
				foreach($dir_stat as $key=>$val){
					$dt[$key] = $val;
				}
				$data = array(
					'file'=>$dir,
					'info'=>$dt
				);
				array_push($files_list,$data);
			}
		}
	}
	return $files_list;
}
/*********************************************************/
/* open_and_scan() function								 */
/* Open the directory to be scanned and start scan       */
/* Returns: array with list of changed and scanned files */
/*********************************************************/
function open_and_scan($SCAN_DIRECTORY,$prev_files){
	global $files,$exempt_files,$prev_files,$exempt_directory;
	$changed_files = '';
	$file_details = stat($SCAN_DIRECTORY);
	$dt = array();
	foreach($file_details as $key=>$info){
		$dt[$key] = $info;
	}
	$data = array(
		'file'=>$SCAN_DIRECTORY,
		'info'=>$dt
	);
	array_push($files,$data);
	$changed_files .= get_prev_stats($SCAN_DIRECTORY,$prev_files);
					
	$handle = opendir($SCAN_DIRECTORY);
	while($f = readdir($handle)){
		if($f != '.' && $f != '..'){
			if(!is_dir($SCAN_DIRECTORY.$f)){
				$file_array = explode('.',$f);
				$count = count($file_array);
				$extension = $file_array[$count-1];
				if($count > 1 && !in_array($extension,$exempt_files)){
					$file_details = stat($SCAN_DIRECTORY.$f);
					$dt = array();
					foreach($file_details as $key=>$info){
						$dt[$key] = $info;
					}
					$data = array(
						'file'=>$SCAN_DIRECTORY.$f,
						'info'=>$dt
					);
					array_push($files,$data);
					$changed_files .= get_prev_stats($SCAN_DIRECTORY.$f,$prev_files);
				}
			} else {
				if(!in_array($f,$exempt_directory)){
					open_and_scan($SCAN_DIRECTORY.$f.'/',$prev_files);
				}
			}
		}
	}
	return array(
		'changed'=>$changed_files,
		'files'=>$files
	);
}
/*********************************************************/
/* Get stats data of a file from previous scan           */
/* Returns information about the changes made on the file*/
/*********************************************************/
function get_prev_stats($file,$prev_files){
	global $sleep;
	$changed_file = '';
	$file_stats = stat($file);
	$file_stats = json_encode($file_stats);
	$file_stats = json_decode($file_stats);
	if(count($prev_files) > 0){
		foreach($prev_files as $file_info){
			$prev_file = $file_info['file'];
			$prev_info = $file_info['info'];
			if($prev_file === $file){
				$inf = json_encode($prev_info);
				$inf = json_decode($inf);
				$changed_file .= compare_stat($file_stats,$inf,$file);
				break;
			}
		}
	}
	return $changed_file;
}
/***********************************************************/
/* Compares stats of a file from previous scan and current */
/* scan. if it's different, it'll return the information   */
/***********************************************************/
function compare_stat($info1,$info2,$file){
	//Previous file details
	$atime1 = $info1->atime; //Last Access timestamp
	$ctime1 = $info1->ctime; //last inode change timestamp
	$mtime1 = $info1->mtime; //Last modification timestamp
	$uid1 = $info1->uid; //userid of owner
	$guid1 = $info1->gid; //groupid of owner
	$dev1 = $info1->dev; //Device ID
	
	//New file details
	$atime2 = $info2->atime; //Last Access timestamp
	$ctime2 = $info2->ctime; //last inode change timestamp
	$mtime2 = $info2->mtime; //Last modification timestamp
	$uid2 = $info2->uid; //userid of owner
	$guid2 = $info2->gid; //groupid of owner
	$dev2 = $info2->dev; //Device ID
	
	$file_details = '';
	if($mtime1 !== $mtime2){
		$file_details .= $file.' has been modified'."\n";
		$ip = access_log_ip($ctime2);
		$file_details .= 'Possible IP Address(es) : '.$ip."\n";
		$file_details .= 'Modification details:'."\n";
		$file_details .= 'Modified Date : '.date('Y-m-d H:i:s',$mtime2)."\n";
		$file_details .= 'Last Access Date : '.date('Y-m-d H:i:s',$atime2)."\n";
		$file_details .= 'iNode Change Date : '.date('Y-m-d H:i:s',$ctime2)."\n";
		$file_details .= 'User ID : '.$uid2."\n";
		$file_details .= 'Group ID : '.$guid2."\n";
		$file_details .= "=================================================================\n";
	}
	return $file_details;
}
/*********************************************************************/
/* Clean old JSON Files												 */
/*********************************************************************/
function clean_old_files(){
	$files = scandir(OUTPUT_FOLDER);
	foreach($files as $file){
		if($file != '.' && $file != '..'){
			$ext = substr($file,-4,4);
			if($ext == 'json'){
				$filemtime = filemtime(OUTPUT_FOLDER.'/'.$file);
				$diff = time() - $filemtime;
				if($diff >= FILE_LIFETIME){
					@unlink(OUTPUT_FOLDER.'/'.$file);
				}
			}
		}
	}
}

/**********************************************************************/
/* Get Possible IP Address from the access log by matching timestamp  */
/**********************************************************************/
function access_log_ip($timestamp){
	global $ACCESS_LOG;
	if(!isset($ACCESS_LOG) || empty($ACCESS_LOG)){
		return '';
	}
	$file_handle = file($ACCESS_LOG);
	$date = date('d/M/Y:H:i:s',$timestamp);
	$ip = 'Unknown';
	$ip_list = array();
	foreach($file_handle  as $line){
		if(strstr($line,$date)){
			$array = explode('-',$line);
			$ip = $array[0];
			if(!in_array($ip,$ip_list)){
				array_push($ip_list,$ip);
			}
		}
	}
	return count($ip_list) > 0 ? implode(',',$ip_list) : $ip;
}

//Custom array search to allow to search on multi-dimensional array
function array_multi_search($array,$key,$value=''){
	$key = strtolower($key);
	$value = strtolower($value);
	if(!is_array($array)){
		return false;
	}
	foreach($array as $sub_array){
		if(!is_array($sub_array)){
			if(strtolower($sub_array) == $key){
				return true;
			}
			return false;
		}
		foreach($sub_array as $k=>$v){
			if(strtolower($k) == $key){
				if(strtolower($v) == $value){
					return true;
				}
			}
		}
	}
	return false;
}