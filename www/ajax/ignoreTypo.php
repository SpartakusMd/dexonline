<?php
require_once '../../lib/Core.php';
User::mustHave(User::PRIV_EDIT);

$typoId = Request::get('id');
$typo = Typo::get_by_id($typoId);
if ($typo) {
  Log::debug("Ignored typo {$typo->id} ({$typo->problem}) reported by [{$typo->userName}]");
  $typo->delete();
}
