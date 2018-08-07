<?PHP

$ftp = array(
        'server'        =>      '91.98.98.41',
        'user'          =>      'mfa',
        'pass'          =>      '123654',
);
$dbPassword = "BX9gtAgXSpm2wza195B1";

#-------------------------------------------
function _log($txt){
        echo $txt . "\r\n";
        file_put_contents('/script/_backup.log', date('Y-m-d H:i:s ') . $txt . "\r\n", FILE_APPEND);
}
#-------------------------------------------
function ftp_mksubdirs($ftpcon, $ftpbasedir, $ftpath){
   @ftp_chdir($ftpcon, $ftpbasedir);
   $parts = explode('/', $ftpath);
   foreach($parts as $part){
      if(!@ftp_chdir($ftpcon, $part)){
         ftp_mkdir($ftpcon, $part);
         ftp_chdir($ftpcon, $part);
      }
   }
}
#-------------------------------------------
function backupFile($site, $copyToFtp=false){
    global $storagePath, $dbPassword, $ftp;
	if(empty($site['file']['path']))
		return;
	_log("Backup Files of " . $site['name']);
    $fname = $storagePath . date("Ymd") . '/files/';
    exec("mkdir -p $fname");
    $fname = $fname . $site['name'] . '_' . date("Hi") . ".tgz";
    $exclude = (is_array($site['file']['exclude'])) ? "--exclude='" . $site['file']['path'] . '/' . implode("' --exclude='" . $site['file']['path'] . '/', $site['file']['exclude']) . "' " : "";
    exec("tar $exclude -czf $fname " . $site['file']['path']);
		
    if($copyToFtp){ //make a copy ON FTP
            _log("Move Data to FTP Backup");
            $conn_id = ftp_connect($ftp['server']);
            $login_result = @ftp_login($conn_id, $ftp['user'], $ftp['pass']);
            if ((!$conn_id) || (!$login_result)) {
                _log("FTP connection has failed!");
                exit;
            } else {
                _log("Connected to ftp server");
                ftp_pasv($conn_id, true);
                    ftp_mksubdirs($conn_id, '/', date('Ymd') . '/files/');
                    _log("FTP: File upload in progress");
                    $upload = ftp_put($conn_id, $site['name'].date("_Hi").".tgz", $fname, FTP_BINARY);
                    if (!$upload) {
                            _log("FTP: upload has failed!");
                    } else {
                            _log("FTP: File Uploade complete successfully");
                    }
            }
            ftp_close($conn_id);
    }


}
#-------------------------------------------
function backupDB($site, $copyToFtp=false){
    global $storagePath, $dbPassword, $ftp;
    if(is_array($site['db'])){
        $DB = $site['db']['name'];
        $Table = @$site['db']['table'];
        $ignoredTable = empty($site['db']['ignore']) ? '' : '--ignore-table=' . $site['db']['ignore'];
        $addDrop = '--add-drop-table';
    } else {
        $DB = $site['db'];
        $Table = '';
        $ignoredTable = '';
        $addDrop = '--add-drop-database';
    }
    
    if(empty($DB))
    	return;
    _log("Backup " . $DB);
    $fname = $storagePath . date("Ymd") . '/db/';
    exec("mkdir -p $fname");
    $fname = $fname . $DB . '.' . date("Hi") . ".sql";
    exec("mysqldump -p$dbPassword --opt $addDrop --extended-insert  --force --hex-blob --routines --triggers --result-file=$fname $DB $Table $ignoredTable");
    exec("cd $storagePath && gzip $fname");
    exec("rm -f {$storagePath}$DB.last.sql.gz");
    exec("cp $fname.gz {$storagePath}$DB.last.sql.gz");

    //-------------------------------------------------

    if($copyToFtp){ //make a copy ON FTP
            _log("Move Data to FTP Backup");
            $conn_id = ftp_connect($ftp['server']);
            $login_result = @ftp_login($conn_id, $ftp['user'], $ftp['pass']);
            if ((!$conn_id) || (!$login_result)) {
                _log("FTP connection has failed!");
                exit;
            } else {
                _log("Connected to ftp server");
                ftp_pasv($conn_id, true);
                    ftp_mksubdirs($conn_id, '/', date('Ymd') . '/db/');
                    _log("FTP: File upload in progress");
                    $upload = ftp_put($conn_id, $DB.date("_Hi").".sql.gz", "{$storagePath}$DB.last.sql.gz", FTP_BINARY);
                    if (!$upload) {
                            _log("FTP: upload has failed!");
                    } else {
                            _log("FTP: File Uploade complete successfully");
                    }
            }
            ftp_close($conn_id);
    }
}

