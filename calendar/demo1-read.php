<?php
  if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
  }

  require_once __DIR__ . '/../conf/settings.php';
  $status = include(__DIR__ . '/../conf/calendar-status.php');

  // Get the API client and construct the service object.
  $client = new Google_Client();
  $client->useApplicationDefaultCredentials();
  $client->addScope(Google_Service_Calendar::CALENDAR_READONLY);
  $calendar = new Google_Service_Calendar($client);

  $readCalendarId = 'en.usa#holiday@group.v.calendar.google.com'; // Holidays in the US
  $progressCharacter = "▓";
  $remainingCharacter = "░";


  // Obtain the next upcoming event
  $rCalendarFutureParams = array(
    'maxResults' => 1,
    'orderBy' => 'startTime',
    'singleEvents' => true,
    'timeMin' => date('c'),
    'timeZone' => 'America/Chicago',
  );
  $results = $calendar->events->listEvents($readCalendarId, $rCalendarFutureParams);
  $events = $results->getItems();
  // Write out the response/detail of the event
  print_r($events);


  print "\n\nDone. Press any key to continue: ";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);
  print "\nProgress between the previous and next holiday:\n";


  // Obtain most recent (past) event
  $rCalendarPastParams = array(
    'maxResults' => 100,
    'orderBy' => 'startTime',
    'singleEvents' => true,
    'timeMin' => date('c', strtotime('-3 months')),
    'timeMax' => date('c'),
    'timeZone' => 'America/Chicago',
  );
  $lastResult = $calendar->events->listEvents($readCalendarId, $rCalendarPastParams);
  $lastEvents = $lastResult->getItems();
  $lastEvent = end($lastEvents);
  $leStart = getEventDate($lastEvent, $eventType = 'past', true);
  $leEnd = getEventDate($lastEvent, $eventType = 'past', false);
  
  // Obtain first upcoming event
  $rCalendarFutureParams = array(
    'maxResults' => 2,
    'orderBy' => 'startTime',
    'singleEvents' => true,
    'timeMin' => date('c'),
    'timeZone' => 'America/Chicago',
  );
  $results = $calendar->events->listEvents($readCalendarId, $rCalendarFutureParams);
  $events = $results->getItems();
  $nextEvent = reset($events);
  $start = getEventDate($nextEvent, $eventType = 'next', true);
  $end = getEventDate($nextEvent, $eventType = 'next', false);

  $activeEvent = false;
  $eventInterval = date_diff(date_create($leEnd), date_create($start));
  $timePassed = date_diff(date_create($leEnd), date_create());
  if ($leStart == $start) {
    // Active event but we don't care (post progress), grab the next one and recalculate.
    $nextEvent = next($events);
    $start = getEventDate($nextEvent, $eventType = 'next', true);
    $end = getEventDate($nextEvent, $eventType = 'next', false);
    $eventInterval = date_diff(date_create($leStart), date_create($start));
  }

  $percentComplete = intval((((($timePassed->days * 24) + $timePassed->h) * 60) + $timePassed->i) / (((($eventInterval->days * 24) + $eventInterval->h) * 60) + $eventInterval->i) * 100);
  $totalBars = 25;
  $completeBars = min(intval($percentComplete / (100 / ($totalBars + 1))), $totalBars);
  $incompleteBars = $totalBars - $completeBars;

  /**
   * We make a special post when we match that special circumstance when a holiday/event is reached the first time.
   *  Post a little differently (celebrate!)
   *  - this is the FIRST TIME we've seen this event title/summary.
   *      The logic looks weird because $lastEvent->getSummary() is for the _current_ run and $status['lastEventSummary']
   *      is for the _previous_ run of the bot script.
  */
  if ($lastEvent->getSummary() != $status['lastEventSummary']) {
    $postText = "Hooray! It's " . $lastEvent->getSummary() . "!";
    print "\n\n$postText\n\n";
  /**
   * Prepare to normally post...if we should (see negative logic). We don't normally post when:
   *  - an active event is happening today (no post), unless we don't respect active events
   *  - the percent complete hasn't changed since our last go (no post)
   */
  } else if (!($activeEvent || ($percentComplete == $status['lastPercentComplete']))) {
    // Craft a traditional post text
    $postText = "";
    // Progress bar creation...
    $z = 0;
    while ($z < $completeBars) {
      $postText .= $progressCharacter;
      $z++;
    }
    $z = 0;
    while ($z < $incompleteBars) {
      $postText .= $remainingCharacter;
      $z++;
    }

    // Auxiliary/Ancillary text for the post
    $postText .= " $percentComplete%\n\n";
    $postText .= "from " . $lastEvent->getSummary();
    $postText .= " to " . $nextEvent->getSummary();
    $postText .= "!";

    print "\n\n$postText\n\n";
  }

  // Finally, write out our last status
  $status['lastPercentComplete'] = $percentComplete;
  $status['lastEventDate'] = $leStart;
  $status['lastEventSummary'] = $lastEvent->getSummary();
  file_put_contents(__DIR__ . '/../conf/calendar-status.php', '<?php return ' . var_export($status, true) . '; ?>');


  /**
   * getEventDate($sourceEvent, $eventType = 'next', $start = true)
   *  Simple function to return the string format of $sourceEvent's date/dateTime
   *  - $sourceEvent = instance of Google_Service_Calendar_Event Object (not an array of events)
   *  - $eventType = 'next' (default): nature of event. Possible options ('next','past'), though 
   *      any argument other than 'next' is treated as 'past'
   *      $eventType is used to nuance a default date if/when none exists.
   *  - $start = obtain the event's start (true) or end (false) time.
   * @return string the event date
   */
  function getEventDate($sourceEvent, $eventType = 'next', $start = true) {
    global $allDayEventEndTime;
    if (empty($sourceEvent)) {
      // Default to today/now in unlikely situation $sourceEvent is empty
      $year = ('next' == $eventType) ? date('Y') + 1 : date('Y') - 1;
      $calcDate = "$year-01-01";
    } else {
      // If dateTime format is availble, prefer it (not an all-day event)
      $calcDate = ($start) ? $sourceEvent->start->dateTime : $sourceEvent->end->dateTime;
      if (empty($calcDate)) {
        // Use date format if dateTime isn't available (an all-day event), add time for "end" dates.
        $calcDate = ($start) ? $sourceEvent->start->date : $sourceEvent->start->date . " " . $allDayEventEndTime;
      }
    }
    return $calcDate;
  }

?>