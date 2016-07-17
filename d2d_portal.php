<?php

//$a = array(1, 2, 3);
//$b = array(4, 5, 6);
//$c = array(91, 92, 93);
//$a['roles'] = &$c;
//$b[1] = &$c;
//$x = 'test2'; 
//$b[1] = &$x;
//var_dump($a); echo "\n\n\n";
//var_dump($b); echo "\n\n\n";
//echo $a['roles']['SortBy']['object'];
//echo "\n\n\n";
//var_dump($a['roles']['SortBy']['object']);
//echo is_numeric(trim(' 0x7 '));
//exit();


//ob_start();


require('d2d_sys_init.inc.php');


date_default_timezone_set(D2D_TIME_ZONE);

if (mb_stripos($_SERVER['HTTP_ACCEPT'], 'rdf') == false) {
   if (D2D_DEBUG) echo "The time is: " . date("h:i:sa") . "<br />\n";
   if (D2D_DEBUG) echo "SCRIPT START AT: " . substr(microtime(true) . '          ', 0, 10) . "\n<br /><br /><br />\n\n";
}

require_once('d2d_errorhandling.inc.php');
require_once('d2d_dereferencefunctions.inc.php');
require_once('d2d_cachefunctions.inc.php');



// RECREATE REQUEST URI. --> CURRENTLY DISREGARDS (USED) PROTOCOL AND USES HTTP AS STANDARD FOR URI.
$webURI = "http://" . D2D_HTTP_HOST . D2D_REQUEST_URI;
$locURI = D2D_DOCUMENT_ROOT . D2D_REQUEST_URI;


// If the accept header demands RDF, give it!
if (mb_stripos($_SERVER['HTTP_ACCEPT'], 'rdf') !== false) { // NB! Needs strict compare because a match at position 0 evaluates to false for a non-strict compare.
  $rdfData = createResourceDescription($webURI, $locURI, 404);
  header('Content-Type: application/rdf+xml'); // ; charset=utf-8
  echo $rdfData;
  //while (ob_get_level() > 0) { // Flush all existing output buffers
  //  ob_end_flush();
  //}
  exit();
}


// Check for a cached version of this resource
$documentDomStr = '';
if (!D2D_DEBUG && D2D_CACHE_ENABLED) {
  if (checkForDocumentCache(D2D_REQUEST_URI, $documentDomStr)) {
    echo $documentDomStr;
    finishAndExit();
  }
}


require_once('EasyRdf/lib/EasyRdf.php');
//require_once('d2d_microtriplestore.inc.php');
require_once('d2d_parsefunctions.inc.php');
require_once('d2d_templatefunctions.inc.php');

// SET TIME ZONE ACCORDING TO SETTING
date_default_timezone_set($timeZone);

// INITIALIZE THE MICRO TRIPLE STORE
//initializeMTS();

// Set the d2d namespace for EasyRdf
EasyRdf_Namespace::set('d2d', D2D_NS);

//DEBUG
//$webURI = D2D_NS; // . 'Document';
//echo $webURI . "<br />\n <br />\n <br />\n ";
//$graph = EasyRdf_Graph::newAndLoad($webURI);
//var_dump($graph) . "<br />\n <br />\n <br />\n ";
//echo $graph . "<br />\n <br />\n <br />\n ";
//exit();


// Initialise definition library;
$definitionLibrary = array('byDefinition' => array(), 'byClass' => array());


// Load the main graph object
$graph = new EasyRdf_Graph();
$graph->parse(createResourceDescription($webURI, $locURI, 404), null, $webURI);


// Get the document object and the main article object. Because the ->resource method will create the requested resource if it does not exists, we first get the main article object and check if it is not null. If the Article is null, we try removing a trailing / from $WebURI.
$mainArticle = $graph->getResource($webURI, 'd2d:hasArticle');
if ($mainArticle === null) { // This section is meant ONLY to correct a wrong 'about' URI within the document that SHOULD have a trailing slash; NOT to serve the same document for sub-dirictory paths with and without trailing slash. 
  if (mb_substr($webURI, -1) == '/') {
    $mainArticle = $graph->getResource(mb_substr($webURI, 0, -1), 'd2d:hasArticle');
  }
  if ($mainArticle === null) {
    returnErrorPage(422, '<i>On resource:</i><br />' . $webURI . '<br /><br /><i>The follwing property could not be found:</i><br />d2d:hasArticle');
  } else {
    $doc = $graph->resource(mb_substr($webURI, 0, -1));
  }
} else {
  $doc = $graph->resource($webURI);
}
Dereferences::$fetchedUris[$webURI] = true; // Register the document URI as a dereferenced URI to prevent double loading of triples.


// Get the document rendering definition and load it's template(s) to the template library
//$renderDef = persistentGet($doc, null, 'd2d:renderedBy', 'resource');
$renderDef = persistentGet($doc, null, 'd2d:renderedBy', 'resource', null, 0);
if ($renderDef !== null) {
  $template = (string) persistentGet($renderDef, null, 'd2d:hasTemplate');
  if ($template !== null) {
    loadTemplate($template, true);
  }
}


// Get the specified document template from the template library
$documentDomStr = Templates::$arr['::doc::'];
if ($documentDomStr !== null) {
  $docFieldCount = preg_match_all("/(<d2d:Content.*d2d:Content.*>|<d2d:Content.*\/>)/sU", $documentDomStr, $matches);
  if ($docFieldCount != 1) {
    if ($docFieldCount == 0) { 
      $docFieldCount = 'none';
    }
    returnErrorPage(409, '<i>Document template must have exactly one content field (' . $docFieldCount . ' detected) for render definition:</i><br />' . $renderDef->getUri());
  }
} else {
  $documentDomStr = '<!DOCTYPE html><html><head><title></title></head><body><d2d:Content/></body></html>';
  $templateObj = null;
}

// Insert general document data such as title and meta tags
//-> Title tag
$titleTagStr = "<title>" . persistentGet($doc, null, 'd2d:title', 'literal') . "</title>";
$headTagStr = ""; 
//-> Meta tags
$headParams = persistentAll($doc, null, 'd2d:charset', 'literal', null, 0);
foreach ($headParams as $headParam) {
  $headTagStr .= '<meta charset="' . $headParam . '" />';
}
$headParams = persistentAll($doc, null, 'd2d:refresh', 'literal', null, 0);
foreach ($headParams as $headParam) {
  $headTagStr .= '<meta http-equiv="refresh" content="' . $headParam . '" />';
}
$headParams = persistentAll($doc, null, 'd2d:contentType', 'literal', null, 0);
foreach ($headParams as $headParam) {
  $headTagStr .= '<meta http-equiv="content-type" content="' . $headParam . '" />';
}
$headParams = persistentAll($doc, null, 'd2d:defaultStyle', 'literal', null, 0);
foreach ($headParams as $headParam) {
  $headTagStr .= '<meta http-equiv="default-style" content="' . $headParam . '" />';
}
$headParams = persistentAll($doc, null, 'd2d:compatible', 'literal', null, 0);
foreach ($headParams as $headParam) {
  $headTagStr .= '<meta http-equiv="X-UA-Compatible" content="' . $headParam . '" />';
}
$headParams = persistentAll($doc, null, 'd2d:author', 'literal', null, 0);
foreach ($headParams as $headParam) {
  $headTagStr .= '<meta name="author" content="' . $headParam . '" />';
}
$headParams = persistentAll($doc, null, 'd2d:robots', 'literal', null, 0);
foreach ($headParams as $headParam) {
  $headTagStr .= '<meta name="robots" content="' . $headParam . '" />';
}
$headParams = persistentAll($doc, null, 'd2d:revisitAfter', 'literal', null, 0);
foreach ($headParams as $headParam) {
  $headTagStr .= '<meta name="revisit-after" content="' . $headParam . '" />';
}
$headParams = persistentAll($doc, null, 'd2d:generator', 'literal', null, 0);
foreach ($headParams as $headParam) {
  $headTagStr .= '<meta name="generator" content="' . $headParam . '" />';
}
$headParams = persistentAll($doc, null, 'd2d:keywords', 'literal', null, 0);
foreach ($headParams as $headParam) {
  $headTagStr .= '<meta name="keywords" content="' . $headParam . '" />';
}
$headParams = persistentAll($doc, null, 'd2d:description', 'literal', null, 0);
foreach ($headParams as $headParam) {
  $headTagStr .= '<meta name="description" content="' . $headParam . '" />';
}
//-> Style sheets
$headParams = persistentAll($doc, null, 'd2d:hasStyle', 'literal', null, 0);
foreach ($headParams as $headParam) {
  $headTagStr .= '<link rel="stylesheet" type="text/css" href="' . $headParam . '" />';
}
//-> Scripts
$headParams = persistentAll($doc, null, 'd2d:hasScript', 'literal', null, 0);
foreach ($headParams as $headParam) {
  $headTagStr .= '<script src="' . $headParam . '">//script</script>';
}
//-> Meta tags OLD
//$headTagStr = "";
//$metaTags = persistentAll($doc, null, 'd2d:meta', 'resource', null, 0);
//foreach ($metaTags as $metaTag) {
//  $headTagStr .= "<meta";
//  $metaParamNames = $metaTag->properties();
//  foreach ($metaParamNames as $metaParamName) {
//    $metaParamValue = $metaTag->get($metaParamName, 'literal', null);
//    if ($metaParamValue !== null) $headTagStr .= " $metaParamName=\"$metaParamValue\"";
//  }
//  $headTagStr .= " />";
//}
$documentDomStr = preg_replace("/(<title.*<\/title[^>]*>|<title[^>]*\/>)/sUi", $headTagStr . $titleTagStr, $documentDomStr, 1);


// Get any article definition(s)
$articleDefs = persistentAll($doc, null, 'd2d:prefArticleDef', 'resource', null, 0);
foreach ($articleDefs as $articleDef) {
  registerArticleDef($articleDef, $definitionLibrary);
}
if ($articleDefs) {
  $articleDef = reset($articleDefs);
} else {
  $articleDef = null;
}

// Get any article rendering definition(s)
$renderDefs = persistentAll($doc, null, 'd2d:prefRenderDef', 'resource', null, 0);
foreach ($renderDefs as $renderDef) {
  registerRenderDef($renderDef, $definitionLibrary);
}
if ($renderDefs) {
  $renderDef = reset($renderDefs);
} else {
  $renderDef = null;
}

$articleDomStr = parseArticle($mainArticle, $articleDef, $renderDef, $definitionLibrary);


$documentDomStr = preg_replace("/[<\[]d2d:Content.*[<\[]\/d2d:Content[^>\]]*[>\]]|[<\[]d2d:Content[^>\]]*\/[>\]]/sUi", $articleDomStr, $documentDomStr);

// Remove any remaining d2d tags
//$documentDomStr = preg_replace("/[<\[]d2d:.*[<\[]\/d2d:[^>\]]*[>\]]|[<\[]d2d:[^>\]]*\/[>\]]/sUi", '', $documentDomStr); // NB: Pattern used to be: "/[<\[][\/]?d2d:[^>\]]*[\/]?[>\]]/sUi" but that removes individual opening, closing and self-closing tags, while tag *pairs* should be removed including any possible (sample) content inbetween.
$documentDomStr = preg_replace("/[<\[][\/]?d2d:[^>\]]*[\/]?[>\]]/sUi", '', $documentDomStr); // NB: Diabled new version (line above) and reversed to old version because everything between d2d:template tags is removed, which constitutes major parts of the document.


// Remove any remaining d2d namespace declarations
$documentDomStr = preg_replace('/[ ]?xmlns:.*' . str_replace('/', '\/', D2D_NS) . '"/sUi', '', $documentDomStr);


//-------------------------------- \/ OUTPUT SECTION \/ -------------------------------

// Write debug info if enabled
if (D2D_DEBUG) {
  echo "SCRIPT DONE AT: " . substr(microtime(true) . '          ', 0, 10) . "<br />\n";
  echo "The time is: " . date("h:i:sa") . "<br />\n";
}

// Send warnings if enabled
if (D2D_SHOW_WARNINGS) $documentDomStr .= Warnings::dumpWarnings(D2D_SHOW_WARNINGS);

// Write a cache file for this resource if caching is enabled
if (!D2D_DEBUG && D2D_CACHE_ENABLED) writeDocumentCache($documentDomStr);

// Send the document to the client
echo $documentDomStr;

//-------------------------------- /\ OUTPUT SECTION /\ -------------------------------

finishAndExit();


//-------------------------------------------------------------------------------------

function finishAndExit() {

  //while (ob_get_level() > 0) { // Flush all existing output buffers
  //  ob_end_flush();
  //}

  exit();
}

//-------------------------------------------------------------------------------------

?>
