<?php

class Entry extends BaseObject implements DatedObject {
  public static $_table = 'Entry';

  private $tags = null;

  const STRUCT_STATUS_NEW = 1;
  const STRUCT_STATUS_IN_PROGRESS = 2;
  const STRUCT_STATUS_UNDER_REVIEW = 3;
  const STRUCT_STATUS_DONE = 4;

  const STRUCTURIST_ID_ANY = -1;
  const STRUCTURIST_ID_NONE = 0;

  public static $STRUCT_STATUS_NAMES = [
    self::STRUCT_STATUS_NEW => 'neîncepută',
    self::STRUCT_STATUS_IN_PROGRESS => 'în lucru',
    self::STRUCT_STATUS_UNDER_REVIEW => 'așteaptă moderarea',
    self::STRUCT_STATUS_DONE => 'terminată',
  ];

  // create and associate and empty tree if $tree == true
  static function createAndSave($description, $tree = false) {
    $e = Model::factory('Entry')->create();
    $e->description = $description;
    $e->structStatus = self::STRUCT_STATUS_NEW;
    $e->save();

    if ($tree) {
      $t = Tree::createAndSave($description);
      TreeEntry::associate($t->id, $e->id);
    }

    return $e;
  }

  function _clone($cloneDefinitions, $cloneLexems, $cloneTrees, $cloneStructurist) {
    $e = $this->parisClone();

    if (!$cloneStructurist) {
      $e->structStatus = self::STRUCT_STATUS_NEW;
      $e->structuristId = 0;
    }
    $e->save();

    if ($cloneDefinitions) {
      EntryDefinition::copy($this->id, $e->id, 1);
    }

    if ($cloneLexems) {
      EntryLexem::copy($this->id, $e->id, 1, ['main' => true]);
      EntryLexem::copy($this->id, $e->id, 1, ['main' => false]);
    }

    if ($cloneTrees) {
      TreeEntry::copy($this->id, $e->id, 2);
    }

    return $e;
  }

  function loadMeanings() {
    foreach ($this->getTrees() as $t) {
      $t->getMeanings();
    }
  }

  // Returns the description up to the first parenthesis (if any).
  function getShortDescription() {
    return preg_split('/\s+[(\/]/', $this->description)[0];
  }

  function getTags() {
    if ($this->tags === null) {
      $this->tags = ObjectTag::getTags($this->id, ObjectTag::TYPE_ENTRY);
    }
    return $this->tags;
  }

  /**
   * Returns the list of lexemes sorted with main lexemes first. Excludes duplicate lexemes
   * and lexemes that have a form equal to the entry's description.
   **/
  function getPrintableLexems() {
    return Model::factory('Lexem')
      ->table_alias('l')
      ->select('l.*')
      ->select('el.main')
      ->distinct()
      ->join('EntryLexem', ['l.id', '=', 'el.lexemId'], 'el')
      ->where('el.entryId', $this->id)
      ->where_not_equal('l.formNoAccent', $this->getShortDescription())
      ->order_by_desc('el.main')
      ->order_by_asc('l.formNoAccent')
      ->find_many();
    return $results;
  }

  static function loadUnassociated() {
    $query = 'select * from Entry ' .
           'where id not in (select entryId from EntryLexem) ' .
           'or id not in (select entryId from EntryDefinition)';
    return Model::factory('Entry')
      ->raw_query($query)
      ->find_many();
  }

  /**
   * For every set of entries having the same case-sensitive description, load one of them at random.
   */
  static function loadAmbiguous() {
    // The key here is to create a subquery of all the case-insensitiv descriptions
    // appearing at least twice.
    $query = 'select * from Entry ' .
           'join (select description d from Entry group by description having count(*) > 1) dup ' .
           'on description = d ' .
           'group by binary description ' .
           'having count(*) > 1 ' .
           'order by description';
    return Model::factory('Entry')->raw_query($query)->find_many();
  }

  /**
   * Load entries marked as under review or structured having definitions that still need to
   * be structured.
   **/
  static function loadWithDefinitionsToStructure() {
    return Model::factory('Entry')
      ->table_alias('e')
      ->select('e.*')
      ->distinct()
      ->join('EntryDefinition', ['e.id', '=', 'ed.entryId'], 'ed')
      ->join('Definition', ['ed.definitionId', '=', 'd.id'], 'd')
      ->join('Source', ['d.sourceId', '=', 's.id'], 's')
      ->where_in('e.structStatus', [self::STRUCT_STATUS_UNDER_REVIEW, self::STRUCT_STATUS_DONE])
      ->where('d.structured', 0)
      ->where_in('d.status', [Definition::ST_ACTIVE, Definition::ST_HIDDEN])
      ->where('s.structurable', 1)
      ->order_by_asc('e.description')
      ->find_many();
  }

  static function searchInflectedForms($cuv, $hasDiacritics) {
    $field = $hasDiacritics ? 'formNoAccent' : 'formUtf8General';

    // load lexemes from two sources:
    // * simple lexemes that generate this form;
    // * comppound lexemes that have a fragment that generates this form

    $simple = 'select l.id ' .
            'from Lexem l ' .
            'join InflectedForm i on l.id = i.lexemId ' .
            "where i.$field = :form";
    $compound = 'select l.id ' .
              'from Lexem l ' .
              'join Fragment f on l.id = f.lexemId ' .
              'join InflectedForm i on f.partId = i.lexemId ' .
              "where i.$field = :form";
    $subquery = "{$simple} union {$compound}";

    // load entries for the above lexemes
    $query = 'select distinct e.* ' .
           'from Entry e ' .
           'join EntryLexem el on e.id = el.entryId ' .
           "join ({$subquery}) l on el.lexemId = l.id";

    $entries = Model::factory('Entry')
             ->raw_query($query, ['form' => $cuv])
             ->order_by_expr("(e.description != '{$cuv}')") // exact match
             ->order_by_expr("(e.description not like concat ('{$cuv}', ' (%'))") // partial match
             ->order_by_asc('e.description')
             ->find_many();

    return $entries;
  }

  // Returns the first main lexeme (or the first lexeme if none of them are main).
  function getMainLexem() {
    return Model::factory('Lexem')
      ->table_alias('l')
      ->select('l.*')
      ->join('EntryLexem', ['l.id', '=', 'el.lexemId'], 'el')
      ->where('el.entryId', $this->id)
      ->where('el.main', true)
      ->order_by_asc('el.lexemRank')
      ->find_one();
  }

  function getMainLexemIds() {
    return $this->getLexemIds(['main' => true]);
  }

  function getVariantLexemIds() {
    return $this->getLexemIds(['main' => false]);
  }

  static function getHomonyms($entries) {
    $entryIds = [];
    $homonymIds = [];

    foreach ($entries as $e) {
      $entryIds[] = $e->id;

      foreach ($e->getLexems() as $l) {
        $homonymEntries = Model::factory('EntryLexem')
                        ->table_alias('el')
                        ->select('el.entryId')
                        ->join('Lexem', ['el.lexemId', '=', 'l.id'], 'l')
                        ->where('l.formNoAccent', $l->formNoAccent)
                        ->find_array();
        foreach ($homonymEntries as $h) {
          $homonymIds[$h['entryId']] = true;
        }
      }
    }

    if (empty($homonymIds)) {
      $homonyms = [];
    } else {
      $homonyms = Model::factory('Entry')
                ->where_in('id', array_keys($homonymIds))
                ->where_not_in('id', $entryIds)
                ->find_many();
    }

    return $homonyms;
  }

  /**
   * Validates an entry for correctness. Returns an array of { field => array of errors }.
   * $original: the original, unmodified entry
   **/
  function validate($original) {
    $errors = [];

    if (!mb_strlen($this->description)) {
      $errors['description'][] = 'Descrierea nu poate fi vidă.';
    }

    if (($this->structStatus == Entry::STRUCT_STATUS_DONE) &&
        ($original->structStatus != Entry::STRUCT_STATUS_DONE) &&
        !User::can(User::PRIV_EDIT)) {
      $errors['structStatus'][] = 'Doar moderatorii pot marca structurarea drept terminată. ' .
                                'Vă rugăm să folosiți valoarea „așteaptă moderarea”.';
    }

    if ($this->structuristId != $original->structuristId) {
      if (User::can(User::PRIV_ADMIN)) {
        // Admins can modify this field
      } else if (($original->structuristId == User::getActiveId()) &&
                 !$this->structuristId) {
        // Structurists can remove themselves
      } else if (!$original->structuristId &&
                 ($this->structuristId == User::getActiveId()) &&
                 ($original->structStatus == Entry::STRUCT_STATUS_NEW) &&
                 ($this->structStatus == Entry::STRUCT_STATUS_IN_PROGRESS)) {
        // The system silently assigns structurists when they start the process
      } else if (!$original->structuristId &&
                 ($this->structuristId == User::getActiveId()) &&
                 ($original->structStatus == Entry::STRUCT_STATUS_IN_PROGRESS) &&
                 ($this->structStatus == Entry::STRUCT_STATUS_IN_PROGRESS)) {
        // Structurists can claim orphan entries
      } else {
        $errors['structuristId'] = 'Nu puteți modifica structuristul, dar puteți (1) revendica ' .
                                 'o intrare în lucru fără structurist sau (2) renunța la ' .
                                 'o intrare dacă vi se pare prea greu de structurat.';
      }
    }

    return $errors;
  }

  // delete empty trees that are not associated with any other entries
  function deleteEmptyTrees() {
    foreach ($this->getTrees() as $t) {
      $meaning = Meaning::get_by_treeId($t->id);
      if (!$meaning) {

        $otherAssoc = Model::factory('TreeEntry')
                    ->where('treeId', $t->id)
                    ->where_not_equal('entryId', $this->id)
                    ->find_one();

        if (!$otherAssoc) {
          $t->delete();
        }
      }
    }
  }

  function mergeInto($otherId) {
    $this->deleteEmptyTrees();

    EntryDefinition::copy($this->id, $otherId, 1);
    EntryLexem::copy($this->id, $otherId, 1, ['main' => true]);
    EntryLexem::copy($this->id, $otherId, 1, ['main' => false]);
    TreeEntry::copy($this->id, $otherId, 2);

    $visuals = Visual::get_all_by_entryId($this->id);
    foreach ($visuals as $v) {
      $v->entryId = $otherId;
      $v->save();
    }

    $vts = VisualTag::get_all_by_entryId($this->id);
    foreach ($vts as $vt) {
      $vt->entryId = $otherId;
      $vt->save();
    }

    $this->delete();
  }

  function delete() {
    EntryDefinition::delete_all_by_entryId($this->id);
    EntryLexem::delete_all_by_entryId($this->id);
    TreeEntry::delete_all_by_entryId($this->id);

    // orphan Visuals and VisualTags
    $visuals = Visual::get_all_by_entryId($this->id);
    foreach ($visuals as $v) {
      $v->entryId = 0;
      $v->save();
    }

    $vts = VisualTag::get_all_by_entryId($this->id);
    foreach ($vts as $vt) {
      $vt->entryId = 0;
      $vt->save();
    }

    Log::warning("Deleted entry {$this->id} ({$this->description})");
    parent::delete();
  }

  function __toString() {
    return $this->description;
  }

}

?>
