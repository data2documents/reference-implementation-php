<?php

/*===============================================================*\
   D2D SYSTEM CONFIG FILE
   --------------------------------------------------------------
   This file contains default settings that apply for all sites 
   on this server, if not overruled in a per-site config file.
   A per-site config file can be found or created as 
   'd2d_init.inc.php' in the root directory of each site.
\*===============================================================*/


// INITIALISE CONSTANT VALUE ARRAY
$const = array();


// SYSTEM CONSTANTS
$const['D2D_DEBUG'] = false;

$const['D2D_NS'] = 'http://rdfns.org/d2d/';

if ($_GET["D2D_DOCUMENT_ROOT"]) {;
  $const['D2D_DOCUMENT_ROOT'] = $_GET["D2D_DOCUMENT_ROOT"];
} else {
  $const['D2D_DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'];
}
if ($_GET["D2D_HTTP_HOST"]) {
  $const['D2D_HTTP_HOST'] = $_GET["D2D_HTTP_HOST"];
} else {
  $const['D2D_HTTP_HOST'] = $_SERVER['HTTP_HOST'];
}
if ($_GET["D2D_REQUEST_URI"]) {
  $const['D2D_REQUEST_URI'] = $_GET["D2D_REQUEST_URI"];
} else {
  $const['D2D_REQUEST_URI'] = $_SERVER['REQUEST_URI'];
}


// SHOW D2D PROCESSING WARNINGS: CAN BE false, true (INCLUDES WARNINGS AS COMMENTS AT END OF DOCUMENT) OR 'html' (APPENDS WARNINGS TO END OF DOCUMENT)
$const['D2D_SHOW_WARNINGS'] = 'html';


// DEFAULT TIME ZONE 
$const['D2D_TIME_ZONE'] = 'Europe/Amsterdam';

// ACCEPTED FORMATS FOR DEREFERENCE REQUESTS MADE DURING D2D PROCESSING
$const['D2D_RDF_ACCEPT'] = 'application/rdf+xml, application/x-turtle, application/rdf+turtle, application/turtle, text/turtle; q=0.9, text/n3; q=0.9, text/rdf+n3; q=0.9, application/rss+xml; q=0.8, application/xhtml+xml; q=0.7, application/xml; q=0.7, text/xml; q=0.6, application/x-trig; q=0.5, application/octet-stream; q=0.4, text/html; q=0.3, text/plain; q=0.2, */*; q=0.1';

// DEFAULT LANGUAGE 
$const['D2D_DEFAULT_FIELD_LANGUAGE'] = null; // 'en'; // 'nl'; // null; // NB: Set to null to indicate no default language; this will result in no language filter hence possibly multiple literals for a specific property.

// DEFAULT FIELD TRIM
$const['D2D_DEFAULT_FIELD_TRIM'] = true;

// DEFAULT FIELD LIMIT
$const['D2D_DEFAULT_FIELD_LIMIT'] = '*';

// DEFAULT FIELD USE LABEL
$const['D2D_DEFAULT_FIELD_USELABEL'] = true;

// DEFAULT FIELD CREATE LINK
$const['D2D_DEFAULT_FIELD_CREATELINK'] = true;

// DEFAULT ARTICLE/SECTION SKIP ON ERROR
$const['D2D_DEFAULT_ARTSEC_SKIPONERROR'] = false;


// DEFAULT CACHE SETTINGS
$const['D2D_CACHE_ENABLED'] = false;
$const['D2D_CACHE_FOLDER'] = '.d2d_cache'; // Name of a (sub)folder in the site's document root.
$const['D2D_CACHE_EXPIRE'] = false; // Expiration time in seconds, or false for no expiration (infinity).


// DEFAULT MICRO TRIPLE STORE SETTINGS
$const['D2D_MTS_SOURCE'] = substr($const['D2D_DOCUMENT_ROOT'], strrpos($const['D2D_DOCUMENT_ROOT'], "/") + 1); // Set the name of the database to 'domain.tld'. 
$const['D2D_MTS_HOST'] = 'localhost';
$const['D2D_MTS_USER'] = $const['D2D_MTS_Source'];
$const['D2D_MTS_PASS'] = $const['D2D_MTS_Source'] . '_changeme';



// ------------------------------------------------------- PROCESS SITE SPECIFIC CONFIG FILE
// The site specific config file can override any constant by setting it in the $const array
@ include($const['D2D_DOCUMENT_ROOT'] . '/d2d_init.inc.php');
//------------------------------------------------------------------------------------------



// PROCESSING ON CONSTANTS VALUES, SUCH AS DETERMINATION OF DERIVATIVE CONSTANTS AND VALUE CHECKING
$const['D2D_NS_LEN'] = mb_strlen($const['D2D_NS']);
$const['D2D_CACHE_FOLDER'] = trim($const['D2D_CACHE_FOLDER'], "/");


// WRITE CONSTANTS
foreach($const as $key => $value) {
  define($key, $value); 
}

// CLEAN UP
unset($const);



?>
