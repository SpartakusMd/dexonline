<?php
require_once("../../phplib/util.php"); 
ini_set('memory_limit', '512M');
util_assertModerator(PRIV_EDIT);
util_assertNotMirror();

$lexems = Model::factory('Lexem')->where('consistentAccent', 0)->order_by_asc('formNoAccent')->find_many();

RecentLink::createOrUpdate('Lexeme fără accent');

SmartyWrap::assign('sectionTitle', 'Lexeme fără accent');
SmartyWrap::assign('recentLinks', RecentLink::loadForUser());
SmartyWrap::assign('lexems', $lexems);
SmartyWrap::assign('sectionCount', count($lexems));
SmartyWrap::displayAdminPage('admin/lexemList.ihtml');

?>
