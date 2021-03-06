<?php
require_once '../lib/Core.php';

$modelType = Request::get('modelType');

$modelType = ModelType::get_by_code($modelType); // Use the ModelType object from this point on

if (!$modelType) {
  FlashMessage::add('Date incorecte.');
  Util::redirect('scrabble');
}
$models = FlexModel::loadByType($modelType->code);

$lexemes = [];
foreach ($models as $m) {
  $lexemes[] = $m->getExponentWithParadigm();
}

Smart::addCss('paradigm');
Smart::assign('models', $models);
Smart::assign('lexemes', $lexemes);
Smart::assign('modelType', $modelType);
Smart::display('modele-flexiune.tpl');
