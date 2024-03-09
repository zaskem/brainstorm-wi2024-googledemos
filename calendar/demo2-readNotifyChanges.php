<?php
  if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
  }

  require_once __DIR__ . '/../conf/settings.php';

  // Get the API client and construct the service object.
  $client = new Google_Client();
  $client->useApplicationDefaultCredentials();
  $client->addScope(Google_Service_Calendar::CALENDAR_READONLY);
  $calendar = new Google_Service_Calendar($client);

  $referenceInputCalendarId = 'YOURCALENDARIDGOESHERE@group.calendar.google.com'; // <-- EDIT THIS LINE TO ADD YOUR CALENDAR ID
  $calendarStatusStateFile = __DIR__ . '/../conf/calendar-events.php';

  $previousEventsData = include($calendarStatusStateFile);

  $refCalendarParams = array(
    'maxResults' => 10,
    'orderBy' => 'startTime',
    'singleEvents' => true,
    'timeMin' => date('c', strtotime('-2 months')),
    'timeMax' => date('c', strtotime('+2 weeks')),
    'timeZone' => 'America/Chicago',
  );

  // Obtain reference calendar events
  // Obtain first upcoming event
  $results = $calendar->events->listEvents($referenceInputCalendarId, $refCalendarParams);
  $events = $results->getItems();
  $firstEvent = reset($events);
  $start = getEventDate($firstEvent, $eventType = 'next', true);
  $end = getEventDate($firstEvent, $eventType = 'next', false);

  foreach ($events as $event) {
    if (array_key_exists($event['id'], $previousEventsData)) {
      if ($event['updated'] > $previousEventsData[$event['id']]['updated']) {
        print "UPDATED information for '$event[summary]' is available...\n";
        $eventDateSummary = (is_null($event['start']['date'])) ? $event['start']['dateTime'] : $event['start']['date'];
        $foundEvent = array('created'=>$event['created'], 'updated'=>$event['updated'], 'summary'=>$event['summary'], 'description'=>$event['description'], 'creator'=>$event['creator']['displayName'], 'effDate'=>$eventDateSummary);
        $previousEventsData[$event['id']] = $foundEvent;
      } else {
        print "We've already seen '$event[summary]' in previous runs, skipping...\n";
      }
    } else {
      $eventDateSummary = (is_null($event['start']['date'])) ? $event['start']['dateTime'] : $event['start']['date'];
      $foundEvent = array('created'=>$event['created'], 'updated'=>$event['updated'], 'summary'=>$event['summary'], 'description'=>$event['description'], 'creator'=>$event['creator']['displayName'], 'effDate'=>$eventDateSummary);
      $previousEventsData[$event['id']] = $foundEvent;
      print "Adding '$event[summary]' to the seen list...\n";
    }
  }

  // Finally, write out our last status
  file_put_contents($calendarStatusStateFile, '<?php return ' . var_export($previousEventsData, true) . '; ?>');


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