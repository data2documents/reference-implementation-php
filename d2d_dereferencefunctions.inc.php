<?php

//-------------------------------------------------------------------------------------

function createResourceDescription($webURI, $locURI, $FailResponseCode) {

  if (!$webURI) returnErrorPage($FailResponseCode, '<i>The follwing resource could not be found:</i><br /> [empty string]');

  $fileURI = $locURI;
  //remove fragment identifier part
  $charPos = mb_stripos($fileURI, '#');
  if ($charPos !==  false) {
    $fileURI = substr($fileURI, 0, $charPos);
  }
  //remove request query part  
  $charPos = mb_stripos($fileURI, '?');
  if ($charPos !==  false) {
    $fileURI = substr($fileURI, 0, $charPos);
  }
 
  if ($fileURI == D2D_DOCUMENT_ROOT) $fileURI .= "/";

  if (!preg_match("/\.(gif|jpg|png|css|php|mp3|htm|pdf|zip|js|html|rdf|ttl|xml|rss|nt)$/i", $fileURI)) {
    if (mb_substr($fileURI, -1) == '/') {
    //  $fileURI = mb_substr($fileURI, 0, -1);
    //}
    //if ($fileURI == D2D_DOCUMENT_ROOT) {
    //  $fileURI .= '/';
      $fileURIrdf = $fileURI . 'index.rdf';
      $fileURIttl = $fileURI . 'index.ttl';
    } else {
      $fileURIrdf = $fileURI . '.rdf';
      $fileURIttl = $fileURI . '.ttl';

    }
  } else {
    // return file????????
  }

  // Check if rdf/xml file exists
  if (file_exists($fileURIrdf)) {
    $rdfData = file_get_contents($fileURIrdf);
    return $rdfData;
  } elseif (file_exists($fileURIttl)) {
    $rdfData = file_get_contents($fileURIttl);
    return $rdfData;
  //} else {
    // TODO: Check for triples in store
  } else {
    // Not found as file nor in triple store
    if ($FailResponseCode == null) { 
      return null;
    } else {
      returnErrorPage($FailResponseCode, '<i>The follwing resource could not be found:</i><br />' . $webURI);
    }
  }
}


//-------------------------------------------------------------------------------------

function getLocalURI($uri) {
  $hostPos = mb_stripos($uri, D2D_HTTP_HOST);
  if ($hostPos !== false && $hostPos < 10) {
    // The requested URI is on this server.
    $locURI = D2D_DOCUMENT_ROOT . mb_substr($uri, $hostPos + mb_strlen(D2D_HTTP_HOST));
    return $locURI;
  } else {
    // The requested URI is not on this server.
    return false;
  }
}

//-------------------------------------------------------------------------------------


function dereferenceURI($graphObj, $uri) {
  //FOR LOCAL TESTING WITH DBPEDIA:
  //if (mb_substr($uri, 0, 18) == 'http://dbpedia.org') $uri = 'http://rdfns.org/dbpedia.org' . mb_substr($uri, 18);

  $result = 0;
  try {
    if (mb_substr($uri, 0, 2) == '_:') {
      //The uri is a blank node uri and cannot be dereferenced.
      return false;
    }

    // Prevent dereferencing the same URI multiple times for the specified graph.
    // Remove fragment identifier part
    $hashPos = mb_stripos($uri, '#');
    if ($hashPos !==  false) {
      $truncatedUri = substr($uri, 0, $hashPos);
    } else {
      $truncatedUri = $uri;
    }
    if (Dereferences::$fetchedUris[$truncatedUri] == true) { // Prefent double dereferencing of the same URI.
      //echo "SOLVED DOUBLE DEREFERENCE: $uri \n<br /><br /><br />\n\n";
      return true;
    }
    if (D2D_DEBUG) $ppc_time_start = microtime(true);
    Dereferences::$fetchedUris[$truncatedUri] = true; // This does not account for possible (http/network/etc.) errors in dereferencing the URI, however the EasyRDF load function also does not indicate such errors (documentation states only that the number of loaded triples is returned; no mention of any error specific return values such as false or null). Hence, a more elaborted approach is useless at this time.

    $locURI = getLocalURI($uri);
    if ($locURI !== false) {
      // The requested URI is on this server. Get it locally instead of using http.
      $rdfData = createResourceDescription($uri, $locURI, 422);
      if ($rdfData == '') {
        addWarning(503, "<i>A dereference request failed for the following resource:</i><br />" . $uri . "<br / ><br /><i>Error description:</i><br />No content returned.");
        $result = 0;
      } else {
        $result = $graphObj->parse($rdfData, null, $uri);
      }
    } else {
      // The requested URI is not on this server. Use an http request to dereference it.

      //$result = $graphObj->load($uri); // NB: OLD DIRECT LOAD FROM EasyRdf; not used because it falls over (incorrect) content-type strings such as text/xml ('unknown format'), even though the actual content is well formed and parsable XML/RDF. Besides, we have better control over the http headers that are send with the request.

      // Create a stream context
      $opts = array(
        'http'=>array(
          'method'=>"GET",
          'header'=>"Accept: " . D2D_RDF_ACCEPT . "\r\n" //. 
                    //"Accept-language: en\r\n" .
      ));
      $context = stream_context_create($opts);
      // Open the URI using the HTTP headers set above
      $rdfData = @file_get_contents($uri, false, $context) or $rdfData = null;
      if ($rdfData === null) {
        addWarning(503, "<i>A dereference request failed for the following resource:</i><br />" . $uri . "<br / ><br /><i>Error description:</i><br />Suppressed error on file_get_contents.");
        $result = 0;
      } elseif ($rdfData == '') {
        addWarning(503, "<i>A dereference request failed for the following resource:</i><br />" . $uri . "<br / ><br /><i>Error description:</i><br />No content returned.");
        $result = 0;
      } else {
        $result = $graphObj->parse($rdfData, null, $uri);
      }


      if (D2D_DEBUG) {echo "Dereference request (Start Time: " . mb_substr($ppc_time_start . '          ', 0, 10) . "; Duration: " . mb_substr((microtime(true) - $ppc_time_start) . ' ', 0, 5) . "): $uri <br />\n"; flush();}
    }

  } catch (Exception $e) {
    $result = 0;
    addWarning(503, "<i>A dereference request failed for the following resource:</i><br />" . $uri . "<br / ><br /><i>Error description:</i><br />" . $e->getMessage());
  }

  return $result;
}


//-------------------------------------------------------------------------------------

function persistentGet(&$graphOrResourceObj, $resource = null, $property, $type = null, $lang = null, $errorCode = null) {
  if ($resource === null) {
    for ($i = 1; $i < 3; $i++) { // Do max 2 loops; one attempt to get objects for the property without dereferencing, and one after dereferencing if nothing was returned
      if ($lang !== null && $type === null) { // If language is specified but type is not, we need to request literals and resources seperatly, otherwise the language filter is (also) not applied for literals (is this a bug in EasyRDF?) 
        $result = $graphOrResourceObj->get($property, 'literal', $lang); // Prefer a literal for the single result, since a language tag was provided.
        if ($result === null) $result = $graphOrResourceObj->get($property, 'resource', $lang);
      } else {
        $result = $graphOrResourceObj->get($property, $type, $lang);
      }
      if ($result !== null || $i == 2) break; // If there is a result, or this was the second attempt, do not dereference and exit the loop
      dereferenceURI($graphOrResourceObj->getGraph(), $graphOrResourceObj->getUri());
    }
  } else {
    for ($i = 1; $i < 3; $i++) { // Do max 2 loops; one attempt to get objects for the property without dereferencing, and one after dereferencing if nothing was returned
      if ($lang !== null && $type === null) { // If language is specified but type is not, we need to request literals and resources seperatly, otherwise the language filter is (also) not applied for literals (is this a bug in EasyRDF?) 
        $result = $graphOrResourceObj->get($resource, $property, 'literal', $lang); // Prefer a literal for the single result, since a language tag was provided.
        if ($result === null) $result = $graphOrResourceObj->get($resource, $property, 'resource', $lang);
      } else {
        $result = $graphOrResourceObj->get($resource, $property, $type, $lang);
      }
      if ($result !== null || $i == 2) break; // If there is a result, or this was the second attempt, do not dereference and exit the loop
      dereferenceURI($graphOrResourceObj, $resource);
    }
  }

  if ($result === null) {
    if ($errorCode === null) {
      if ($resource === null) {
        returnErrorPage(422, '<i>On resource:</i><br />' . $graphOrResourceObj->getUri() . '<br /><br /><i>The follwing property could not be found:</i><br />' . $property);
      } else {
        returnErrorPage(422, '<i>On resource:</i><br />' . $resource . '<br /><br /><i>The follwing property could not be found:</i><br />' . $property);
      }
    } else {
      if ($errorCode > 0) {
        returnErrorPage($errorCode);
      } else {
        return null;
      }
    }
  } else {
    return $result;
  }
}


//-------------------------------------------------------------------------------------

function persistentAll(&$graphOrResourceObj, $resource = null, $property, $type = null, $lang = null, $errorCode = null) {
  if ($resource === null) {
    for ($i = 1; $i < 3; $i++) { // Do max 2 loops; one attempt to get objects for the property without dereferencing, and one after dereferencing if nothing was returned
      if ($lang !== null && $type === null) { // If language is specified but type is not, we need to request literals and resources seperatly, otherwise the language filter is (also) not applied for literals (is this a bug in EasyRDF?) 
        $result = array_filter($graphOrResourceObj->all($property, 'literal', $lang)); // Returned array is filtered for members containing null, 0, '', false, or empty array
        $result = array_merge($result, array_filter($graphOrResourceObj->all($property, 'resource', $lang))); // Returned array is filtered for members containing null, 0, '', false, or empty array
      } else {
        $result = array_filter($graphOrResourceObj->all($property, $type, $lang)); // Returned array is filtered for members containing null, 0, '', false, or empty array
      }
      if ($result || $i == 2) break; // If there is a result (if the array is not empty), or this was the second attempt, do not dereference and exit the loop
      dereferenceURI($graphOrResourceObj->getGraph(), $graphOrResourceObj->getUri());
    }
  } else {
    for ($i = 1; $i < 3; $i++) { // Do max 2 loops; one attempt to get objects for the property without dereferencing, and one after dereferencing if nothing was returned
      if ($lang !== null && $type === null) { // If language is specified but type is not, we need to request literals and resources seperatly, otherwise the language filter is (also) not applied for literals (is this a bug in EasyRDF?) 
        $result = array_filter($graphOrResourceObj->all($resource, $property, 'literal', $lang)); // Returned array is filtered for members containing null, 0, '', false, or empty array
        $result = array_merge($result, array_filter($graphOrResourceObj->all($resource, $property, 'resource', $lang))); // Returned array is filtered for members containing null, 0, '', false, or empty array
      } else {
        $result = array_filter($graphOrResourceObj->all($resource, $property, $type, $lang)); // Returned array is filtered for members containing null, 0, '', false, or empty array
      }
      if ($result || $i == 2) break; // If there is a result (if the array is not empty), or this was the second attempt, do not dereference and exit the loop
      dereferenceURI($graphOrResourceObj, $resource);
    }
  }

  if (!$result) { // If the array is empty
    if ($errorCode === null) {
      if ($resource === null) {
        returnErrorPage(422, '<i>On resource:</i><br />' . $graphOrResourceObj->getUri() . '<br /><br /><i>The follwing property could not be found:</i><br />' . $property);
      } else {
        returnErrorPage(422, '<i>On resource:</i><br />' . $resource . '<br /><br /><i>The follwing property could not be found:</i><br />' . $property);
      }
    } else {
      if ($errorCode > 0) {
        returnErrorPage($errorCode);
      } else {
        return $result;
      }
    }
  } else {
    // Process possible RDF Container or Collection objects
    $processedResult = array();
    foreach ($result as $obj) {
      if ($obj instanceof EasyRdf_Container || $obj instanceof EasyRdf_Collection) {
        foreach ($obj as $objObj) {
          $processedResult[] = $objObj;
        }
      } else {
        $processedResult[] = $obj;
      }
    }
    return $processedResult;
  }
}


//-------------------------------------------------------------------------------------


class Dereferences {
    public static $fetchedUris = array();
}

//-------------------------------------------------------------------------------------


?>
