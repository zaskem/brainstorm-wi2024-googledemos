<?php
  if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
  }

  require_once __DIR__ . '/../conf/settings.php';

  $client = new Google_Client();
  $client->useApplicationDefaultCredentials();
  $client->addScope(Google_Service_Drive::DRIVE);
  $drive = new Google_Service_Drive($client);

  $shareGroupName = 'groupname@domain.com';
  $shareDriveFolderName = "Demo Folder";
  $shareDriveFileName = "Astonished You Are";
  $emptyFileMetadata = new Google_Service_Drive_DriveFile();


  print "\n\nReady. Press any key to list all files available to this service account:\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  // Obtain List of all files available to this service account:
  $optParams = array(
    'pageSize' => 20,
    'supportsAllDrives' => true,
    'includeItemsFromAllDrives' => true,
    'corpora' => 'allDrives',
    'fields' => 'files(id, name)'
  );
  $results = $drive->files->listFiles($optParams);
  foreach ($results->getFiles() as $file) {
    printf("%s (%s)\n", $file->getName(), $file->getId());
  }


  print "\n\nDone. Press any key to create a Shared Drive folder.\n\n ";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  // Creating a Shared Drive folder...
  $file = new Google_Service_Drive_DriveFile(array(
    'name' => $shareDriveFolderName,
    'mimeType' => 'application/vnd.google-apps.folder',
    'driveId' => $shareDriveId,
    'parents' => array($shareDriveId)
  ));
  $shareDriveFolder = $drive->files->create($file, array('fields' => 'id','supportsAllDrives' => true));
  $shareDriveFolderId = $shareDriveFolder->id;


  print "\n\nDone. Press any key to create two Shared Drive files.\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  // Creating a Shared Drive file...
  $fileMetadata = new Google_Service_Drive_DriveFile(array(
    'name' => $shareDriveFileName,
    'mimeType' => 'application/vnd.google-apps.spreadsheet',
    'driveId' => $shareDriveId,
    'parents' => array($shareDriveFolderId)
  ));
  $newShareDriveFile = $drive->files->create($fileMetadata, array('fields' => 'id','supportsAllDrives' => true));
  $newShareDriveFileId = $newShareDriveFile->id;

  $fileMetadata = new Google_Service_Drive_DriveFile(array(
    'name' => $shareDriveFileName,
    'mimeType' => 'application/vnd.google-apps.document',
    'driveId' => $shareDriveId,
    'parents' => array($shareDriveFolderId)
  ));
  $newShareDriveFile = $drive->files->create($fileMetadata, array('fields' => 'id','supportsAllDrives' => true));
  $newShareDriveFileId = $newShareDriveFile->id;


  print "\n\nDone. Press any key to upload a CSV to the Shared Drive folder.\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  $csvInputFileName = 'csvData.csv';
  $csvData = file_get_contents(__DIR__ . '/../data/' . $csvInputFileName);

  // Generate Metadata
  $fileMetadata = new Google_Service_Drive_DriveFile(array(
    'name' => $csvInputFileName,
    'supportsAllDrives' => true,
    'driveId' => $shareDriveId,
    'parents' => array($shareDriveFolderId)
  ));
  // Create File
  $doneFile = $drive->files->create($fileMetadata, array(
    'data' => $csvData,
    'mimeType' => 'text/csv',
    'uploadType' => 'multipart',
    'supportsAllDrives' => true,
    'fields' => 'id'
  ));


  print "\n\nDone. Press any key to download a CSV from the Shared Drive folder.\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  $csvToDownloadId = $doneFile['id'];
  $csvDownloadFileName = 'csvDataFromDrive.csv';
  // Download and save any matching file not already present
  $downloadContentResponse = $drive->files->get($csvToDownloadId, array('alt' => 'media'));
  $downloadContent = $downloadContentResponse->getBody()->getContents();

  file_put_contents(__DIR__ . '/../data/' . $csvDownloadFileName, $downloadContent);


  print "\n\nDone. Press any key to move the uploaded CSV to the root of the Shared Drive.\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  $emptyFileMetadata = new Google_Service_Drive_DriveFile();
  // Retrieve the existing parents to remove
  $oldFile = $drive->files->get($csvToDownloadId, array('fields' => 'parents','supportsAllDrives' => true));
  $previousParents = join(',', $oldFile->parents);
  $oldFile = $drive->files->update($csvToDownloadId, $emptyFileMetadata, array(
    'addParents' => $shareDriveId,
    'removeParents' => $previousParents,
    'supportsAllDrives' => true,
    'fields' => 'id, parents'
  ));


  print "\n\nDone. Press any key to share the '$shareDriveFileName' document with '$shareGroupName'.\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  // Sharing a standard 'My Drive' file with '$shareGroupName'...
  $drive->getClient()->setUseBatch(true);
  $batch = $drive->createBatch();
  $groupPermission = new Google_Service_Drive_Permission(array(
    'type' => 'group',
    'role' => 'writer',
    'emailAddress' => $shareGroupName,
    'supportsAllDrives' => true
  ));
  $request = $drive->permissions->create($newShareDriveFileId, $groupPermission, array('fields' => 'id','supportsAllDrives' => true));
  $batch->add($request, 'group');
  $results = $batch->execute();
  $drive->getClient()->setUseBatch(false);


  print "\n\nDone. Press any key to list all files available to this service account:\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  // Obtain List of all files available to this service account:
  $optParams = array(
    'pageSize' => 20,
    'supportsAllDrives' => true,
    'includeItemsFromAllDrives' => true,
    'corpora' => 'allDrives',
    'fields' => 'files(id, name)'
  );
  $results = $drive->files->listFiles($optParams);
  foreach ($results->getFiles() as $file) {
    printf("%s (%s)\n", $file->getName(), $file->getId());
  }


  print "\n\nDone. Press any key to list files ONLY in 'Drive-Search' folder:\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  // GRAB THE PARENT ID OF THE FOLDER IN QUESTION
  $optFolderParams = array(
    'pageSize' => 10,
    'q' => "mimeType = 'application/vnd.google-apps.folder' and name = 'Drive-Search' and trashed = false",
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
    }
  }

  // NOW SEARCH FOR FILES
  $optFolderParams = array(
    'pageSize' => 10,
    'q' => "mimeType != 'application/vnd.google-apps.folder' $folderParam and trashed = false",
    'fields' => 'files(id, name)',
    'driveId' => $shareDriveId,
    'corpora' => 'drive',
    'supportsAllDrives' => true,
    'includeItemsFromAllDrives' => true
  );

  $results = $drive->files->listFiles($optFolderParams);
  foreach ($results->getFiles() as $file) {
    printf("%s (%s)\n", $file->getName(), $file->getId());
  }


  print "\n\nDone. Press any key to list ONLY Google-Specific files available to this service account:\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  // GRAB A LIST OF GOOGLE-SPECIFIC FILES
  $optFileParams = array(
    'pageSize' => 10,
    'q' => "(mimeType = 'application/vnd.google-apps.document' or mimeType = 'application/vnd.google-apps.spreadsheet') and trashed = false",
    'fields' => 'files(id, name)',
    'driveId' => $shareDriveId,
    'corpora' => 'drive',
    'supportsAllDrives' => true,
    'includeItemsFromAllDrives' => true
  );
  $results = $drive->files->listFiles($optFileParams);
  foreach ($results->getFiles() as $file) {
    printf("%s (%s)\n", $file->getName(), $file->getId());
  }


  print "\n\nDone. Press any key to list ONLY CSV files available to this service account:\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);
  print "\n";


  // IDENTIFY CSVs TO PROCESS
  $optFileParams = array(
    'pageSize' => 10,
    'q' => "mimeType = 'text/csv' and trashed = false",
    'fields' => 'files(id, name)',
    'driveId' => $shareDriveId,
    'corpora' => 'drive',
    'supportsAllDrives' => true,
    'includeItemsFromAllDrives' => true
  );
  $results = $drive->files->listFiles($optFileParams);
  foreach ($results->getFiles() as $file) {
    printf("%s (%s)\n", $file->getName(), $file->getId());
  }


  print "\n\nDone. Press any key to continue to start cleaning up our demo mess.\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  // Cleaning up files matching '$shareDriveFileName'...
  $optParams = array(
    'pageSize' => 20,
    'supportsAllDrives' => true,
    'includeItemsFromAllDrives' => true,
    'q' => 'name=\''.$shareDriveFileName.'\'',
    'fields' => 'files(id, name)'
  );
  $results = $drive->files->listFiles($optParams);
  foreach ($results->getFiles() as $file) {
    $drive->files->delete($file->getId(), array('supportsAllDrives' => true));
  }

  // Cleaning up files matching '$shareDriveFolderName'...
  $optParams = array(
    'pageSize' => 20,
    'supportsAllDrives' => true,
    'includeItemsFromAllDrives' => true,
    'q' => 'name=\''.$shareDriveFolderName.'\'',
    'fields' => 'files(id, name)'
  );
  $results = $drive->files->listFiles($optParams);
  foreach ($results->getFiles() as $file) {
    $drive->files->delete($file->getId(), array('supportsAllDrives' => true));
  }


  print "\n\nDone. Press any key to list all files still available to this service account:\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  // List of all files still available to this service account:
  $optParams = array(
    'pageSize' => 20,
    'q' => "mimeType = 'application/vnd.google-apps.folder' and name != 'Drive-Search' and name != 'Docs-Demo' and name != 'Sheets-Demo' and name != 'Merge-Demo'",
    'supportsAllDrives' => true,
    'includeItemsFromAllDrives' => true,
    'corpora' => 'allDrives',
    'fields' => 'files(id, name)'
  );
  $results = $drive->files->listFiles($optParams);
  foreach ($results->getFiles() as $file) {
    printf("%s (%s)\n", $file->getName(), $file->getId());
  }


  print "\n\nDone. Press any key to clean up rogue files still available to this service acount.\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  // Cleaning up other rogue files not excluded...
  $optParams = array(
    'pageSize' => 20,
    'q' => "mimeType = 'application/vnd.google-apps.folder' and name != 'Drive-Search' and name != 'Docs-Demo' and name != 'Sheets-Demo' and name != 'Merge-Demo'",
    'supportsAllDrives' => true,
    'includeItemsFromAllDrives' => true,
    'corpora' => 'allDrives',
    'fields' => 'files(id, name)'
  );
  $results = $drive->files->listFiles($optParams);
  foreach ($results->getFiles() as $file) {
    $drive->files->delete($file->getId(), array('supportsAllDrives' => true));
  }

?>
