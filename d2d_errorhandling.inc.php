<?php

//-------------------------------------------------------------------------------------

function returnErrorPage($errorCode, $info = null) {

//  while (ob_get_level() > 0) { // Clean up all existing output buffers
//    ob_end_clean();
//  }

  getInfoForCode($errorCode, $protocol, $description);

  //ob_start();

  header($_SERVER["SERVER_PROTOCOL"] . ' ' . $protocol);
  echo "<!DOCTYPE html>";
  echo "<html><head><title>";
  echo $protocol;
  echo '</title></head><body>';
  if (D2D_SHOW_WARNINGS) echo Warnings::dumpWarnings(D2D_SHOW_WARNINGS);
  echo '<section><h1>';
  echo $protocol;
  echo '</h1><p>';
  echo $description;
  echo '</p></section>';
  if ($info !== null) {
    echo '<section style="margin-top: 50px;"><h4>Additional information</h4><p>';
    echo $info;
    echo '</p></section>';
  }
  echo '</body></html>';

//  ob_end_flush();

  exit();
}

//-------------------------------------------------------------------------------------

function addWarning($errorCode, $info = null) {

  Warnings::$messages[] = array('errorCode' => $errorCode, 'info' => $info);

}

//-------------------------------------------------------------------------------------

function getInfoForCode($errorCode, &$protocol, &$description) {

  switch ($errorCode) {

    case 404:
      $protocol = '404 Not Found';
      $description = 'That\'s an error; the requested resource was not found on this server.';
      return true;

    case 409:
      $protocol = '409 Conflict';
      $description = 'That\'s an error; the request could not be processed because of a conflict.';
      return true;

    case 422:
      $protocol = '422 Unprocessable Entity';
      $description = 'That\'s an error; the request could not be processed due to semantic errors. A referenced entity may be invalid or not found.';
      return true;

    case 500:
      $protocol = '500 Internal Server Error';
      $description = 'That\'s an error; the server is unable to handle the request because an unexpected condition was encountered.';   
      return true;

    case 503:
      $protocol = '503 Service Unavailable';
      $description = 'That\'s an error; the server is currently unable to handle the request due to temporary overloading or maintenance of the server.';   
      return true;
  }

  return false;

}

//-------------------------------------------------------------------------------------

class Warnings {
    public static $messages = array();

    public static function dumpWarnings($format = null) {

      $result = '';
      switch (mb_strToLower($format)) {

        case 'html':
          if (Warnings::$messages) {
            $result .= '<div><div style="margin-top: 5px; margin-left: 5px; margin-right: 5px; margin-bottom: 0px; padding: 4px; background-color: #ffd700;"><h3 style="margin-top: 0px; margin-bottom: -5px;">D2D PROCESSING - WARNINGS (' . count(Warnings::$messages) . ')</h3></div>';
            $result .= '<div style="margin-top: 0px; margin-left: 5px; margin-right: 5px; margin-bottom: 5px; padding: 5px; border: #ffd700 4px solid; background-color: #cccccc;">';
            foreach (Warnings::$messages as $message) {
              getInfoForCode($message['errorCode'], $protocol, $description);
              if ($notFirstItem) $result .= '<hr style="margin: 5px; border: #ffd700 1px solid" />';
              $result .= "<article style=\"margin-top: 40px;\"><h4 style=\"margin-top: -20px;\">$protocol</h4><p>$description</p>";
              if ($message['info'] !== null) $result .= '<section style="margin-top: 10px;"><h5>Additional information</h5><p>' . $message['info'] . '</p></section>';
              $result .= '</article>';
              $notFirstItem = true;
            }
            $result .= '</div></div>';
          }
          return $result;

        default:
          if (Warnings::$messages) {
            $result .= "<!--\n\n\n====================================== D2D PROCESSING - WARNINGS (" . count(Warnings::$messages) . ") ======================================\n";
            foreach (Warnings::$messages as $message) {
              getInfoForCode($message['errorCode'], $protocol, $description);
              if ($notFirstItem) $result .= "-------------------------------------------------------------------------------------------------------\n";
              $result .= mb_strToUpper("$protocol - $description\n");
              if ($message['info'] !== null) $result .= 'Additional information: ' . $message['info'] . "\n";
              $notFirstItem = true;
            }
            $result .= "=======================================================================================================\n\n\n-->";

          }
          return $result;
      }
    }

}

//-------------------------------------------------------------------------------------



?>
