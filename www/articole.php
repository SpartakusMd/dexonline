<?php
require_once '../lib/Core.php';

$type = Request::get('t');

if ($type == 'rss') {
  $articles = WikiArticle::getRss();
  $results = [];
  foreach ($articles as $a) {
    $results[] = [
      'title' => $a->title,
      'description' => $a->htmlContents,
      'pubDate' => date('D, d M Y H:i:s', $a->modDate) . ' EEST',
      'link' => sprintf("http://%s/articol/%s", $_SERVER['HTTP_HOST'], $a->getUrlTitle()),
    ];
  }

  header("Content-type: application/rss+xml");
  Smart::assign([
    'rss_title' => 'Articole lingvistice - dexonline',
    'rss_link' => 'http://' . $_SERVER['HTTP_HOST'] . '/rss/articole/',
    'rss_description' => 'Articole pe teme lingvistice de la dexonline',
    'rss_pubDate' => date('D => d M Y H:i:s') . ' EEST',
    'results' => $results,
  ]);
  Smart::displayWithoutSkin('xml/rss.tpl');
  exit;
}

Smart::assign('wikiTitles', WikiArticle::loadAllTitles());
Smart::display('articole.tpl');
