<?php

$cacheDocumentPathFile = '';

//-------------------------------------------------------------------------------------

function checkForDocumentCache($RequstIRI, &$document) {
  global $cacheDocumentPathFile;

  $cacheDocumentPathFile = '';

  $file = $RequstIRI;
  //remove fragment identifier part
  $charPos = mb_stripos($file, '#');
  if ($charPos !==  false) {
    $file = substr($file, 0, $charPos);
  }
  //remove request query part  
  $charPos = mb_stripos($file, '?');
  if ($charPos !==  false) {
    $file = substr($file, 0, $charPos);
  }
  //remove slash at start
  if (mb_substr($file, 0, 1) == '/') $file = mb_substr($file, 1);


  if ($file == '') $file = "_-_index_-_"; // Can be empty when main domain is requested

  $file = str_replace('/', ';~', $file); // replace all slashes


  $file = $file . '.pgc'; // add 'page cache' extention

  $path = D2D_DOCUMENT_ROOT . '/' . D2D_CACHE_FOLDER . '/documents/';
  // Check if cache directory exists, and create it if not.
  if (!file_exists($path)) {
    if (!mkdir($path, 0750, true)) returnErrorPage(500, 'Could not create cache folder');
  }
  $cacheDocumentPathFile = $path . $file;

  // Check if cache file exists
  if (file_exists($cacheDocumentPathFile)) {
    $docuData = file_get_contents($cacheDocumentPathFile);
    // Filter out cache meta data
    $sepp = mb_strpos($docuData, '||~||');
    $meta = json_decode(mb_substr($docuData, 0, $sepp));
    // Check expiration
    if (D2D_CACHE_EXPIRE !== false) {
      if (time() - $meta->timestamp >= D2D_CACHE_EXPIRE) return false;
    }
    // Filter out the document
    $document = mb_substr($docuData, $sepp + 5);
    return true;
  }
}


//-------------------------------------------------------------------------------------


function writeDocumentCache($document) {
  global $cacheDocumentPathFile;

  if ($cacheDocumentPathFile == '') return false;

  $docmeta = '{"timestamp": ' . time() . '}||~||';
  $docmeta .= $document;

  // Write file. Old file will be overwritten.
  file_put_contents($cacheDocumentPathFile, $docmeta);

  return true;
}

//-------------------------------------------------------------------------------------

?>
