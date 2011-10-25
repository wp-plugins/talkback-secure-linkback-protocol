<?php
/*!
* TalkBack basic-client public key  page
* This file shows how to use the TalkBack library to display the public key that other blog need to fetch.
* @Author: Elie Bursztein (elie _AT_ cs.stanford.edu)
* @version: 1.0
* @url: http://talkback.stanford.edu
* @Licence: LGPL
*/

require_once "config.php"; 				//configuration file
require_once "talkbackClass.php";	//the talkback cslient library

//creating an instance of the lib
$tb = new TalkBack();

if (!$tb->load(FALSE)) {
	die("can't get the public key. Did  you do the registration process ?");
}

print $tb->keyGetPub();

?>
