<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

$title = "New Ticket";
require_once("karnaf_header.php");
show_title("New Ticket");
if(IsKarnafOperSession()) $isoper = 1;
else $isoper = 0;
if(isset($_POST['cat3'])) {
  if($isoper) $uip = $_POST['uip'];
  else $uip = get_session_ip();
  $randstr = RandomNumber(10);
  $priority = 0;
  $rep_u = "";
  /* It would make sense to have the helpdesk team get tickets by default (unless the category assigns them to another team) */
  $rep_g = KARNAF_DEFAULT_GROUP;
  $query = squery("SELECT id,name,default_group,default_priority FROM karnaf_cat3 WHERE id=%d", $_POST['cat3']);
  if($result = sql_fetch_array($query)) {
    if(!empty($result['default_group'])) $rep_g = $result['default_group'];
    $priority = (int)$result['default_priority'];
    $cat3_id = $result['id'];
  }
  sql_free_result($query);
  if(!isset($cat3_id)) $error = "Invalid category provided, please try again!";
  if(isset($_POST['uphone'])) $uphone = fix_html($_POST['uphone']);
  else $uphone = "";
  $upriority = (int)$_POST['upriority'];
  if($upriority < $priority) $priority = $upriority;
  if(isset($_POST['unick']) && !empty($_POST['unick'])) $unick = fix_html($_POST['unick']);
  else if(isset($_POST['nick']) && !empty($_POST['nick'])) $unick = fix_html($_POST['nick']);
  else $unick = $nick;
  $uemail = fix_html($_POST['uemail']);
  if((strtoupper($unick) == strtoupper($nick)) && ($nick != "Guest")) {
    $is_real = 1;
    if(empty($uemail)) $uemail = $a_email;
    /* IRC Operators will automatically get "above normal" priority unless they chose a low priority or the category has an higher priority */
    if($isoper && $upriority>=0 && $priority<10) $priority = 10;
  }
  else $is_real = 0;
  if(isset($_POST['private']) && ($_POST['private'] == "on")) $is_private = 1;
  else $is_private = 0;
  if(isset($_POST['email_upd']) && ($_POST['email_upd'] == "on")) $email_upd = 1;
  else $email_upd = 0;
  if(isset($_POST['memo_upd']) && ($_POST['memo_upd'] == "on")) $memo_upd = 1;
  else $memo_upd = 0;
  $description = $_POST['description'];
  if(empty($description)) $error = "You must write a ticket description!";
  if($uemail == "a@b.c") $error = "You must use a real email address!";
  if(isset($_POST['ext1']) && !empty($_POST['ext1'])) $ext1 = $_POST['ext1'];
  else if(preg_match("/\[AKILL ID:(\d+K-[a-z0-9]+)\]/", $description, $matches)) $ext1 = $matches[1];
  else if(preg_match("/\[ID: (DM-\d+)\]/", $description, $matches)) $ext1 = $matches[1];
  else if(preg_match("/\[ID:(\d+[QGA]-[a-z0-9]+)\]/", $description, $matches)) $ext1 = $matches[1];
  else if(preg_match("/proxyinfo.php\?dnsbl=([a-z\.]+)&ip=/", $description, $matches)) $ext1 = $matches[1];
  else if(preg_match("/monitor.php\?dnsbl=([a-z\.]+)&ip=/", $description, $matches)) $ext1 = $matches[1];
  if(isset($ext1)) {
    /* Some sanity checks to make sure the user give us the correct AKILL ID */
    if(preg_match("/OS2\d+-\d+/", $ext1, $matches)) {
      $error = "We can't locate the OS2 ID you provided, please provide the other ID you got.";
    }
    else if(strstr($ext1,"ID:")) $error = "The ID you provided is invalid, please makre sure you only write/copy the ID.";
    else if(preg_match("/\d+([KIQGA])-[a-z0-9]+/", $ext1, $matches)) {
      if(strstr($ext1,"]") || strstr($ext1,"[") || strstr($ext1,":")) $error = "The ID you provided is invalid, please makre sure you only write/copy the ID.";
      if(strstr($ext1," ")) $error = "The ID you provided is invalid, please makre sure you only write/copy the ID with no trailing spaces.";
    }
  }
  if(isset($ext1) && !is_backup_running()) custom_new_ticket_ext1_check($ext1);
  if(!$isoper && defined("IRC_MODE")) {
    /* Don't let users open another ticket if they already have a pending one on the same team but let opers bypass it! */
    $query = squery("SELECT id,unick,open_time,rep_g FROM karnaf_tickets WHERE status!=0 AND rep_g='%s' AND ((unick='%s' AND unick!='Guest') OR uemail='%s' OR (opened_by='%s' AND opened_by!='Guest'))",
                    $rep_g, $unick, $uemail, $unick);
    if($result = sql_fetch_array($query)) {
      $error = "You already have an open ticket (#".$result['id'].") for ".$rep_g.".<br>";
      $error .= "Please do not open more than one ticket about the same issue.<br>";
      $error .= "If you need to send a reply or give more information please view your current ticket stauts and post a reply there.";
    }
    sql_free_result($query);
  }
  if(isset($error)) {
    echo "Error!<br><br>".$error;
  }
  else {
    squery("INSERT INTO karnaf_tickets(randcode,status,description,cat3_id,unick,ufullname,uemail,uphone,uip,upriority,priority,open_time,opened_by,rep_u,rep_g,is_real,is_private,email_upd,memo_upd) VALUES('%s',%d,'%s','%d','%s','%s','%s','%s','%s',%d,%d,%d,'%s','%s','%s',%d,%d,%d,%d)",
           $randstr,1,$description,$cat3_id,$unick,fix_html($_POST['uname']),$uemail,$uphone,$uip,$upriority,$priority,time(),$nick,$rep_u,
           $rep_g,$is_real,$is_private,$email_upd,$memo_upd);
    $id = sql_insert_id();
    if(isset($ext1)) squery("UPDATE karnaf_tickets SET ext1='%s' WHERE id=%d", $ext1, $id);
    if(isset($_POST['ext2']) && !empty($_POST['ext2'])) squery("UPDATE karnaf_tickets SET ext2='%s' WHERE id=%d", $_POST['ext2'], $id);
    if(isset($_POST['ext3']) && !empty($_POST['ext3'])) squery("UPDATE karnaf_tickets SET ext3='%s' WHERE id=%d", $_POST['ext3'], $id);
?>
Your ticket has been opened.
<br>
Ticket ID: <?=$id?>
<br>
Verification Number: <?=$randstr?>
<br>
Assigned to: <?=$rep_g?>
<br>
Ticket status: <a href="<?=KARNAF_URL?>/view.php?id=<?=$id?>&code=<?=$randstr?>"><?=KARNAF_URL?>/view.php?id=<?=$id?>&code=<?=$randstr?></a>
<? custom_new_ticket_thankyou(); ?>
<?
  }
} else {
?>
<script type="text/javascript">
var xmlhttp;
function loadcat2(cat1) {
    if (cat1 == 0) return;
    xmlhttp=null;
    if (window.XMLHttpRequest) {// code for all new browsers
        xmlhttp=new XMLHttpRequest();
    }
    else if (window.ActiveXObject) {// code for IE5 and IE6
        xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
    }
    if (xmlhttp!=null) {
        document.getElementById('Ticket_Category').innerHTML = 'Loading categories, please wait...';
        document.getElementById('Ticket_Extra').innerHTML = '';
        xmlhttp.onreadystatechange=do_cat2_change;
        xmlhttp.open("GET","karnaf_categories.php?x2&id=" + cat1,true);
        xmlhttp.send(null);
    }
    else{
        alert("Your browser does not support XMLHTTP.");
    }
}

function loadcat3(cat2) {
    xmlhttp=null;
    if (window.XMLHttpRequest) {// code for all new browsers
        xmlhttp=new XMLHttpRequest();
    }
    else if (window.ActiveXObject) {// code for IE5 and IE6
        xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
    }
    if (xmlhttp!=null) {
        document.getElementById('Ticket_Subject').innerHTML = 'Loading subjects, please wait...';
        document.getElementById('Ticket_Extra').innerHTML = '';
        xmlhttp.onreadystatechange=do_cat3_change;
        xmlhttp.open("GET","karnaf_subjects.php?x2&id=" + cat2,true);
        xmlhttp.send(null);
    }
    else{
        alert("Your browser does not support XMLHTTP.");
    }
}

function loadext(cat3) {
    xmlhttp=null;
    if (window.XMLHttpRequest) {// code for all new browsers
        xmlhttp=new XMLHttpRequest();
    }
    else if (window.ActiveXObject) {// code for IE5 and IE6
        xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
    }
    if (xmlhttp!=null) {
        document.getElementById('Ticket_Extra').innerHTML = '';
        xmlhttp.onreadystatechange=do_ext_change;
        xmlhttp.open("GET","karnaf_ext.php?x2&id=" + cat3,true);
        xmlhttp.send(null);
    }
    else {
        alert("Your browser does not support XMLHTTP.");
    }
}

function do_cat2_change() {
    if (xmlhttp.readyState==4) {
        if (xmlhttp.status==200) {
            //alert(xmlhttp.responseText);
            document.getElementById('Ticket_Category').innerHTML = xmlhttp.responseText;
            //alert("error status code: " + xmlhttp.status);
            xmlhttp = null;
            loadcat3(0);
        }
        else {
            alert("Problem retrieving XML data");
            alert("error status code: " + xmlhttp.status);
            xmlhttp = null;
        }
    }
}

function do_cat3_change() {
    if (xmlhttp.readyState==4) {
        if (xmlhttp.status==200) {
            //alert(xmlhttp.responseText);
            document.getElementById('Ticket_Subject').innerHTML = xmlhttp.responseText;
            //alert("error status code: " + xmlhttp.status);
        }
        else{
            alert("Problem retrieving XML data");
            alert("error status code: " + xmlhttp.status);
        }
        xmlhttp = null;
    }
}

function do_ext_change() {
    if (xmlhttp.readyState==4) {
        if (xmlhttp.status==200) {
            //alert(xmlhttp.responseText);
            document.getElementById('Ticket_Extra').innerHTML = xmlhttp.responseText;
            //alert("error status code: " + xmlhttp.status);
        }
        else{
            alert("Problem retrieving XML data");
            alert("error status code: " + xmlhttp.status);
        }
        xmlhttp = null;
    }
}

function open_ticket() {
    if(document.form1.description.value == '') {
        alert('You must write a ticket description.');
        return;
    }
    if(document.form1.cat3.value == 0) {
        alert('You must choose a ticket type/category/subject.');
        return;
    }
<? if($nick == "Guest") { ?>
    if(document.form1.uemail.value == '') {
        alert('You must write a valid email address if you are not using your registered nick.');
        return;
    }
<? } ?>
    document.form1.submit1.disabled = true;
    document.form1.submit1.value = 'Loading, please wait...';
    document.form1.submit();
}

<? if($isoper) { ?>
function setinfo(username,name,email,phone) {
  form1.unick.value = username;
  form1.uname.value = name;
  form1.uemail.value = email;
  form1.uphone.value = phone;
}

function open_search() {
  window.open("searchuser.php","searchwin","status=0,toolbar=0,location=0,scrollbars=1,width=500,height=200");
}
<? } ?>

</script>
<? custom_new_ticket_welcome(); ?>
<form name="form1" id="form1" method="post">
<table border="1">
<tr class="Karnaf_Head2"><td colspan="2">Ticket Description</td></tr>
<tr><td colspan="2">
<textarea name="description" id="description" wrap=soft rows="10" style="width:100%"></textarea>
</td></tr>
<tr>
<td valign="top" width="50%">
<table width="100%">
<tr class="Karnaf_Head2"><td colspan="2">Client Information</td></tr>
<? if($nick == "Guest") { ?>
<tr>
<td><?=USER_FIELD?>:</td>
<td><input name="unick" type="text" value="<?=$nick?>"></td>
</tr>
<tr>
<td>Password:</td>
<td><input type="password" name="password" maxlength="50"></td>
</tr>
<? } else if($isoper) { ?>
<tr>
<td><?=USER_FIELD?>:</td>
<td><input name="unick" type="text" value=""><input type="button" value="Search" onClick="javascript:open_search()"></td>
</tr>
<? } else { ?>
<tr>
<td><?=USER_FIELD?>:</td>
<td><input name="unick" type="text" value="<?=$nick?>"></td>
</tr>
<? } ?>
<tr>
<td>Name:</td>
<td><input name="uname" type="text"></td>
</tr>
<tr>
<td>E-Mail:</td>
<td><input name="uemail" id="uemail" type="text" value="<?=($isoper?"":$a_email)?>"></td>
</tr>
<? if(!defined("IRC_MODE")) { ?>
<tr>
<td>Phone:</td>
<td><input name="uphone" type="text"></td>
</tr>
<? } ?>
<tr>
<td>IP:</td>
<td>
<? if($isoper) { ?>
<input name="uip" type="text">
<? } else { ?>
<?=get_session_ip()?>
<? } ?>
</td>
</tr>
<tr>
<td>Update by Mail:</td>
<td><input name="email_upd" type="checkbox" checked></td>
</tr>
<tr>
<td>Update by Memo:</td>
<td><input name="memo_upd" type="checkbox"<? if($nick != "Guest") echo " checked"; ?>></td>
</tr>
</table>
</td>
<td valign="top">
<table width="100%">
<tr class="Karnaf_Head2"><td colspan="2">Ticket Information</td></tr>
<tr>
<td>Ticket Type:</td>
<td>
<select name="cat1" id="cat1" onChange="javascript:loadcat2(this.value);">
<option value="0">--Select--</option>
<?
  $cat1_id = 0;
  $query = squery("SELECT id,name FROM karnaf_cat1 ORDER BY priority,name");
  while($result = sql_fetch_array($query)) {
    if(!$cat1_id) $cat1_id = (int)$result['id'];
?>
<option value="<?=$result['id']?>"><?=$result['name']?></option>
<?
  }
  sql_free_result($query);
?>
</select>
</td>
</tr>
<tr>
<td>Ticket Category:</td>
<td>
<span id="Ticket_Category">
<select name="cat2" id="cat2" disabled onChange="javascript:loadcat3(this.value);">
<option value="0">--Select--</option>
</select>
</span>
</td>
</tr>
<tr>
<td>Ticket Subject</td>
<td>
<span id="Ticket_Subject">
<select name="cat3" id="cat3" disabled>
<option value="0">--Select--</option>
</select>
</span>
</td>
</tr>
<tr>
<td>Priority:</td>
<td>
<select name="upriority">
<?
  $upriority = 0;
  $query = squery("SELECT priority_id,priority_name FROM karnaf_priorities ORDER BY priority_id");
  while($result = sql_fetch_array($query)) {
?>
<option value="<?=$result['priority_id']?>"<? if($result['priority_id']==$upriority) echo " SELECTED"; ?>><?=$result['priority_name']?></option>
<?
  }
  sql_free_result($query);
?>
</select>
</td>
</tr>
<? if($isoper) { ?>
<tr>
<td>Private Ticket:</td>
<td><input name="private" type="checkbox"></td>
</tr>
<? } ?>
</table>
</td>
</tr>
</table>
<span id="Ticket_Extra">
</span>
<br>
<center><input name="submit1" id="submit1" type="button" value="Open Ticket" onClick="javascript:open_ticket();"></center>
</form>
<?
}
require_once("karnaf_footer.php");
?>
