<?php

/**
 * Instantiating a parser is expensive, so we do it in a factory class.
 **/
class ParserFactory {
  // map of sourceId => class name
  private static $sourceMap = null;

  // map of sourceId => instantiated parser
  private static $parserMap = [];

  static function getParser($definition) {
    $sid = $definition->sourceId;

    self::loadSourceMap();

    if (!array_key_exists($sid, self::$parserMap)) {
      if (isset(self::$sourceMap[$sid])) {
        // instantiate the parser lazily
        $className = self::$sourceMap[$sid];
        self::$parserMap[$sid] = new $className();
      } else {
        // no parser is defined for this source
        self::$parserMap[$sid] = null;
      }
    }

    if (self::$parserMap[$sid]) {
      // PHP-parsing-tool runs out of memory for large definitions
      ini_set('memory_limit', '1G');
    }

    return self::$parserMap[$sid];
  }

  private static function loadSourceMap() {
    if (self::$sourceMap == null) {
      $cfg = Config::PARSERS;
      $sources = Model::factory('Source')
        ->where_in('urlName', array_keys($cfg))
        ->find_many();

      $map = [];
      foreach ($sources as $s) {
        $map[$s->urlName] = $s->id;
      }

      self::$sourceMap = [];
      foreach ($cfg as $urlName => $class) {
        if (isset($map[$urlName])) {
          self::$sourceMap[$map[$urlName]] = $class;
        }
        // otherwise the source urlName is probably misspelled, but it's also
        // possible we are in testing mode and that source does not exist
      }
    }
  }

}
