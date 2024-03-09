<?php
  if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
  }

  require_once __DIR__ . '/../conf/settings.php';

  $client = new Google_Client();
  $client->useApplicationDefaultCredentials();
  $client->addScope(Google_Service_Drive::DRIVE);
  $client->addScope(Google_Service_Docs::DOCUMENTS);
  $drive = new Google_Service_Drive($client);
  $docs = new Google_Service_Docs($client);

  $shareDriveFolderName = "Docs-Demo";
  $docFileName = "Existing Doc Demo";
  $copyDocFileName = "Parts Mailing Demo";


  print "\n\nReady. Press any key to find a Document by name.\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  // GRAB THE PARENT ID OF THE FOLDER IN QUESTION
  $optFolderParams = array(
    'pageSize' => 10,
    'q' => "mimeType = 'application/vnd.google-apps.folder' and name = '$shareDriveFolderName' and trashed = false",
    'fields' => 'files(id, name)',
    'driveId' => $shareDriveId,
    'corpora' => 'drive',
    'supportsAllDrives' => true,
    'includeItemsFromAllDrives' => true
  );

  $results = $drive->files->listFiles($optFolderParams);
  $folderParam = '';
  if (count($results->getFiles()) > 0) {
    foreach ($results->getFiles() as $folder){
      $folderParam .= " and '" . $folder->getId() . "' in parents";
      $shareDriveFolderId = $folder->getId();
    }
  }

  // NOW SEARCH FOR FILES
  $optFolderParams = array(
    'pageSize' => 10,
    'q' => "mimeType = 'application/vnd.google-apps.document' $folderParam and name = '$docFileName' and trashed = false",
    'fields' => 'files(id, name)',
    'driveId' => $shareDriveId,
    'corpora' => 'drive',
    'supportsAllDrives' => true,
    'includeItemsFromAllDrives' => true
  );

  $results = $drive->files->listFiles($optFolderParams);
  $files = $results->getFiles();
  $docFileId = $files[0]->getId();
  
  print "Found " . $files[0]->getName() . " with Id " . $files[0]->getId() . "...\n";


  print "\n\nDone. Press any key to insert some meat to this file.\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  $requests = array();
  $requests[] = new Google_Service_Docs_Request(array(
    'insertText' => array(
      'text' => "Bacon ipsum dolor amet meatloaf turducken boudin cow sausage pork loin. Beef ham ground round doner meatball, shank short ribs bacon pig drumstick flank landjaeger. Meatloaf fatback shank, chuck cow alcatra meatball flank leberkas shoulder pancetta. Spare ribs picanha ball tip, leberkas prosciutto burgdoggen kevin turkey hamburger. Biltong hamburger ribeye, burgdoggen beef ribs chislic porchetta drumstick chuck cupim cow venison tongue boudin t-bone.\n\nPicanha pork filet mignon, alcatra cow meatball kevin tenderloin t-bone pork loin rump. Strip steak spare ribs hamburger, kevin tenderloin turducken pork chop. T-bone ham capicola, drumstick pork rump frankfurter tenderloin salami ribeye cupim corned beef tongue. Alcatra ground round pancetta, short ribs beef frankfurter bacon brisket prosciutto sirloin pork chop chuck capicola venison turducken.\n\nBacon tri-tip kevin beef ribs t-bone. Pancetta tongue shank burgdoggen frankfurter sirloin meatloaf brisket jowl short loin. Andouille beef ribs sirloin pastrami biltong, alcatra salami boudin turducken pig pork kevin t-bone. Biltong andouille ground round turducken burgdoggen bresaola meatball cow.",
      'location' => array(
        'index' => 1,
      ),
    ),
  ));

  $batchUpdateRequest = new Google_Service_Docs_BatchUpdateDocumentRequest(array(
    'requests' => $requests
  ));

  $response = $docs->documents->batchUpdate($docFileId, $batchUpdateRequest);


  print "\n\nDone. Press any key to create a file from template...\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  // NOW SEARCH FOR FILES
  $optFolderParams = array(
    'pageSize' => 10,
    'q' => "mimeType = 'application/vnd.google-apps.document' $folderParam and name = '$copyDocFileName' and trashed = false",
    'fields' => 'files(id, name)',
    'driveId' => $shareDriveId,
    'corpora' => 'drive',
    'supportsAllDrives' => true,
    'includeItemsFromAllDrives' => true
  );

  $results = $drive->files->listFiles($optFolderParams);
  $files = $results->getFiles();
  $copyDocFileId = $files[0]->getId();

  $personName = 'Ricky';
  $inputType = 'Cog';
  $rateData = '2345';
  // Creating a Shared Drive file...
  $fileMetadata = new Google_Service_Drive_DriveFile(array(
    'name' => "$copyDocFileName - $personName",
    'mimeType' => 'application/vnd.google-apps.document',
    'driveId' => $shareDriveId,
    'parents' => $shareDriveFolderId
  ));
  $copiedShareDriveFile = $drive->files->copy($copyDocFileId, $fileMetadata, array('fields' => 'id','supportsAllDrives' => true));
  $copiedShareDriveFileId = $copiedShareDriveFile->id;


  print "\n\nDone. Press any key to 'mail merge' data to the Doc.\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  $requests = array();
  $requests[] = new Google_Service_Docs_Request(array(
    'replaceAllText' => array(
      'containsText' => array(
        'text' => "{{recipientName}}",
        'matchCase' => false
      ),
      'replaceText' => "$personName"
    ),
  ));
  $requests[] = new Google_Service_Docs_Request(array(
    'replaceAllText' => array(
      'containsText' => array(
        'text' => "{{inputType}}",
        'matchCase' => false
      ),
      'replaceText' => "$inputType"
    ),
  ));
  $requests[] = new Google_Service_Docs_Request(array(
    'replaceAllText' => array(
      'containsText' => array(
        'text' => "{{rateData}}",
        'matchCase' => false
      ),
      'replaceText' => "$rateData"
    ),
  ));
            
  $batchUpdateRequest = new Google_Service_Docs_BatchUpdateDocumentRequest(array(
    'requests' => $requests
  ));

  $response = $docs->documents->batchUpdate($copiedShareDriveFileId, $batchUpdateRequest);


  print "\n\nDone. Press any key to export Documents to PDF and Word.\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  $mailingDownloadFileName = 'rickyDoc.pdf';
  $downloadContentResponse = $drive->files->export($copiedShareDriveFileId, 'application/pdf', array('alt' => 'media'));
  file_put_contents(__DIR__ . '/../data/' . $mailingDownloadFileName, $downloadContentResponse->getBody()->getContents());

  $baconDownloadFileName = 'meatyDoc.docx';
  $downloadContentResponse = $drive->files->export($docFileId, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', array('alt' => 'media'));
  file_put_contents(__DIR__ . '/../data/' . $baconDownloadFileName, $downloadContentResponse->getBody()->getContents());


  print "\n\nDone. Press any key to clean up this demo.\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);

  // Cleaning up the file we created...
  $drive->files->delete($copiedShareDriveFileId, array('supportsAllDrives' => true));

?>