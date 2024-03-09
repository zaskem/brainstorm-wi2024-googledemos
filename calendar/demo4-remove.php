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


  // Obtain upcoming events only
  $futureParams = array(
    'orderBy' => 'startTime',
    'singleEvents' => true,
    'timeMin' => date('c'),
    'timeZone' => 'America/Chicago',
  );

  // Query target calendar items; remove each
  $results = $calendar->events->listEvents($readWriteCalendarId, $futureParams);

  foreach ($results->getItems() as $event) {
    $eventId = $event->getId();
    $results = $calendar->events->delete($readWriteCalendarId, $eventId);
  }

?>