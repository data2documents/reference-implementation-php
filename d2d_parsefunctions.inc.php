<?php

//-------------------------------------------------------------------------------------

function parseArticle($article, $articleDef, $renderDef, $definitionLibrary, $processingRSR = false) {

//if ((string) $article == 'http://www.playground.nl/d2d/a/HomePageArticle') { $nestedCall = true;} // DEBUG CODE
//if ($nestedCall) { var_dump($renderDef); exit();}


    // Get al the types for the provided Article Resource in order to check if Article Definition (and therefore Rendering Definition) do or do not fit.
    if ($processingRSR) { // For processing of (SPARQL) resultset rows, the definition(s) must be provided with the initial function call, because they can not be fetched using the 'article' in any way, since it is not an RDF resource. 
      $types = array(new EasyRdf_Resource(D2D_NS . 'SparqlResultset'));
    } else {
      $types = persistentAll($article, null, 'rdf:type', null, null, 0); // NB: The EasyRdf 'isA' method does not work correctly, as well as the 'types' method, which reurns a NULL value for most of the listed types (related?). Hence, we have to use rdf:type 'manually'. This is not such a burdon though, because the 'isA' method also has to loop over the list of types internally, but furthermore, it does not dereference the resource URI in order to get its types if not present (which is the case for these statements).
    }
    $secondTimeArround = false;
    do {
      // If an Article Definition is provided, check if the provided article definition matches with a resource class type of the provided Article Resource.
      if ($articleDef !== null) {
        do {
          $resourceClasses = persistentAll($articleDef, null, 'd2d:fitsClass', 'resource', null, 0);
          $resourceClasses[] = $articleDef; // Add the URI of the article definition itself as a default class type for article instances following this article definition
          foreach ($resourceClasses as $resourceClass) {
            foreach($types as $type) { 
              if ($type->getUri() == $resourceClass->getUri()) {
                break 3;
              }
            }
          }
          $articleDef = null; // The provided article definition did not match the resource class of the provided Article Resource.
        } while (false);
      }
      // Check if the provided render definition matches with the resource class of the provided article instance
      if ($renderDef !== null) {
        do {
          $articleDefs = persistentAll($renderDef, null, 'd2d:renders', 'resource');
          // If an Article Definition was provided: Check if the provided Rendering Definition fits the provided Article Definition. The Article Definition itself was already checked to fit a type of the provided Article Resource.
          if ($articleDef !== null) { 
            foreach ($articleDefs as $artDef) {
              if ($articleDef->getUri() == $artDef->getUri()) {
                break 2;
              }
            }
            $articleDef = null; // The provided Article Definition did not match the provided Rendering Definition.
          }
          // If an Article Definition was NOT provided: Load all Article Definitions that fit the provided Rendering Definition, and check if one of those fits a resource type for the provided Article Resource. Pick the first match.
          foreach ($articleDefs as $artDef) {
            $resourceClasses = persistentAll($artDef, null, 'd2d:fitsClass', 'resource', null, 0);
            $resourceClasses[] = $artDef; // Add the URI of the article definition itself as a default class type for article instances following this article definition
            foreach ($resourceClasses as $resourceClass) {
              foreach($types as $type) { 
                if ($type->getUri() == $resourceClass->getUri()) {
                  $articleDef = $artDef;
                  break 4;
                }
              }
            }
          }
          $renderDef = null; // The provided Rendering Definition did not match an Article Definition that fits a resource class type of the provided Article Resource.
        } while (false);
      }
      // If no render definition is available at this point, try to find one in the definitions library. First using the Article Definition if available; if that fails try using the resource class types of the provided Article Instance
      // NB: If a Render Definition is known at this point, an Article Definition is also known. 
      if ($secondTimeArround == false && $renderDef === null) {
        if ($articleDef !== null) {    
          $renderDef = getRenderDefForArticleDef($articleDef, $definitionLibrary);
        }
        // If a rendering definition is (still) not found, try to find it in the definitions library using the resource class types of the provided article resource.
        if ($renderDef === null) {
          foreach($types as $type) { 
            $definitionSet = getDefinitionsForClass($type->getUri(), $definitionLibrary);
            if ($definitionSet['articleDef'] !== null) {
              if ($articleDef === null) $articleDef = $definitionSet['articleDef']; // Do not break; if there is a match for a render definition further on, prefer that one. Because of the lacj of break, only set when not done so earlier to take the lastly added article definition.
            }
            if ($definitionSet['renderDef'] !== null) {
              $articleDef = $definitionSet['articleDef']; // Set again to the article definition matching the render definition (set before can be skipped if another article definition was set earlier.
              $renderDef = $definitionSet['renderDef'];
              break;
            }
          }
          // If a Render Definition was not found in the (prefered) definitions library for the directly provided Article Definition, try getting a default Rendering Definition for the provided Article Definition, if provided.
          if ($renderDef === null) {
            if ($articleDef !== null) {
              $renderDef = persistentGet($articleDef, null, 'd2d:defaultRenderDef', 'resource', null, 0);
            }
          }
          // If a default Render Definition was not found for the directly provided Article Definition (if provided), try getting a Rendering Definition directly from the provided Article Resource.
          if ($renderDef === null && !$processingRSR) { // NB: Resultset Rows are not RDF resources, hence they can not have a directly specified Article Definition or Rendering Definition.
            $renderDef = persistentGet($article, null, 'd2d:useRenderDef', 'resource', null, 0);
            // If a render definition could not be retrieved directly from the Article Resource, try getting an Article Definition directly from the Article Resource.
            if ($renderDef === null) {
              $directArticleDef = persistentGet($article, null, 'd2d:useArticleDef', 'resource', null, 0);
              if ($directArticleDef !== null) { // Use the directly specified Article Definition to try and find a Rendering Definition in the (prefered) definitions library.
                $renderDef = getRenderDefForArticleDef($directArticleDef, $definitionLibrary);
                if ($renderDef !== null) { // If a Rendering Definition was found for the directly specified Article Definition, use the directly specified Article Definition instead of a (possibly) provided Article Definition.
                  $articleDef = $directArticleDef;
                } else { // If a Rendering Definition was not found for the directly specified Article Definition, try getting a default Rendering Definition for the directly specified Article Definition.
                  $renderDef = persistentGet($directArticleDef, null, 'd2d:defaultRenderDef', 'resource', null, 0);
                  if ($renderDef !== null) {   
                    $articleDef = $directArticleDef;
                  }
                }
              } else {
                if (count($types) == 1) { // If the article resource has only one type, see if that type itself is an article definition.
                  $classTypes = persistentAll($types[0], null, 'rdf:type', null, null, 0);
                  foreach($classTypes as $classType) {
                    if ($classType->getUri() == D2D_NS . 'ArticleDefinition') {
                      $articleDef = $types[0];
                      break;
                    }
                  } 
                }
              } 
            }
          }
        }
        $secondTimeArround = true;
      } else {
        break; // If an Rendering Definition (and Article Definition) was found, or this is the second time the outer do loop is run, break out of it; The second loop is to check a possibly retrieved Article Definition and/or Rendering Definition only.
      }
    } while (true);


  // If not at least the article definition is known (NB: Likely to be changed to rendering definition?!), stop processing this 'article' --> Should there be an option to (in that case) list (all) properties?
  if ($articleDef === null) {
    if ($processingRSR) { 
      foreach ($article as $varName => $varValue) {$articleDump .= "$varName: $varValue    <br />\n";}
      addWarning(422, '<i>An article definition could not be found to process the following SPARQL resultset-row as D2D article:</i><br />' . $articleDump);
    } else {
      addWarning(422, '<i>An article definition could not be found to process the following resource as D2D article:</i><br />' . $article->getUri());
    }
    return null;
  }




  // Load the article rendering definition's template(s) to the template library
  if ($renderDef !== null) {
    $template = (string) persistentGet($renderDef, null, 'd2d:hasTemplate');
    if ($template !== null) {
      loadTemplate($template, false, $renderDef);
    }
  }


  // Get the number of fields for the article definition
  //$fieldCount = (int)(string) persistentGet($articleDef, null, 'd2d:fieldCount');


  // Get the skipOnError property for the article definition
  $skipOnError = $articleDef->get('d2d:skipOnError');
  if ($skipOnError === null) { // Property not provided; use default setting
    $skipOnError = D2D_DEFAULT_ARTSEC_SKIPONERROR;
  } else {
    $skipOnError = filter_var((string) $skipOnError, FILTER_VALIDATE_BOOLEAN);
  }


  // Get all field specifications of the article type
  $fieldSpecs = persistentAll($articleDef, null, 'd2d:hasFieldSpec', 'resource', null, 0);
  //$fieldSpecs = $articleDef->allResources('d2d:hasFieldSpec');
  $fieldCount = count($fieldSpecs);
  //if (count($fieldSpecs) < $fieldCount) {
  //  // Aperently, we do not have all field specification subjects loaded in the graph; therfore, try dereferencing the article type.
  //  dereferenceURI($articleDef->getGraph(), $articleDef->getUri());
  //  $fieldSpecs = $articleDef->allResources('d2d:hasFieldSpec');
  //  if (count($fieldSpecs) < $fieldCount) {
  //    returnErrorPage(422, '<i>On resource:</i><br />' . $articleDef->getUri() . '<br /><br /><i>The follwing property did not match the specified number of values (' . count($fieldSpecs) . ' instead of ' . $fieldCount . '):</i><br />d2d:hasFieldSpec');
  //  }
  //}


  // ORDER FIELDSPECS ON SEQUENCE INDEX --> only needed if no template is available
  //if ($renderDef === null) {
  //  usort($fieldSpecs, function($a, $b) {
  //      $indexA = (int) (string) persistentGet($a, null, 'd2d:index'); // NB: NEEDS cast through string, otherwise $fieldIndex will contain inproper value (array element count)
  //      $indexB = (int) (string) persistentGet($b, null, 'd2d:index'); // NB: NEEDS cast through string, otherwise $fieldIndex will contain inproper value (array element count)
  //      return $indexA - $indexB;
  //  });
  //}


  // Get the specific template from the template library
  if ($renderDef !== null) {
    $articleDomStr = Templates::$arr[$renderDef->getUri()];
  } else {
    $articleDomStr = null;
  }
  if ($articleDomStr !== null) {
    $templateArray = BuildArticleTemplateArray($articleDomStr);
  } else {
    $articleDomStr = '';
    $templateArray = null;
  }

  $fieldIndex = 0;

  foreach($fieldSpecs as $fieldSpec) {
    $fieldDomStr = '';

          $fieldIndex++; // = (int) (string) persistentGet($fieldSpec, null, 'd2d:index'); // NB: NEEDS cast through string, otherwise $fieldIndex will contain inproper value (array element count)


          $fieldType = persistentGet($fieldSpec, null, 'd2d:hasFieldType');
          $fieldType = $fieldType->shorten();


          $trimContent = $fieldSpec->get('d2d:trim');
          if ($trimContent === null) { // Property not provided; use default setting
            $trimContent = D2D_DEFAULT_FIELD_TRIM;
          } else {
            $trimContent = filter_var((string) $trimContent, FILTER_VALIDATE_BOOLEAN);
          }

          $limitContent = $fieldSpec->get('d2d:limit');
          if ($limitContent === null) $limitContent = D2D_DEFAULT_FIELD_LIMIT; // Property not provided; use default setting
          if ($limitContent == '*' || !filter_var((string) $limitContent, FILTER_VALIDATE_INT)) {
            $limitContent = PHP_INT_MAX;
          } else {
            $limitContent = (int) (string) $limitContent;
          }

          // Get the needed langage, if present
          $language = persistentGet($fieldSpec, null, 'd2d:language', 'literal', null, 0);
          if ($language === null) $language = D2D_DEFAULT_FIELD_LANGUAGE; // Property not provided; use default setting

          $useLabel = $fieldSpec->get('d2d:useLabel');
          if ($useLabel === null) { // Property not provided; use default setting
            $useLabel = D2D_DEFAULT_FIELD_USELABEL;
          } else {
            $useLabel = filter_var((string) $useLabel, FILTER_VALIDATE_BOOLEAN);
          }

          $createLink = $fieldSpec->get('d2d:createLink');
          if ($createLink === null) { // Property not provided; use default setting
            $createLink = D2D_DEFAULT_FIELD_CREATELINK;
          } else {
            $createLink = filter_var((string) $createLink, FILTER_VALIDATE_BOOLEAN);
          }


          $contentObjects = array();
          $unboundRoles = array();
          $encounteredRoles = array();

          $validateResult = validateTripleSpec($fieldSpec, $article,  $contentObjects, $unboundRoles, $encounteredRoles, $language);

          $isOptional = filter_var((string) $fieldSpec->get('d2d:isOptional'), FILTER_VALIDATE_BOOLEAN); // Stringified result of get() will be an empty string in case the property is not specified (and the return object will be null), or 'true', 'false', '1' or '0'
          if ($validateResult === null) {
            if (!$isOptional) {
              if (!$skipOnError) {
                returnErrorPage(409, '<i>On resource:</i><br />' . $article->getUri() . '<br /><br /><i>A match could not be found for the non-optional field specification:</i><br />' . $fieldSpec->getUri() . '<br /><br /><i>Of article definition:</i><br />' . $articleDef->getUri());
              } else {
                addWarning(409, '<i>On resource:</i><br />' . $article->getUri() . '<br /><br /><i>A match could not be found for the non-optional field specification:</i><br />' . $fieldSpec->getUri() . '<br /><br /><i>Of article definition:</i><br />' . $articleDef->getUri());
                return null;
              }
            }
            $nrOfContentObjects = 0;
          } else {
            $nrOfContentObjects = count($contentObjects);

            //Process unbound roles
            foreach ($unboundRoles as $unboundRole) {
              $roleName = $unboundRole['role'];
              switch ($roleName) {
                case 'PrefArticleDef':
                  registerArticleDef($unboundRole['object'], $definitionLibrary);
                  break;
                case 'PrefSectionDef':
                  registerArticleDef($unboundRole['object'], $definitionLibrary); // NB: CURRENTLY USES ARTICLE DEF REGISTER FUNCTION!
                  break;
                case 'PrefRenderDef':
                  registerRenderDef($unboundRole['object'], $definitionLibrary);
                  break;
              }
            }

            if ($encounteredRoles['SortBy']) { // If the 'SortBy' role was encountered during processing of the field specification, Sort the Content objects using provided objects. 
              usort($contentObjects, function($a, $b) {
                  $valA = trim((string) $a['roles']['SortBy']['object']);
                  $valB = trim((string) $b['roles']['SortBy']['object']);
//echo "SORT: $valA  <>  $valB  <br />\n";
                  if ($valA === null && $valB === null) return 0;
                  if ($valA === null) return 1;
                  if ($valB === null) return -1;
                  if (is_numeric($valA) && is_numeric($valB)) return $valA - $valB;
                  //TODO: Add special case for comparing strings that are dates; NB: For now, comparing the dates as plain strings works good enough due to XSD's international date notation and the fixed date/time formats using 4/2 digits. It works fine for xsd:data, xsd:time and xsd:dateTime as long as no time zones are included, or all time zones are equal.
                  return strcmp($valA, $valB);
              });
            }


            if ($encounteredRoles['SparqlQuery']) { // If the 'Query' role was encountered during processing of the field specification, execute the query and substitute the result as processable content.
              $processingRSO = true;
              $nrOfContentObjects = 0;
              $rsoPrefArticleDef = $contentObjects[0]['roles']['PrefArticleDef']['object'];
              $rsoPrefRenderDef = $contentObjects[0]['roles']['PrefRenderDef']['object'];
              $contentEndpoint = (string) $contentObjects[0]['roles']['SparqlEndpoint']['object'];
              $contentQuery = (string) $contentObjects[0]['roles']['SparqlQuery']['object'];
              if (!$contentEndpoint) {
                addWarning(409, '<i>On resource:</i><br />' . $article->getUri() . '<br /><br /><i>A SPARQL Endpoint address could not be found for field specification:</i><br />' . $fieldSpec->getUri() . '<br /><br /><i>Of article definition:</i><br />' . $articleDef->getUri());
              } elseif (!$contentQuery) {
                addWarning(409, '<i>On resource:</i><br />' . $article->getUri() . '<br /><br /><i>A SPARQL Query string could not be found for field specification:</i><br />' . $fieldSpec->getUri() . '<br /><br /><i>Of article definition:</i><br />' . $articleDef->getUri());
              } else {
                try {
                  $sparql = new EasyRdf_Sparql_Client($contentEndpoint);
                  $contentObjects = $sparql->query($contentQuery);
                  if ($contentObjects instanceof EasyRdf_Sparql_Result) {
                    $nrOfContentObjects = $contentObjects->numRows();
                  } else {
                    addWarning(409, '<i>On resource:</i><br />' . $article->getUri() . '<br /><br /><i>A SPARQL Query request failed to return results for field specification:</i><br />' . $fieldSpec->getUri() . '<br /><br /><i>Of article definition:</i><br />' . $articleDef->getUri() . "<br /><br /><i>Endpoint:</i><br />" . $contentEndpoint . "<br / ><br /><i>Query:</i><br /><pre>" . str_replace(array('<', '>'), array('&lt;', '&gt;'), $contentQuery) . "</pre>");
                  }
                } catch (Exception $e) {
                  addWarning(503, '<i>On resource:</i><br />' . $article->getUri() . '<br /><br /><i>A SPARQL Query request failed for field specification:</i><br />' . $fieldSpec->getUri() . '<br /><br /><i>Of article definition:</i><br />' . $articleDef->getUri() . "<br /><br /><i>Endpoint:</i><br />" . $contentEndpoint . "<br / ><br /><i>Query:</i><br /><pre>" . str_replace(array('<', '>'), array('&lt;', '&gt;'), $contentQuery) . "</pre><br /><br /><i>Error description:</i><br />" . $e->getMessage());
                }
              }
              if (!$nrOfContentObjects) {
                if (!$isOptional) {
                  if (!$skipOnError) {
                    returnErrorPage(409, '<i>On resource:</i><br />' . $article->getUri() . '<br /><br /><i>A SPARQL Query produced no results for field specification:</i><br />' . $fieldSpec->getUri() . '<br /><br /><i>Of article definition:</i><br />' . $articleDef->getUri() . "<br /><br /><i>Endpoint:</i><br />" . $contentEndpoint . "<br / ><br /><i>Query:</i><br /><pre>" . str_replace(array('<', '>'), array('&lt;', '&gt;'), $contentQuery) . "</pre>");
                  } else {
                    addWarning(409, '<i>On resource:</i><br />' . $article->getUri() . '<br /><br /><i>A SPARQL Query produced no results for the non-optional field specification:</i><br />' . $fieldSpec->getUri() . '<br /><br /><i>Of article definition:</i><br />' . $articleDef->getUri() . "<br /><br /><i>Endpoint:</i><br />" . $contentEndpoint . "<br / ><br /><i>Query:</i><br /><pre>" . str_replace(array('<', '>'), array('&lt;', '&gt;'), $contentQuery) . "</pre>");
                    return null;
                  }
                }
              }
            } else {
              $processingRSO = false;
            }

          }





          if ($validateResult !== null) {
            if ($nrOfContentObjects > 0) {
              $contentObjectIndex = 0;
              $usedContentObjects = 0;
              foreach($contentObjects as $contentObject) {
                $contentObjectIndex++;
                if ($usedContentObjects >= $limitContent) break;

                unset($contentStr);
                $contentStr = '';
                switch ($fieldType) {

                  case 'd2d:Article':
                    if ($processingRSO) {
                      $contentStr = parseArticle($contentObject, $rsoPrefArticleDef, $rsoPrefRenderDef, $definitionLibrary, true);
                    } elseif ($contentObject['object']) {
                      $contentStr = parseArticle($contentObject['object'], $contentObject['roles']['PrefArticleDef']['object'], $contentObject['roles']['PrefRenderDef']['object'], $definitionLibrary);
                    } else {
                      $contentStr = null;
                    }
                    break;

                  default:
                    $contentStr = (string) $contentObject['object'];
                    if ($contentObject['object'] instanceof EasyRdf_Resource && $fieldType != 'd2d:Img') {
                      $resourceUri = $contentStr;
                      if ($useLabel) {
                        try {
                          $rdfsLabel = persistentGet($contentObject['object'], null, 'rdfs:label', 'literal', $language, 0);
                          if ($rdfsLabel !== null) $contentStr = $rdfsLabel;
                        } catch (Exception $e) {}
                      }
                      if ($createLink) $contentStr = '<a target="_blank" href="' . $resourceUri . '">' . $contentStr . '</a>';
                    }
                    break;
                }
                if ($contentStr === null) continue; // If there is no content for the current content object (e.g. article was disqualified), continue on with the next content object
                $usedContentObjects++;
                if ($trimContent) $contentStr = trim($contentStr);

                if ($templateArray === null) {
                  $fieldDomStr .= wrapContentForFieldType($contentStr, $fieldType);
                } else {
                  if (is_array($templateArray['d2d:Field'][$fieldIndex])) {
                    foreach($templateArray['d2d:Field'][$fieldIndex] as &$fieldObj) {
                      if ($fieldObj['isSegmented']) {
                        foreach($fieldObj['segmentTemplates'] as $segmentTemplateCopy) {
                          if (segmentMatches($segmentTemplateCopy['matchRule'], $contentObjectIndex, $nrOfContentObjects)) {
                            foreach($segmentTemplateCopy['components'] as $compKey => &$segmentComponent) {
                              if (is_array($segmentComponent)) {
                                if ($segmentComponent['type'] == 'd2d:Content' && $segmentComponent['index'] == $fieldIndex) {
                                  $segmentTemplateCopy['components'][$compKey] = &$contentStr;
                                }
                              }
                            }
                            // Since there wás content to place, the field tag and matching segment tag are always regarded 'satisfied' *if a matching segment was found*, even if no actual content was placed because a content tag was not placed inside the matching segment tag. This allowes for conditional markup in the document sepereted from the actual placement of the content.
                            $segmentTemplateCopy['satisfied'] = true;
                            $templateArray['d2d:Segment'][$fieldIndex][0]['components'][] = $segmentTemplateCopy;
                            $templateArray['d2d:Segment'][$fieldIndex][0]['satisfied'] = true;
                            $fieldObj['satisfied'] = true;
                            $templateArray['satisfied'] = true;
                            break; // Only use the first matching segment template
                          }
                        }
                      } else {
                        foreach($fieldObj['components'] as &$fieldComponent) {
                          if (is_array($fieldComponent)) {
                            if ($fieldComponent['type'] == 'd2d:Content' && $fieldComponent['index'] == $fieldIndex) {
                              $fieldComponent['components'][] = $contentStr;
                              $fieldComponent['satisfied'] = true;

                            }
                          }
                        }
                        // Since there wás content to place, the field tag is always regarded 'satisfied', even if no actual content was placed because a content tag was not placed inside the field tag. This allowes for conditional markup in the document sepereted from the actual placement of the content.
                        $fieldObj['satisfied'] = true;
                        $templateArray['satisfied'] = true;
                      }
                    }
                  }
                }
              }
              if (!$usedContentObjects) {
                if (!$isOptional) {
                  if (!$skipOnError) {
                    returnErrorPage(409, '<i>On resource:</i><br />' . $article->getUri() . '<br /><br /><i>A qualifying match could not be found for the non-optional field specification:</i><br />' . $fieldSpec->getUri() . '<br /><br /><i>Of article definition:</i><br />' . $articleDef->getUri());
                  } else {
                    addWarning(409, '<i>On resource:</i><br />' . $article->getUri() . '<br /><br /><i>A qualifying match could not be found for the non-optional field specification:</i><br />' . $fieldSpec->getUri() . '<br /><br /><i>Of article definition:</i><br />' . $articleDef->getUri());
                    return null;
                  }
                }
              }
            }
          }



    if ($templateArray === null) {
      $articleDomStr .= $fieldDomStr;
    }
  }


  if ($templateArray === null) {
    $articleDomStr = '<article>' . $articleDomStr . '</article>';
  } else {
if ($nestedCall) {
//DEBUG_strip_parrent($templateArray);
//var_dump($templateArray); exit();
}
    $articleDomStr = serializeArticleTemplateArray($templateArray);
//echo $articleDomStr; exit();
  }

  return $articleDomStr;
}


//-------------------------------------------------------------------------------------

function segmentMatches($matchRule, $indexToMatch, $totalCount) {

  if ($matchRule == "*") return true;
  if ($matchRule == $indexToMatch) return true;

  if (strtolower($matchRule) == "odd" && $indexToMatch % 2 != 0) return true;
  if (strtolower($matchRule) == "even" && $indexToMatch % 2 == 0) return true;

  if (mb_strpos(str_replace(array(' ', ';'), ',', ',' . $matchRule . ','), ',' . $indexToMatch . ',') !== false) return true;

  $negativeEndIndex = $indexToMatch - ($totalCount + 1);
  if (mb_strpos(str_replace(array(' ', ';'), ',', ',' . $matchRule . ','), ',' . $negativeEndIndex . ',') !== false) return true;

  //TODO: Function evaluation 

  return false;

}

//-------------------------------------------------------------------------------------

function serializeArticleTemplateArray($templateArray) {
  $articleStr = '';

  if (!$templateArray['satisfied']) return $articleStr;

  foreach($templateArray['components'] as $component) {
    if (is_array($component)) {
      $articleStr .= serializeArticleTemplateArray($component);
    } else {
      $articleStr .= $component; 
    }
  }

  return $articleStr;
}


//-------------------------------------------------------------------------------------

function DEBUG_strip_parrent(&$templateArray) {
  unset($templateArray['parent']);
  foreach($templateArray['components'] as &$component) {
    if (is_array($component)) {
      DEBUG_strip_parrent($component);
    }
  }
}




//-------------------------------------------------------------------------------------

function wrapContentForFieldType($contentStr, $fieldType) {
  static $anchorTagOpen = false;

  switch ($fieldType) {

    case 'd2d:Article':
    case 'd2d:Section':
      $tagStr = $contentStr;
      break;

    case 'd2d:Img':
      $tagStr = '<img src="' . $contentStr . '" />';
      break;

    case 'd2d:A':
      $tagStr = '<a href="' . $contentStr . '">';
      $anchorTagOpen = true;
      break;


    default: // Including d2d:H1, d2d:H2, d2d:H3, d2d:H4, d2d:H5, d2d:H6, d2d:P, d2d:Div
      $temp = mb_strtolower(mb_substr($fieldType, 4));
      $tagStr = '<' . $temp . '>' . $contentStr . '</' . $temp . '>';
      break;
  }

  if ($anchorTagOpen && $fieldType != 'd2d:A') {
    $tagStr .= '</a>';
    $anchorTagOpen = false;
  }

  return $tagStr;
}

//-------------------------------------------------------------------------------------


function validateTripleSpec($specification, $subject, &$contentObjects, &$unboundRoles, &$encounteredRoles, $neededLanguage = null, $branchMeasure = array()) {
  if ($specification === null) {
    return null;
  }

  if ($subject instanceof EasyRdf_Literal) {
    return null;
  }

  if ($specification->isA('d2d:TripleSpecification') || $specification->isA('d2d:FieldSpecification')) {
    if ($specification->isA('d2d:TripleSpecification')) {

      // Get the required predicate for the triple specification
      $neededPredicate = persistentGet($specification, null, 'd2d:hasPredicate', 'resource', null, 0);
      if ($neededPredicate === null) {
        // This triple specification does not match! Return the result of a possible alternative triple specification (will result in null if not specified).
        goto doAlternatives;
      }

      // Check if this triple specification is inverted
      $inverted = persistentGet($specification, null, 'd2d:inverted', 'literal', null, 0);

      // Check if this triple specification is optional
      $isOptional = persistentGet($specification, null, 'd2d:isOptional', 'literal', null, 0);

      // Get the needed object type, if present
      $neededObjectType = persistentGet($specification, null, 'd2d:hasObjectType', 'resource', null, 0);

      // Get all objects for the specified predicate
      if ($inverted) {
        $subjectGraph = $subject->getGraph();
        $objects = $subjectGraph->resourcesMatching($neededPredicate, $subject); // NB: The '<fullIRI>' approach that is needed with the EasyRDF 'get' and 'all' methods, does not work with the 'resourcesMatching' method of the graph object. Without the <> it does work (contrary to get and all). However just passing the resource abject also works, hence this alternative is chosen as it is most likely to be future-version proof might this inconcistency be solved in any future update. 
        if (!$objects) { // Dereference the subject URI, which in fact is the object since this is an inverse triple specification. This only helps if the resource provides a Symmetric Concise Bounded Description (SCBD).
          dereferenceURI($subjectGraph, $subject->getUri());
          $objects = $subjectGraph->resourcesMatching($neededPredicate, $subject); // NB: The '<fullIRI>' approach that is needed with the EasyRDF 'get' and 'all' methods, does not work with the 'resourcesMatching' method of the graph object. Without the <> it does work (contrary to get and all). However just passing the resource abject also works, hence this alternative is chosen as it is most likely to be future-version proof might this inconcistency be solved in any future update. 
        }
//echo $neededPredicate->getUri(); echo "<br ><br > \n\n";
//if (!$objects) {echo "NO OBJECTS<br ><br > \n\n";} else {foreach($objects as $debugobj) { echo (string) $debugobj; echo  "<br ><br > \n\n";}};
//exit();
      } else {
        $objects = persistentAll($subject, null, '<' . $neededPredicate->getUri() . '>', null, $neededLanguage, 0);
      }

      // Get all object roles of the triple specification
      $roleObjs = $specification->allResources('d2d:hasRole');
      $roleObjs[] = $neededPredicate; // Add the needed predicate to the role objects array; if the needed predicate resembles a knwon D2D role predicate, it is added as role automatically.
      $roles = array();
      foreach($roleObjs as $roleObj) {
        $role = mb_strtolower(mb_substr($roleObj->getUri(), D2D_NS_LEN));
        switch ($role) { // Case correction; needed if e.g. the property predicate 'prefArticleDef' is encountered instead of the D2D role class resource 'PrefArticleDef';
          case 'content'       : $role = 'Content'       ; break;
          case 'prefarticledef': $role = 'PrefArticleDef'; break;
          case 'prefsectiondef': $role = 'PrefSectionDef'; break;
          case 'prefrenderdef' : $role = 'PrefRenderDef' ; break;
          case 'sortby'        : $role = 'SortBy'        ; break;
          case 'exclusion'     : $role = 'Exclusion'     ; $hasExclusionRole = true; break;
          case 'sparqlquery'   : $role = 'SparqlQuery'   ; break;
          case 'sparqlendpoint': $role = 'SparqlEndpoint'; break;
        }
        if (!in_array($role, $roles)) {
          $roles[] = $role; // NB: This $roles array contains the roles for the cuurent list of $objects for the particular instance of this function.
          $encounteredRoles[$role] = true; // NB: The $encounteredRoles array is used to register which roles were encountered during processing of the complete field specification.
        }
      }

      // Filter out objects that are not of the correct type
      if ($neededObjectType !== null) {
        foreach($objects as $key => $object) {
          if ($object instanceof EasyRdf_Literal) {
            if ($object->getDatatypeUri() != $neededObjectType->getUri()) {
              unset($objects[$key]);
            }
          } else {
            $types = persistentAll($object, null, 'rdf:type', null, null, 0); // NB: The EasyRdf 'isA' method does not work correctly, as well as the 'types' method, which reurns a NULL value for most if the listed types (related?). Hence, we have to use rdf:type 'manually'. This is not such a burdon though, because the 'isA' method also has to loop over the list of types internally, but furthermore, it does not dereference the resource URI in order to get its types if not present (which is the case for these statements).
            foreach($types as $type) { 
              if ($type->getUri() == $neededObjectType->getUri()) {
                continue 2; // Continue with the next object, hence do not unset current object.
              }
            }
            unset($objects[$key]);
          }
        }
      }
      // Filter out empty array elements and check if there is still at least 1 object left; oe none if this triple specification has the Exclusion role.
      $objects = array_filter($objects); // Array is filtered for members containing null, 0, '', false, or empty array
      if (!$objects) { // If the array is empty
        if ($hasExclusionRole) {
          return true; // There are no objects, and this triple specification has the Exclusion role. Hence, all conditions are met.
        } else {
          goto doAlternatives; // This triple specification does not match! Return the result of a possible alternative triple specification (will result in null if not specified).
        }
      } else {
        if ($hasExclusionRole) goto doAlternatives; // This triple specification does not match! Return the result of a possible alternative triple specification (will result in null if not specified).
      }


    } else {
      $variableSpecification = persistentGet($specification, null, 'd2d:usesVariable', null, null, 0);
      if ($variableSpecification !== null) {
        if ($variableSpecification instanceof EasyRdf_Literal) {
          $variableName = trim((string) $variableSpecification);
        } else {
          $variableName = persistentGet($variableSpecification, null, 'd2d:variableName', 'literal', null, 0);
          if ($variableName === null) {
            $variableName = '';
          } else {
            $variableName = trim((string) $variableName);
          }
        }
        if (!$variableName) return null; // This variable specification is invalid; It does not provide a usable variable name.
        $contentStr = (string) $subject->{$variableName};
        if ($contentStr === null) return null; // This variable specification is invalid; The provided variable name does not exist in the resultset.
        $contentObjects[] = array('object' => $contentStr, 'roles' => array(), 'branchMeasure' => $branchMeasureCopy);
        return true;
      } else {   
        $objects = array($subject);
        $roles = array();
      }
    }


    // Get all child triple specifications of the specification, and process them in recursion for each object.
    $childTripleSpecs = $specification->allResources('d2d:mustSatisfy');
    $branchCounter = 0;
    foreach ($objects as $key => $object) {
      $branchMeasureCopy = $branchMeasure;
      $branchMeasureCopy[] = ++$branchCounter;
      foreach($childTripleSpecs as $childTripleSpec) {
        $recurResult = validateTripleSpec($childTripleSpec, $object,  $contentObjects, $unboundRoles, $encounteredRoles, $neededLanguage, $branchMeasureCopy); // RECURSIVE CALL
        if ($recurResult === null) {
          $branchMeasureCopy = null;
          unset($objects[$key]);
          break;
        }
      }
      if ($branchMeasureCopy !== null) { // If the object is valid
        // Assign object and roles to the ContentObjects and/or UnboundRoles arrays.
        foreach($roles as $role) {
          if ($role == 'Content') {
            $contentObjects[] = array('object' => $object, 'roles' => array(), 'branchMeasure' => $branchMeasureCopy);
          } elseif ($role == 'SparqlQuery') {
            $coRoles = array('SparqlQuery' => array('role' => $role, 'object' => $object, 'branchMeasure' => $branchMeasureCopy));
            $contentObjects[] = array('object' => "SparqlQuery", 'roles' => $coRoles, 'branchMeasure' => $branchMeasureCopy);
          } else {
            $unboundRoles[] = array('role' => $role, 'object' => $object, 'branchMeasure' => $branchMeasureCopy);
          }
        }
      }
    }
    $objects = array_filter($objects); // Array is filtered for members containing null, 0, '', false, or empty array
    if (!$objects) { // If the array is empty
      // This triple specification does not match! Return the result of a possible alternative triple specification (will result in null if not specified).
      goto doAlternatives;
    }





    if ($specification->isA('d2d:FieldSpecification')) {

//          //DEBUG
//          if ($unboundRoles ) {
//            $unboundRoles2 = $unboundRoles; foreach($unboundRoles2 as &$unboundRole2) { $unboundRole2['object'] = (string) $unboundRole2['object']; };   var_dump($unboundRoles2); echo "\n\n\n\n\n";
//            $contentObjects2 = $contentObjects; foreach($contentObjects2 as &$contentObject2) { $contentObject2['object'] = (string) $contentObject2['object']; };   var_dump($contentObjects2); echo "\n\n\n\n\n";
//          }

      // Bind unbound roles and their objects to a content object if possible
      //foreach ($unboundRoles as $roleKey => $unboundRole) {   // TODO: DONE (see for statement below) Concider reversed looping, so roles that are added the latest are chosen first (matters only if branch measure is equal).
      for ($roleKey = count($unboundRoles) - 1; $roleKey >= 0; $roleKey--) { // NB: This way of doing a reversed loop is fast, but only works here because sequential numeric keys with base 0 are ensured.
        $unboundRole = $unboundRoles[$roleKey]; // NB: This line only if for loop is used
        foreach ($contentObjects as $objKey => $contentObject) {
          $roleName = $unboundRole['role'];
          if (!$contentObjects[$objKey]['roles'][$roleName] || isCloserMeasure($contentObjects[$objKey]['branchMeasure'], $unboundRole['branchMeasure'], $contentObjects[$objKey]['roles'][$roleName]['branchMeasure'])) {
            $contentObjects[$objKey]['roles'][$roleName] = $unboundRole;
            switch ($roleName) {
              case 'PrefArticleDef':
                // Role is a 'sticky' role that might need to be applied to multiple content objects; hence leave it in the unbound roles array
                break;
              case 'PrefSectionDef':
                // Role is a 'sticky' role that might need to be applied to multiple content objects; hence leave it in the unbound roles array
                break;
              case 'PrefRenderDef':
                // Role is a 'sticky' role that might need to be applied to multiple content objects; hence leave it in the unbound roles array
                break;
              default:
                // Remove the role and its object from the unbound roles array
                //unset($unboundRoles[$roleKey]);
                break;
            }
          }
        }
      }
      $unboundRoles = array_filter($unboundRoles); // Array is filtered for members containing null, 0, '', false, or empty array
    }




    // If this is the initial call, check to see if there is at least 1 content object to return
    if ($specification->isA('d2d:FieldSpecification')) {
      if (!$contentObjects) { // If the array is empty
        return null; // Since this call has been passed the FieldSpecification, we can say that there is no Content indicated for the FieldSpecification; hence the specification is not valid.
      }
    }

    // All conditions are met; return true to indicate success 
    return true;

  } else {
    // Handles the shortcut-case, in which the 'd2d:mustSatisy' property points directly to a predicate that needs to be used (i.e. not a resource of type 'd2d:TripleSpecification').
    $objects = persistentAll($subject, null, '<' . $specification->getUri() . '>', null, $neededLanguage, 0);
    if (!$objects) return null; // If the array is empty, this shorthand triple specification does not match. Since a shorthand triple specification can not hold an alternative by itself, and can not be optional (but the field specification can be), return null (the field specification can hold an alternative however).
    foreach($objects as $object) {
      $contentObjects[] = array('object' => $object, 'roles' => array());
    }
    return true;
  }


  return null; // This line should never be executed due to the return statements in both branches of the if statement above. This line is here for safety should the above code change.
  //------------- Handle alternative triple specifications
  doAlternatives:

  $alternativeSpecs = persistentAll($specification, null, 'd2d:hasAlternative', 'resource', null, 0);
  if (!$alternativeSpecs) {
    if ($isOptional) {
      return true;
    } else {
      return null; // If the array is empty, there is no alternative triple specification; return null;
    }
  } 

  foreach($alternativeSpecs as $alternativeSpec) {
    $altResult = validateTripleSpec($alternativeSpec, $subject,  $contentObjects, $unboundRoles, $encounteredRoles, $neededLanguage, $branchMeasure); // NON-RECURSIVE CALL FOR ALTERNATIVE
    if ($altResult !== null) return $altResult;
  }  

  if ($isOptional) {
    return true;
  } else {
    return null; // Apperantly, none of the specified alternative triple specs could be validated. 
  }
}



//-------------------------------------------------------------------------------------

function isCloserMeasure($basis, $candidate, $current) {
  $ba_el = reset($basis);
  $ca_el = reset($candidate);
  $cu_el = reset($current);

  do {
    if ($ca_el == $ba_el && $cu_el == $ba_el) {
      $ba_el = next($basis);
      $ca_el = next($candidate);
      $cu_el = next($current);
    } else {
      if ($ca_el == $ba_el) return true;

      return false;
    }
  } while ($ba_el !== false || $ca_el !== false || $cu_el !== false);

  return false;
}


//-------------------------------------------------------------------------------------


?>
