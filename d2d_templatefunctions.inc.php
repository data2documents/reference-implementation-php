<?php

//-------------------------------------------------------------------------------------

function loadTemplate(&$useTemplate, $isPageTemplate = false, $renderDef = null) {
  if (mb_strpos(mb_substr($useTemplate, 0, 10), '://') !== false) {
    // The specified template is a URI that is to be used as a URL for the template.
    $locURI = getLocalURI($useTemplate);
    if ($locURI !== false) {
      if (Templates::$parsedFiles[$locURI] != true) { // Prevent double processing of the same template (Same file can be refferenced by multiple rendering definitions if it contains multiple templates).
        if (file_exists($locURI)) {
          $xmlData = file_get_contents($locURI);
        } else {
          returnErrorPage(422, '<i>The follwing template could not be found:</i><br />' . $useTemplate);
        }
        $xmlObj = simplexml_load_string($xmlData, "SimpleXMLElement", LIBXML_NOBLANKS);
        Templates::$parsedFiles[$locURI] = true;
      } else {
        return;
      }
    } else {
      if (Templates::$parsedFiles[$useTemplate] != true) { // Prevent double processing of the same template (Same file can be refferenced by multiple rendering definitions if it contains multiple templates).
        $xmlObj = simplexml_load_file($useTemplate);
        Templates::$parsedFiles[$useTemplate] = true;
      } else {
        return;
      }
    }
  } else {

//echo $useTemplate . "\n\n--------------------------------------------\n\n\n\n\n\n";
//$arr = BuildArticleTemplateArray($useTemplate); var_dump($arr); exit(); // DEBUG CODE!!

//$arr = array();
//$result = preg_match_all('/[<\[]d2d:Field([^<\[]*\/[>\]]|.*[<\[]\/d2d:Field[^>\]]*[>\]])/sU', $useTemplate, $arr, PREG_OFFSET_CAPTURE); // '/[<\[]d2d:Field([^<\[]*\/[>\]]|.*[<\[]\/d2d:Field[^>\]]*[>\]])/sU'
//var_dump($arr); exit();

    $xmlObj = simplexml_load_string($useTemplate);
  }


  $xmlObj->registerXPathNamespace('d2d', D2D_NS);

  $templates = $xmlObj->xpath('//d2d:Template');

  $index = count($templates); while ($index) { $template = $templates[--$index]; // NB: This is a foreach in reverse order
    $defIRI = $template->attributes(D2D_NS)->{'for'};
    if ($defIRI == null) { 
      if ($renderDef != null && count($templates) == 1) {
        $defIRI = $renderDef->getUri();
      }
    }
    if ($defIRI != null) { 
      $templateXML = $template->asXML();
      $namespaces = $template->getNamespaces();
      $prefixFound = '';
      foreach ($namespaces as $prefix => $namespace) {
        if ($namespace == D2D_NS) {
          $prefixFound = 'xmlns:' . $prefix;
          break;
        }
      }
      if ($prefixFound == '') {
        $prefixFound = 'xmlns:d2d';
      }
      if (mb_strpos(mb_substr($templateXML, 0, mb_strpos($templateXML, '>')), $prefixFound) === false) {
        $template[$prefixFound] = D2D_NS;
        $templateXML = $template->asXML();
      }
      Templates::$arr[(string) $defIRI] = $templateXML;
    }
    unset($template[0]);
  }

  if ($isPageTemplate == true) {
    Templates::$arr['::doc::'] = $xmlObj->asXML();
  }
}



//-------------------------------------------------------------------------------------

function BuildArticleTemplateArray($templateStr) {

  $templateArr = array();
  $templateArr['type'] = 'Article';
  $templateArr['index'] = -1;
  $templateArr['satisfied'] = false;

  $templateArr['d2d:Field'] = array();
  $templateArr['d2d:Segment'] = array();
  $templateArr['d2d:Content'] = array();

  $templateArr['assignedFieldIndexes'] = array();

  $templateArr['components'] = &ProcessArticleTemplateStr($templateStr, $templateArr, $templateArr);

  // Assign indexes to non-indexed field objects based on first free index and construct new array based on index.
  $newObjsArr = array();
  $newIndex = 0;
  do {
    $newIndex++;
  } while ($templateArr['assignedFieldIndexes'][$newIndex]);
  foreach ($templateArr['d2d:Field'] as &$fieldObj) {
    if ($fieldObj['index'] == -1) {
      $fieldObj['index'] = $newIndex;
      do {
        $newIndex++;
      } while ($templateArr['assignedFieldIndexes'][$newIndex]);
    }
    if ($fieldObj['components'] === null) {
      $fieldObj['components'] = array();
      unset($newContentObj);
      $newContentObj = array('type' => 'd2d:Content', 'parent' => &$fieldObj, 'satisfied' => false, 'index' => $fieldObj['index'], 'components' => null);
      $fieldObj['components'][] = &$newContentObj;
      $templateArr['d2d:Content'][] = &$newContentObj;
    }
    foreach ($fieldObj['segmentTemplates'] as &$segTemplate) {
      $segTemplate['index'] = $fieldObj['index'];
    }
    if (!array_key_exists($fieldObj['index'], $newObjsArr)) $newObjsArr[$fieldObj['index']] = array();
    $newObjsArr[$fieldObj['index']][] = &$fieldObj;
  }
  $templateArr['d2d:Field'] = &$newObjsArr;


  // Assign indexes to non-indexed segment objects based on the parent object (= field object) index and construct new array based on index.
  unset($newObjsArr);
  $newObjsArr = array();
  foreach ($templateArr['d2d:Segment'] as &$segmentObj) {
    if ($segmentObj['index'] == -1) {
      $segmentObj['index'] = $segmentObj['parent']['index'];
    }
    if ($segmentObj['components'] === null) {
      $segmentObj['components'] = array();
      unset($newContentObj);
      $newContentObj = array('type' => 'd2d:Content', 'parent' => &$segmentObj, 'satisfied' => false, 'index' => $segmentObj['index'], 'components' => null);
      $segmentObj['components'][] = &$newContentObj;
      $templateArr['d2d:Content'][] = &$newContentObj;
    }
    if (!array_key_exists($segmentObj['index'], $newObjsArr)) $newObjsArr[$segmentObj['index']] = array();
    $newObjsArr[$segmentObj['index']][] = &$segmentObj;
  }
  $templateArr['d2d:Segment'] = &$newObjsArr;


  // Assign indexes to non-indexed content objects based on the parent object (= segment or field object) index and construct new array based on index.
  unset($newObjsArr);
  $newObjsArr = array();
  foreach ($templateArr['d2d:Content'] as &$contentObj) {
    if ($contentObj['index'] == -1) {
      $contentObj['index'] = $contentObj['parent']['index'];
    }
    if ($contentObj['components'] === null) {
      $contentObj['components'] = array();
    }
    if (!array_key_exists($contentObj['index'], $newObjsArr)) $newObjsArr[$contentObj['index']] = array();
    $newObjsArr[$contentObj['index']][] = &$contentObj;
  }
  $templateArr['d2d:Content'] = &$newObjsArr;


  unset($templateArr['assignedFieldIndexes']);

  return $templateArr;
}



//-------------------------------------------------------------------------------------

function &ProcessArticleTemplateStr(&$templateStr, &$parentObj, &$templateArr, $openTagType = 0) {

  $tagNames = array('', 'd2d:Field', 'd2d:Segment', 'd2d:Content');

  $components = array();

  do {
    $openTagResult = preg_match('/[<\[]d2d:Field[^>\]]*[>\]]|[<\[]d2d:Segment[^>\]]*[>\]]|[<\[]d2d:Content[^>\]]*[>\]]/sUi', $templateStr, $matchedOpenTag, PREG_OFFSET_CAPTURE);

    if ($openTagType) {
      $closeTagResult = preg_match('/[<\[]\/' . $tagNames[$openTagType] . '[^>\]]*[>\]]/sUi', $templateStr, $matchedCloseTag, PREG_OFFSET_CAPTURE);
    } else {
      $closeTagResult = 0;
    }

    if ($openTagResult !== 1 && $closeTagResult !== 1) {
      if ($openTagType) returnErrorPage(409, 'An error occured on template parsing. A ' . $tagNames[$openTagType] . ' closing tag is missing.');
      $components[] = $templateStr;
      break;
    } 

    if ($openTagResult === 1) {
      $openTagStr = $matchedOpenTag[0][0];
      $openTagOffset = $matchedOpenTag[0][1];
    } else {
      $openTagStr = '';
      $openTagOffset = PHP_INT_MAX;
    }
    if ($closeTagResult === 1) {
      $closeTagStr = $matchedCloseTag[0][0];
      $closeTagOffset = $matchedCloseTag[0][1];
    } else {
      $closeTagStr = '';
      $closeTagOffset = PHP_INT_MAX;
    }

    if ($openTagOffset < $closeTagOffset) {
      if ($openTagOffset > 0) {
        $components[] = mb_substr($templateStr, 0, $openTagOffset);
      }
      $templateStr = mb_substr($templateStr, $openTagOffset + mb_strlen($openTagStr));

      if (mb_stripos($openTagStr, $tagNames[1]) == 1) {
        $nestedOpenTagType = 1;
      } else if (mb_stripos($openTagStr, $tagNames[2]) == 1) {
        $nestedOpenTagType = 2;
      } else if (mb_stripos($openTagStr, $tagNames[3]) == 1) {
        $nestedOpenTagType = 3;
      }

      unset($compObj);
      $compObj = array();
      $compObj['type'] = $tagNames[$nestedOpenTagType];
      $compObj['parent'] = &$parentObj;
      $compObj['satisfied'] = false;
      $index = GetParameterValue('d2d:index', $openTagStr, -1);
      if (!is_numeric($index)) {
        $index = -1;
      } else {
        if ($index < 0) $index = -1;
      }
      if ($nestedOpenTagType == 1) {
        $templateArr['assignedFieldIndexes'][$index] = true;
        $compObj['segmentTemplates'] = array();
        $compObj['isSegmented'] = false;
      }
      $compObj['index'] = $index;
      if ($nestedOpenTagType == 2) $compObj['matchRule'] = GetParameterValue('d2d:matchRule', $openTagStr, '*');


      if ($nestedOpenTagType == 2) {
        $parentObj['segmentTemplates'][] = &$compObj;
        if (!$parentObj['isSegmented']) {
          $parentObj['isSegmented'] = true;
          unset($newInsertObj);
          $newInsertObj = array('type' => 'd2d:Segment', 'parent' => &$parentObj, 'satisfied' => false, 'index' => $index, 'components' => array());
          $templateArr[$tagNames[$nestedOpenTagType]][] = &$newInsertObj;
          $components[] = &$newInsertObj;
        }
      } else {
        $templateArr[$tagNames[$nestedOpenTagType]][] = &$compObj;
        $components[] = &$compObj;
      }


      if (mb_substr($openTagStr, -2, 1) != '/') { // Only process inner tag string if this is not a self closing tag (which has no inner string) 
        $compObj['components'] = &ProcessArticleTemplateStr($templateStr, $compObj, $templateArr, $nestedOpenTagType);
        if ($nestedOpenTagType == 3) $compObj['components'] = array();
      } else {
        $compObj['components'] = null;
      }
    } else {
      if ($closeTagOffset > 0) {
        $components[] = mb_substr($templateStr, 0, $closeTagOffset);
      }
      $templateStr = mb_substr($templateStr, $closeTagOffset + mb_strlen($closeTagStr));
      break;
    }




    $safety++;

  } while (true);

  return $components;
}


//-------------------------------------------------------------------------------------

function GetParameterValue($parameterName, $tagString, $default = null) {

  $parameterPos = mb_stripos($tagString, $parameterName);
  if ($parameterPos === false) return $default;
 
  $valuePos = $parameterPos + mb_strlen($parameterName);

  $valueStr = '';
  $readPos = $valuePos + 1; // Plus one for = sign
  $quoteOpen = false;
  do {
    $char = mb_substr($tagString, $readPos, 1);
    $readPos++;

    if ($char === false || $char === '') break;
    if ($char == '"') {
      if ($quoteOpen == true) {
        break;
      } else {
        if ($readPos == $valuePos + 2) {
          $quoteOpen = true;
          continue;
        }
      }
    }
    if ($quoteOpen == false) {
      if ($char == ' ' || $char == '/' || $char == '>' || $char == ']') {
        break;
      }
    }

    $valueStr .= $char;

  } while (true);

  if ($valueStr == '') return ''; // null;  // NB: Parameters can be 'legally' empty.
  if (is_numeric($valueStr)) return ($valueStr + 0);
  return $valueStr;
}


//-------------------------------------------------------------------------------------

function registerRenderDef($renderDef, &$definitionsArray) {
  if ($renderDef === null) return;

  $articleDefs = persistentAll($renderDef, null, 'd2d:renders', 'resource', null, 0);
  if (!$articleDefs) return; // If there is no article definition specified (if the array is empty), there is nothing to add.

  foreach ($articleDefs as $articleDef) {
    unset($newEntry);
    $newEntry = array('articleDefUri' => $articleDef->getUri(), 'articleDef' => $articleDef, 'renderDef' => $renderDef);
    $definitionsArray['byDefinition'][] = &$newEntry;
  
    $resourceClasses = persistentAll($articleDef, null, 'd2d:fitsClass', 'resource', null, 0);
    $resourceClasses[] = $articleDef; // Add the URI of the article definition itself as a default class type for article instances following this article definition
    foreach($resourceClasses as $resourceClass) {
      $definitionsArray['byClass'][] = array('class' => $resourceClass->getUri(), 'definitions' => &$newEntry);
    }
  }
}

//-------------------------------------------------------------------------------------

function registerArticleDef($articleDef, &$definitionsArray) {
  if ($articleDef === null) return; // If there is no article definition specified, there is nothing to add.

  unset($newEntry);
  $newEntry = array('articleDefUri' => $articleDef->getUri(), 'articleDef' => $articleDef, 'renderDef' => null);
  $definitionsArray['byDefinition'][] = &$newEntry;
  
  $resourceClasses = persistentAll($articleDef, null, 'd2d:fitsClass', 'resource', null, 0);
  $resourceClasses[] = $articleDef; // Add the URI of the article definition itself as a default class type for article instances following this article definition
  foreach($resourceClasses as $resourceClass) {
    $definitionsArray['byClass'][] = array('class' => $resourceClass->getUri(), 'definitions' => &$newEntry);
  }
}

//-------------------------------------------------------------------------------------

function getDefinitionsForClass($class, &$definitionsArray) {
  $class = (string) $class;
  for ($i = count($definitionsArray['byClass']) - 1; $i >= 0; $i--) { // A revered loop is done to get the latest addition first. NB: This way of doing a reversed loop is fast, but only works here because sequential numeric keys with base 0 are ensured.
    if ($definitionsArray['byClass'][$i]['class'] == $class) {
      return $definitionsArray['byClass'][$i]['definitions'];
    }
  }
  return null;
}

//-------------------------------------------------------------------------------------

function getRenderDefForArticleDef($articleDef, &$definitionsArray) {
  $articleDef = (string) $articleDef;
  for ($i = count($definitionsArray['byDefinition']) - 1; $i >= 0; $i--) { // A revered loop is done to get the latest addition first. NB: This way of doing a reversed loop is fast, but only works here because sequential numeric keys with base 0 are ensured.
    if ($definitionsArray['byDefinition'][$i]['articleDefUri'] == $articleDef) {
      return $definitionsArray['byDefinition'][$i]['renderDef'];
    }
  }
  return null;
}

//-------------------------------------------------------------------------------------

class Templates {
    public static $arr = array();
    public static $parsedFiles = array();
}


//-------------------------------------------------------------------------------------

?>
