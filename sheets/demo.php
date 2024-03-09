<?php
  if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
  }

  require_once __DIR__ . '/../conf/settings.php';

  $client = new Google_Client();
  $client->useApplicationDefaultCredentials();
  $client->addScope(Google_Service_Drive::DRIVE);
  $client->addScope(Google_Service_Sheets::SPREADSHEETS);
  $drive = new Google_Service_Drive($client);
  $sheets = new Google_Service_Sheets($client);

  $shareDriveFolderName = "Sheets-Demo";
  $sheetFileName = "Existing Sheet Demo";
  $newDemoSheetName = "New Sheet Demo";


  print "\n\nReady. Press any key to find a Sheet by name.\n\n";
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
    'q' => "mimeType = 'application/vnd.google-apps.spreadsheet' $folderParam and name = '$sheetFileName' and trashed = false",
    'fields' => 'files(id, name)',
    'driveId' => $shareDriveId,
    'corpora' => 'drive',
    'supportsAllDrives' => true,
    'includeItemsFromAllDrives' => true
  );

  $results = $drive->files->listFiles($optFolderParams);
  $files = $results->getFiles();
  $sheetFileId = $files[0]->getId();
  
  print "Found " . $files[0]->getName() . " with Id " . $files[0]->getId() . "...\n";


  print "\n\nDone. Press any key to read data from the Sheet.\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  $sheetDocument = $sheets->spreadsheets->get($sheetFileId);
  $sheetInstances = $sheetDocument->getSheets();
  $sheetData = $sheets->spreadsheets_values->get($sheetFileId, $sheetInstances[0]->properties->title)['values'];
  print_r($sheetData);


  print "\n\nDone. Press any key to clear data from the Sheet.\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  // CLEAR THE EXISTING DATASET
  $clear = new Google_Service_Sheets_ClearValuesRequest();
  $sheets->spreadsheets_values->clear($sheetFileId, "Input Data", $clear);


  print "\n\nDone. Press any key to write data to a new sheet/tab.\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  $newSheetNameAndRange = 'Destination Data';
  $updateRequests = [
    new Google_Service_Sheets_Request([
      'addSheet' => [
        'properties' => [
          'title' => $newSheetNameAndRange,
          'index' => (count($sheetInstances) + 1)
        ]
      ]
    ])
  ];

  // RUN THE UPDATE REQUEST
  $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
    'requests' => $updateRequests
  ]);
  $sheets->spreadsheets->batchUpdate($sheetFileId, $batchUpdateRequest);

  $replacementData = new Google_Service_Sheets_ValueRange(['range' => $newSheetNameAndRange,'majorDimension' => 'ROWS','values' => $sheetData]);

  // POST THE REPLACEMENT DATASET
  $sheets->spreadsheets_values->update($sheetFileId, $newSheetNameAndRange, $replacementData,['valueInputOption' => 'USER_ENTERED']);


  print "\n\nDone. Press any key to create a new Sheet file.\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  // Creating a Shared Drive file...
  $fileMetadata = new Google_Service_Drive_DriveFile(array(
    'name' => "$newDemoSheetName",
    'mimeType' => 'application/vnd.google-apps.spreadsheet',
    'driveId' => $shareDriveId,
    'parents' => array($shareDriveFolderId)
  ));
  $newShareDriveFile = $drive->files->create($fileMetadata, array('fields' => 'id','supportsAllDrives' => true));
  $newShareDriveFileId = $newShareDriveFile->id;
  
  $sheetNameAndRange = 'Result Data';
  // DO AN UPDATE ON THE DEFAULT SHEET NAME
  $updateRequests = [
    // Change the spreadsheet's sheet name
    new Google_Service_Sheets_Request([
      'updateSheetProperties' => [
        'properties' => [
          'title' => $sheetNameAndRange,
          'index' => 0
        ],
        'fields' => 'title'
      ]   
    ])
  ];

  // RUN THE UPDATE REQUEST
  $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
    'requests' => $updateRequests
  ]);
  $sheets->spreadsheets->batchUpdate($newShareDriveFileId, $batchUpdateRequest);


  print "\n\nDone. Press any key to update '$newDemoSheetName' with test data.\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  // Populating 'New Sheet Demo' with test data...
  $sheetDocument = $sheets->spreadsheets->get($newShareDriveFileId);
  // Obtain a dataset from another document (a local CSV on the filesystem):
  $localDataFile = __DIR__ . '/../data/csvData.csv';
  $importCSV = array();
  $handle = fopen($localDataFile, 'r');
  while ($row = fgetcsv($handle, null, ',')) {
    $importCSV[] = $row;
  }
  $replacementData = new Google_Service_Sheets_ValueRange(['range' => $sheetNameAndRange,'majorDimension' => 'ROWS','values' => $importCSV]);
  $sheets->spreadsheets_values->update($newShareDriveFileId, $sheetNameAndRange, $replacementData,['valueInputOption' => 'USER_ENTERED']);


  print "\n\nDone. Press any key to export this sheet to Excel.\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  $sheetDownloadFileName = "$newDemoSheetName.xlsx";
  $downloadContentResponse = $drive->files->export($newShareDriveFileId, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', array('alt' => 'media'));
  file_put_contents(__DIR__ . '/../data/' . $sheetDownloadFileName, $downloadContentResponse->getBody()->getContents());


  print "\n\nDone. Press any key to clean up this demo. Starting with removing extra Sheet/tab in '$sheetFileName'.\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  // Last tab in the is our target, after refreshing the data
  $sheetDocument = $sheets->spreadsheets->get($sheetFileId);
  $sheetInstances = $sheetDocument->getSheets();
  $sheetSheet = end($sheetInstances);
  $sheetSheetId = $sheetSheet['properties']['sheetId'];

  $updateRequests = [
    new Google_Service_Sheets_Request([
      'deleteSheet' => [
        'sheetId' => "$sheetSheetId"
      ]
    ])
  ];

  // RUN THE UPDATE REQUEST
  $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
    'requests' => $updateRequests
  ]);
  $sheets->spreadsheets->batchUpdate($sheetFileId, $batchUpdateRequest);


  print "\n\nDone. Press any key to remove file '$newDemoSheetName'.\n\n";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  fclose($handle);


  $drive->files->delete($newShareDriveFileId, array('supportsAllDrives' => true));

?>