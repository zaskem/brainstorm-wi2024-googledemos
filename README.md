# BrainStorm Wisconsin Dells 2024 Google Demos
Google API Session Demos for BrainStorm Wisconsin Dells 2024

Written in PHP, the repo includes basic demos and input data to interact with:
* Google Calendar;
* Google Drive;
* Google Sheets; and
* Google Docs.

## Basic Instructions for Use
A set of destination Google resources (input/output calendars, Shared Drive, and starting point documents) need to be created separately. For basic success, the following Google resources should be available:
* Destination (empty) Google Calendar;
* Shared Drive with the following folder structure:
  * Shared Drive
    * "Docs-Demo" folder, two Google Docs stubbed out within
    * "Drive-Search" folder, any number of files/file types within
    * "Sheets-Demo" folder, one Google Sheet with data within 
* Copies of `calendar-events.php`, `calendar-status.php`, and `settings.php` created and placed in the `/conf` directory of this repository (examples provided). The contents of `settings.php` must be modified accoding to the running environment before using these demo scripts

Loosely following the session slide deck, one should be able to run these demos after appropriate developer console actions have been completed (enabled APIs, service accounts, etc.).

### Requirements
* PHP 8.x, cURL-enabled
* PHP Google Client Library
* JSON-based service account credentials (key)

### Disclaimer
This repo and its code is provided as-is for illustrative/exploratory purposes, and was used in a functional live demo on March 10, 2024. No guarantee this code will age well as Google's services change with time.
