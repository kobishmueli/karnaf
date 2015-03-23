<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require("../ktools.php");
check_auth();
?>
<html>
<head>
<title>Karnaf Menu</title>
</head>
<body bgcolor="#DEF0D8">
<table cellpadding="4" cellspacing="1" border="0" width="100%">
<tr>
<td align="center">
<form action="edit.php" target="main" method="get">
<input name="id" type="text" value="" style="width: 100%">
</form>
</td>
</tr>
<?
function Add_Item($name, $link) {
?>
<tr style="cursor:pointer" bgcolor="#6959CD" onmouseover=this.style.backgroundColor="Blue" onmouseout=this.style.backgroundColor="" onclick=javascript:window.parent.main.location.href="<?=$link?>">
<td align="center">
<font color="White">
<?=$name?>
</font>
</td>
</tr>
<?
}
?>
<?
function Add_ItemRed($name, $link) {
?>
<tr style="cursor:pointer" bgcolor="Red" onmouseover=this.style.backgroundColor="Blue" onmouseout=this.style.backgroundColor="" onclick=javascript:window.parent.main.location.href="<?=$link?>">
<td align="center">
<font color="White">
<?=$name?>
</font>
</td>
</tr>
<?
}
?>
<?
function Add_Itemx($name, $link) {
?>
<tr style="cursor:pointer" bgcolor="Gray" onmouseover=this.style.backgroundColor="Blue" onmouseout=this.style.backgroundColor="" onclick=javascript:window.parent.main.location.href="<?=$link?>">
<td align="center">
<font color="White">
<?=$name?>
</font>
</td>
</tr>
<?
}
?>
<?
function Add_Item2($name, $link) {
?>
<tr style="cursor:pointer" bgcolor="#6959CD" onmouseover=this.style.backgroundColor="Blue" onmouseout=this.style.backgroundColor="" onclick=javascript:<?=$link?>>
<td>
<font color="White">
<?=$name?>
</font>
</td>
</tr>
<?
}
?>
<?
function Add_Item3($name, $link) {
?>
<tr style="cursor:pointer" bgcolor="#6959CD" onmouseover=this.style.backgroundColor="Blue" onmouseout=this.style.backgroundColor="" onclick=javascript:window.parent.location.href="<?=$link?>">
<td>
<font color="White">
<?=$name?>
</font>
</td>
</tr>
<?
}
?>
<? Add_Item("My List","mylist.php"); ?>
<? Add_Item("Open Tickets","list.php"); ?>
<? Add_Item("Ticket Lookup (by ID)","lookup.php"); ?>
<? Add_Item("New Ticket","new.php"); ?>
<? Add_Item("Search Ticket","search.php"); ?>
<? if(IsKarnafAdminSession()) { ?>
<? Add_Itemx("Edit types","mng_cat.php?table=cat1"); ?>
<? Add_Itemx("Edit catagories","mng_cat.php?table=cat2"); ?>
<? Add_Itemx("Edit subjects","mng_cat.php?table=cat3"); ?>
<? Add_Itemx("Edit templates","mng_templates.php"); ?>
<? Add_Itemx("View Logs","mng_viewlogs.php"); ?>
<? Add_Itemx("Edit SQL","mng_editsql.php"); ?>
<? Add_Itemx("Stats","stats.php"); ?>
<? Add_Item("Logout","logout.php"); ?>
<? } ?>
<tr><td>
<br>
<u>Tickets:</u>
<br>
<?
  $tickets = 0;
  $query = squery("SELECT count(*) FROM karnaf_tickets");
  if($result = sql_fetch_array($query)) $tickets = $result[0];
  sql_free_result($query);
  echo $tickets;
?>
</td></tr>
</table>
</body>
</html>
<? require_once("karnaf_footer.php"); ?>
