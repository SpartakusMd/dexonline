<?php
require_once '../lib/Core.php';
$p = Request::get('p');

switch($p) {
  case '404':
    http_response_code(404);
    break;
  case 'contact': break;
  case 'links': break;
  default: exit;
}

Smart::display("$p.tpl");
