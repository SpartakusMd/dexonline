<?php
/**
 * This script grants code, e-mail and volunteer medals.
 **/

require_once __DIR__ . '/../lib/Core.php';

const OTRS_MAP = [
  'cata' => 1,
  'Octavian.Mocanu' => 4,
  'radu_borza' => 471,
];

const VOLUNTEER_LEVELS = [
  Medal::MEDAL_VOLUNTEER_5 => 10000000,
  Medal::MEDAL_VOLUNTEER_4 => 1000000,
  Medal::MEDAL_VOLUNTEER_3 => 100000,
  Medal::MEDAL_VOLUNTEER_2 => 10000,
  Medal::MEDAL_VOLUNTEER_1 => 1000,
];

const MIN_DONATION_FOR_MEDAL = 20;
const MIN_DONATION_FOR_HIDDEN_BANNERS = 50;

// user IDs of people for whom we never want to hide banners
const MUST_SEE_BANNERS = [ 1 ];

Log::notice('started');

$opts = getopt('dnov');
$skipDonors = isset($opts['d']);
$skipOtrs = isset($opts['o']);
$skipVolunteers = isset($opts['v']);
$dryRun = isset($opts['n']);

if ($dryRun) {
  print "---- DRY RUN ----\n";
}

// OTRS medals
if (!$skipOtrs) {

  $query = 'select users.login, count(*) as count ' .
    'from otrs.users ' .
    'join otrs.article on users.id = article.change_by ' .
    'where users.id != 1 ' .
    'group by users.id';

  $dbResult = DB::execute($query, PDO::FETCH_ASSOC);
  foreach ($dbResult as $r) {
    if (array_key_exists($r['login'], OTRS_MAP)) {
      $user = User::get_by_id(OTRS_MAP[$r['login']]);
      if ($user && $user->id) {
        if (($r['count'] >= 1000) &&
            (!($user->medalMask & Medal::MEDAL_EMAIL_3))) {
          Log::info("Granting {$user->id} {$user->nick} a MEDAL_EMAIL_3");
          $user->medalMask |= Medal::MEDAL_EMAIL_3;
        } else if (($r['count'] < 1000) &&
                   ($r['count'] >= 500) &&
                   (!($user->medalMask & Medal::MEDAL_EMAIL_2))) {
          Log::info("Granting {$user->id} {$user->nick} a MEDAL_EMAIL_2");
          $user->medalMask |= Medal::MEDAL_EMAIL_2;
        } else if (($r['count'] < 500) &&
                   ($r['count'] >= 100) &&
                   (!($user->medalMask & Medal::MEDAL_EMAIL_1))) {
          Log::info("Granting {$user->id} {$user->nick} a MEDAL_EMAIL_1");
          $user->medalMask |= Medal::MEDAL_EMAIL_1;
        }
        $user->medalMask = Medal::getCanonicalMask($user->medalMask);
        if (!$dryRun) {
          $user->save();
        }
      }
    }
  }
}

// Volunteer medals
if (!$skipVolunteers) {
  $l = VOLUNTEER_LEVELS;
  $minCharsForMedal = end($l);
  $topData = TopEntry::getTopData(TopEntry::SORT_CHARS, SORT_DESC, true);

  foreach ($topData as $e) {
    $user = User::get_by_nick($e->userNick);
    if ($user && $user->id && $e->numChars >= $minCharsForMedal) {
      // determine the current medal level
      reset($l);
      while ($e->numChars < current($l)) {
        next($l);
      }
      $medal = key($l);

      // grant it if the user doesn't have it
      if (!($user->medalMask & $medal)) {
        Log::info('Granting %s %s a medal for %s', $user->id, $user->nick, Medal::getName($medal));
        $user->medalMask |= $medal;
        $user->medalMask = Medal::getCanonicalMask($user->medalMask);
        if (!$dryRun) {
          $user->save();
        }
      }
    }
  }
}

// Donor perks
if (!$skipDonors) {
  // Grant medals to users who don't have one, but have donated enough
  $needMedals = Model::factory('User')
              ->table_alias('u')
              ->select('u.*')
              ->select_expr('sum(d.amount)', 'total')
              ->distinct()
              ->join('Donation', ['d.email', '=', 'u.email'], 'd')
              ->where('anonymousDonor', 0)
              ->where_raw('!(medalMask & ?)', Medal::MEDAL_SPONSOR)
              ->group_by('u.id')
              ->having_raw('total >= ?', MIN_DONATION_FOR_MEDAL)
              ->find_many();
  foreach ($needMedals as $u) {
    Log::info("Granting {$u->id} {$u->nick} a sponsor medal");
    $u->medalMask |= Medal::MEDAL_SPONSOR;
    if (!$dryRun) {
      $u->save();
    }
  }

  // Hide banners for users who have donated recently (since their banners were last hidden)
  $oneYearFromNow = strtotime('+1 year');
  $noBanners = Model::factory('User')
             ->table_alias('u')
             ->select('u.*')
             ->select_expr('sum(d.amount)', 'total')
             ->distinct()
             ->join('Donation', ['d.email', '=', 'u.email'], 'd')
             ->where_not_in('u.id', MUST_SEE_BANNERS)
             ->where_raw('d.createDate >= u.noAdsUntil')
             ->group_by('u.id')
             ->having_raw('total >= ?', MIN_DONATION_FOR_HIDDEN_BANNERS)
             ->find_many();
  foreach ($noBanners as $u) {
    Log::info("Hiding banners for {$u->id} {$u->nick}");
    $u->noAdsUntil = $oneYearFromNow;
    if (!$dryRun) {
      $u->save();
    }
  }
}

Log::notice('finished');
