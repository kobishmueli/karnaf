<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require_once("../ktools.php");
check_auth();
$id = $_GET['id'];
if(empty($id) || !is_numeric($id)) safe_die("Invalid Ticket ID!");
if(isset($_GET['code']) && !empty($_GET['code'])) $randcode = $_GET['code'];
else $randcode = 0;
$query = squery("SELECT unick,randcode,open_time FROM karnaf_tickets WHERE id=%d", $id);
if($result = sql_fetch_array($query)) {
  if(!IsKarnafOperSession() && ($randcode != $result['randcode']) && (($nick != $result['unick']) || $nick=="Guest" || $a_regtime>(int)$result['open_time'])) AccessDenied("You must provide the ticket verification code to view this page.");
  if(isset($_GET['download'])) $download = $_GET['download'];
  else $download = 0;
  $query2 = squery("SELECT file_name,file_type,file_size FROM karnaf_files WHERE id=%d AND tid=%d", $download, $id);
  if(!$query2) safe_die("Error: can't find file!");
  $result2 = sql_fetch_array($query2);
  if(!$result2) safe_die("Error: can't find file!");
  if((int)$result2['file_size'] != 0) header("Content-length: ".$result2['file_size']);
  header("Content-type: ".$result2['file_type']);
  $file_ext = strtolower(substr($result2['file_name'],-4));
  if($file_ext[0] != ".") $file_ext = strtolower(substr($result2['file_name'],-5));
  if($file_ext!=".jpg" && $file_ext!=".png") header("Content-Disposition: attachment; filename=".$result2['file_name']);
  $fn = KARNAF_UPLOAD_PATH."/".$id."/".$download.$file_ext;
  readfile($fn);
  sql_free_result($query2);
}
else safe_die("Invalid Ticket ID!");
sql_free_result($query);
require_once("karnaf_footer.php");
?>
