<?php
  if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
  }

  require_once __DIR__ . '/../conf/settings.php';

  // Get the API client and construct the service object.
  $client = new Google_Client();
  $client->useApplicationDefaultCredentials();
  $client->addScope(Google_Service_Calendar::CALENDAR_EVENTS);
  $calendar = new Google_Service_Calendar($client);

  $readWriteCalendarId = 'YOURDESTINATIONCALENDARIDGOESHERE@group.calendar.google.com'; // <-- EDIT THIS LINE TO ADD DESTINATION CALENDAR ID
  $referenceInputCalendarId = 'YOURSOURCECALENDARIDGOESHERE@group.calendar.google.com'; // <-- EDIT THIS LINE TO ADD SOURCE CALENDAR ID


  // Obtain upcoming events only
  $futureParams = array(
    'orderBy' => 'startTime',
    'singleEvents' => true,
    'timeMin' => date('c'),
    'timeZone' => 'America/Chicago',
  );

  // Query for currently-available (already on target calendar) items
  $results = $calendar->events->listEvents($readWriteCalendarId, $futureParams);

  $alreadyOnCalendar = array();
  foreach ($results->getItems() as $event) {
    $eventTitleKey = $event->getSummary();
    if (is_null($event->getStart()->getDateTime())) {
      $dateTimeKey = preg_split('/(-|:|T)/', $event->getStart()->getDate());
      $calendarEventKey = $dateTimeKey[1].'-'.$dateTimeKey[2].'-'.$eventTitleKey;
    } else {
      $dateTimeKey = preg_split('/(-|:|T)/', $event->getStart()->getDateTime());
      $calendarEventKey = $dateTimeKey[1].'-'.$dateTimeKey[2].'-'.$dateTimeKey[3].':'.$dateTimeKey[4].'-'.$eventTitleKey;
    }
    $alreadyOnCalendar[$calendarEventKey] = array(
      'startDate'=>$event->getStart()->getDate(),
      'startTime'=>$event->getStart()->getDateTime(),
      'endDate'=>$event->getEnd()->getDate(),
      'endTime'=>$event->getEnd()->getDateTime(),
      'eventSummary'=>$event->getSummary(),
      'eventDescription'=>$event->getDescription(),
      'fullEvent'=>$event
    );
  }

  // USE REFERENCE CALENDAR FOR INPUT/COPY
  // Query for upcoming events on the reference/input calendar
  $inputResults = $calendar->events->listEvents($referenceInputCalendarId, $futureParams);

  $refCalendarInput = array();
  foreach ($inputResults->getItems() as $event) {
    $allDay = false;
    $eventTitleKey = $event->getSummary();
    if (is_null($event->getStart()->getDateTime())) {
      print $event->getSummary() . " is all day event.\n";
      $allDay = true;
      $dateTimeKey = preg_split('/(-|:|T)/', $event->getStart()->getDate());
      $eventParams = new Google_Service_Calendar_Event(array(
        'summary' => $eventTitleKey,
        'description' => $event->getDescription(),
        'start' => array(
          'date' => $event->getStart()->getDate(),
          'timeZone' => 'America/Chicago',
        ),
        'end' => array(
          'date' => $event->getEnd()->getDate(),
          'timeZone' => 'America/Chicago',
        ),
      ));
      $eventKey = $dateTimeKey[1].'-'.$dateTimeKey[2].'-'.$eventTitleKey;
    } else {
      print $event->getSummary() . " is a timed event.\n";
      $dateTimeKey = preg_split('/(-|:|T)/', $event->getStart()->getDateTime());
      $eventParams = new Google_Service_Calendar_Event(array(
        'summary' => $eventTitleKey,
        'description' => $event->getDescription(),
        'start' => array(
          'dateTime' => $event->getStart()->getDateTime(),
          'timeZone' => 'America/Chicago',
        ),
        'end' => array(
          'dateTime' => $event->getEnd()->getDateTime(),
          'timeZone' => 'America/Chicago',
        ),
      ));
      $eventKey = $dateTimeKey[1].'-'.$dateTimeKey[2].'-'.$dateTimeKey[3].':'.$dateTimeKey[4].'-'.$eventTitleKey;
    }
    
    if (array_key_exists($eventKey, $alreadyOnCalendar)) {
      print "$eventTitleKey is already on the calendar, skipping...\n";
    } else {
      print "$eventTitleKey needs to be added to the calendar...\n";
      insertEvent($client, $eventParams, $readWriteCalendarId, $calendar);
    }
  }

  // USE FLAT FILE FOR INPUT
  date_default_timezone_set('America/Chicago');
  $inputString = file_get_contents(__DIR__ . '/../data/demoInputCalendarData.json');
  $inputParsed = json_decode($inputString,true);
  $itemCount = $inputParsed["totalRecords"];
  $itemData = $inputParsed["data"];

  foreach ($itemData as $eventItem) {
    $startTimestamp = strtotime($eventItem[3]);
    $startTimestamp = $startTimestamp + ($eventItem[4] * 60);
    $endTimestamp = strtotime($eventItem[5]);
    $endTimestamp = $endTimestamp + ($eventItem[6] * 60);

    if (is_null($eventItem[7])) {
      $summaryText = $eventItem[0];
    } else {
      $summaryText = $eventItem[7];
    }

    $eventKey = date("m-d-H:i", $startTimestamp) . '-' . $summaryText;
    if (array_key_exists($eventKey, $alreadyOnCalendar)) {
      print "$summaryText is already on the calendar, skipping/updating...\n";
      $event = $alreadyOnCalendar[$eventKey]['fullEvent'];
      if ($event->getDescription() != $eventItem[0]) {
        $event->setDescription($eventItem[0]);
        $updatedEvent = $calendar->events->update($readWriteCalendarId, $event->getId(), $event);
      }
    } else {
      print "$summaryText needs to be added to the calendar, creating...\n";
      $eventParams = new Google_Service_Calendar_Event(array(
        'summary' => $summaryText,
        'description' => $eventItem[0],
        'start' => array(
          'dateTime' => date("Y-m-d\TH:i:sP",$startTimestamp),
          'timeZone' => 'America/Chicago',
        ),
        'end' => array(
          'dateTime' => date("Y-m-d\TH:i:sP",$endTimestamp),
          'timeZone' => 'America/Chicago',
        ),
      ));
      insertEvent($client, $eventParams, $readWriteCalendarId, $calendar);
    }
  }


  function insertEvent(Google_Client $client, Google_Service_Calendar_Event $event, string $readWriteCalendarId, Google_Service_Calendar $calendar=null) {
    if(is_null($calendar)) {
      $calendar = new Google_Service_Calendar($client);
    }
    $event = $calendar->events->insert($readWriteCalendarId, $event);
    return $event;
  }

?>