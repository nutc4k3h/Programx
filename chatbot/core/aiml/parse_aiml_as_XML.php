<?php

  /***************************************
  * http://www.program-o.com
  * PROGRAM O
  * Version: 2.1.2
  * FILE: chatbot/core/aiml/parse_aiml_as_XML.php
  * AUTHOR: Elizabeth Perreau and Dave Morton
  * DATE: MAY 4TH 2011
  * DETAILS: this file contains the functions generate php code from aiml
  ***************************************/
  /**
  * function parse_aiml_as_XML()
  * This function starts the process of recursively parsing the AIML template as XML, converting it to text.
  * @param  array $convoArr - the existing conversation array
  * @return array $convoArr
  **/
  function parse_aiml_as_XML($convoArr)
  {
    global $botsay, $error_response;
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Parsing the AIML template as XML", 4);
    $template = add_text_tags($convoArr['aiml']['template']);
    try
    {
      $aimlTemplate = new SimpleXMLElement($template);
    }
    catch (exception $e)
    {
      trigger_error("There was a problem parsing the template as XML. Template value:\n$template", E_USER_WARNING);
      $aimlTemplate = new SimpleXMLElement("<text>$error_response</text>");
    }
    $responseArray = parseTemplateRecursive($convoArr, $aimlTemplate);
    $botsay = trim(implode_recursive(' ', $responseArray, __FILE__, __FUNCTION__, __LINE__));
    $botsay = str_replace(' .', '.', $botsay);
    $botsay = str_replace('  ', ' ', $botsay);
    $convoArr['aiml']['parsed_template'] = $botsay;
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Completed parsing the template. The bot will say: $botsay", 2);
    return $convoArr;
  }

  function add_text_tags($in)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    // Since we're going to parse the template's contents as XML, we need to prepare it first
    // by transforming it into valid XML
    // First, wrap the template in TEMPLATE tags, to give the text a "root" element:
    $template = "<template>$in</template>";
    // SimpleXML can't deal with "mixed" content, so any "loose" text is wrapped in a <text> tag.
    // The process will sometimes add extra <text> tags, so part of the process below deals with that.
    $textTagsToRemove = array('<text></text>' => '', '<text> </text>' => '', '<say>' => '', '</say>' => '',
    );
    // Remove spaces between the tags
    $template = preg_replace('~>\s*?<~', '><', $template);
    $textTagSearch = array_keys($textTagsToRemove);
    $textTagReplace = array_values($textTagsToRemove);
    // Remove CRLF
    $template = str_replace("\r\n", '', $template);
    // Remove newline
    $template = str_replace("\n", '', $template);
    // Throw <text> tags around everything that lies between existing tags
    $template = preg_replace('~>(.*?)<~', "><text>$1</text><", $template);
    // Remove any "extra" <text> tags that may have been generated
    $template = str_replace($textTagSearch, $textTagReplace, $template);
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Returning template:\n$template", 4);
    return $template;
  }

  function implode_recursive($glue, $in, $file = 'unknown', $function = 'unknown', $line = 'unknown')
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    runDebug(__FILE__, __FUNCTION__, __LINE__, "This function was called from $file, function $function at line $line.", 2);
    if (!is_array($in))
    {
      trigger_error('Input not array! Input = ' . print_r($in, true));
      return $in;
    }
    foreach ($in as $index => $element)
    {
      if (empty ($element))
        continue;
      if (is_array($element))
      {
        $in[$index] = implode_recursive($glue, $element, __FILE__, __FUNCTION__, __LINE__);
      }
    }
    $out = (is_array($in)) ? implode($glue, $in) : $in;
    if ($function != 'implode_recursive') runDebug(__FILE__, __FUNCTION__, __LINE__, "Imploding complete. Returning $out", 4);
    return ltrim($out);
  }

  function parseTemplateRecursive($convoArr, SimpleXMLElement $element, $level = 0)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    $HTML_tags = array('a', 'abbr', 'acronym', 'address', 'applet', 'area', 'b', 'bdo', 'big', 'blockquote', 'br', 'button', 'caption', 'center', 'cite', 'code', 'col', 'colgroup', 'dd', 'del', 'dfn', 'dir', 'div', 'dl', 'dt', 'em', 'fieldset', 'font', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'iframe', 'img', 'input', 'ins', 'kbd', 'label', 'legend', 'ol', 'object', 's', 'script', 'small', 'span', 'strike', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'textarea', 'tfoot', 'th', 'thead', 'tr', 'tt', 'u', 'ul');
    $doNotParseChildren = array('li');
    $response = array();
    $parentName = strtolower($element->getName());
    $children = $element->children();
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Processing element $parentName at level $level. element XML = " . $element->asXML(), 2);
    $func = 'parse_' . $parentName . '_tag';
    if (in_array($parentName, $HTML_tags))
      $func = 'parse_html_tag';
    if (function_exists($func))
    {
      if (!in_array(strtolower($parentName), $doNotParseChildren))
      {
        runDebug(__FILE__, __FUNCTION__, __LINE__, "Passing element $parentName to the $func function", 2);
        $retVal = $func($convoArr, $element, $parentName, $level);
        $retVal = (is_array($retVal)) ? $retVal = implode_recursive(' ', $retVal, __FILE__, __FUNCTION__, __LINE__) : $retVal;
        runDebug(__FILE__, __FUNCTION__, __LINE__, "Adding '$retVal' to the response array. tag name is $parentName", 2);
        $response[] = $retVal;
        return $response;
      }
    }
    else
    {
      $retVal = $element;
    }
    $value = trim((string) $retVal);
    $tmpResponse = ($level <= 1 and ($parentName != 'think') and (!in_array($parentName, $doNotParseChildren))) ? $value : '';
    if (count($children) > 0 and is_object($retVal))
    {
      $childLabel = (count($children) == 1) ? ' child' : ' children';
      foreach ($children as $child)
      {
        $childName = $child->getName();
        if (in_array(strtolower($childName), $doNotParseChildren)) continue;
        $tmpResponse = parseTemplateRecursive($convoArr, $child, $level + 1);
        $tmpResponse = implode_recursive(' ', $tmpResponse, __FILE__, __FUNCTION__, __LINE__);
        $tmpResponse = ($childName == 'think') ? '' : $tmpResponse;
        runDebug(__FILE__, __FUNCTION__, __LINE__, "Adding '$tmpResponse' to the response array. tag name is $parentName.", 2);
        $response[] = $tmpResponse;
      }
    }
    return $response;
  }

  function parse_text_tag($convoArr, $element, $parentName, $level)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    return (string) $element;
  }

  function parse_star_tag($convoArr, $element, $parentName, $level)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    $response = array();
    runDebug(__FILE__, __FUNCTION__, __LINE__, "parseStar called from the $parentName tag at level $level. element = " . $element->asXML(), 2);
    $attributes = $element->attributes();
    if (count($attributes) != 0)
    {
      $index = $element->attributes()->index;
    }
    else $index = 1;
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Star index = $index.", 2);
    $star = $convoArr['star'][(int) $index];
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Adding '$star' to the response array.", 2);
    $response[] = $star;
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Index value = $index, Star value = $star", 2);
    return $response;
  }

  function parse_date_tag($convoArr, $element, $parentName, $level)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    global $time_zone_locale;
    $isWindows = (DIRECTORY_SEPARATOR == '/') ? false : true;
    $now = time();
    $format = $element->attributes()->format;
    $locale = $element->attributes()->locale;
    $tz = $element->attributes()->timezone;
    $format = (string) $format;
    $locale = (string) $locale;
    $tz = (string) $tz;
    $tz = (empty ($tz)) ? $time_zone_locale : $tz;
    $hereNow = new DateTimeZone($tz);
    $ts = new DateTime("now", $hereNow);
    //exit("ts = " . print_r($ts->getTimestamp(), true));
    if (empty ($format))
    {
      $response = date($ts->getTimestamp());
    }
    else
    {
      if ($isWindows) $format = str_replace('%l', '%#I', $format);
      $response = strftime($format, $ts->getTimestamp());
    }
    return $response;
  }

  function parse_random_tag($convoArr, $element, $parentName, $level)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    $liArray = $element->xpath('li');
    $pick = array_rand($liArray);
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Picking option #$pick from random tag.\n", 4);
    $li = $liArray[$pick]->children();
    //$li = $li;
    $liTxt = $li->asXML();
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Chose '$liTxt' for output.", 2);
    return $li;
  }

  function parse_get_tag($convoArr, $element, $parentName, $level)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    global $con, $dbn;
    $response = '';
    $bot_id = $convoArr['conversation']['bot_id'];
    $user_id = $convoArr['conversation']['user_id'];
    $var_name = $element->attributes()->name;
    $var_name = ($var_name == '*') ? $convoArr['star'][1] : $var_name;
    if (empty ($var_name))
      $response = 'undefined';
    if (empty ($response))
    {
      $sql = "select `value` from `$dbn`.`client_properties` where `user_id` = $user_id and `bot_id` = $bot_id and `name` = '$var_name';";
      runDebug(__FILE__, __FUNCTION__, __LINE__, "Checking the DB for $var_name - sql:\n$sql", 2);
      $result = db_query($sql, $con);
      if (($result) and (mysql_num_rows($result) > 0))
      {
        $row = mysql_fetch_array($result);
        $response = $row['value'];
      }
      else
        $response = 'undefined';
    }
    runDebug(__FILE__, __FUNCTION__, __LINE__, "The value for $var_name is $response.", 2);
    return $response;
  }

  function parse_set_tag($convoArr, $element, $parentName, $level)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    global $con, $dbn, $user_name;
    $response = array();
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Processing element $parentName at level $level. element XML = " . $element->asXML(), 2);

    $children = $element->children();
    if (!empty ($children))
    {
      $response = parseTemplateRecursive($convoArr, $children, $level + 1);
    }
    else
    {
      $response[] = (string) $element;
    }
    $bot_id = $convoArr['conversation']['bot_id'];
    $user_id = $convoArr['conversation']['user_id'];
    $var_name = (string)$element->attributes()->name;
    $var_name = ($var_name == '*') ? $convoArr['star'][1] : $var_name;
    $vn_type = gettype($var_name);
    runDebug(__FILE__, __FUNCTION__, __LINE__, "var_name = $var_name and is type: $vn_type", 2);
    $var_value = implode_recursive(' ', $response, __FILE__, __FUNCTION__, __LINE__);
    if ($var_name == 'name')
    {
      $user_name = $var_value;
      $convoArr['client_properties']['name'] = $var_value;
      $convoArr['conversation']['user_name'] = $var_value;
      $sql = "UPDATE `$dbn`.`users` set `name` = '$var_value' where `id` = $user_id;";
      runDebug(__FILE__, __FUNCTION__, __LINE__, "Updating user name in the DB. SQL:\n$sql", 3);
      $result = db_query($sql, $con) or trigger_error('Error setting user name in ' . __FILE__ . ', function ' . __FUNCTION__ . ', line ' . __LINE__ . ' - Error message: ' . mysql_error());
      $numRows = mysql_affected_rows();
      $sql = "select `name` from `$dbn`.`users` where `id` = $user_id;";
      runDebug(__FILE__, __FUNCTION__, __LINE__, "Checking the users table to see if the value has changed. - SQL:\n$sql", 2);
      $result = db_query($sql, $con) or trigger_error('Error looking up DB info in ' . __FILE__ . ', function ' . __FUNCTION__ . ', line ' . __LINE__ . ' - Error message: ' . mysql_error());
      $rowCount = mysql_num_rows($result);
      if ($rowCount != 0)
      {
        $rows = mysql_fetch_assoc($result);
        $tmp_name = $rows['name'];
        runDebug(__FILE__, __FUNCTION__, __LINE__, "The value for the user's name is $tmp_name.", 2);
      }
    }
    else $convoArr['client_properties'][$var_name] = $var_value;
    $sql = "select `value` from `$dbn`.`client_properties` where `user_id` = $user_id and `bot_id` = $bot_id and `name` = '$var_name';";
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Checking the client_properties table for the value of $var_name. - SQL:\n$sql", 2);
    $result = db_query($sql, $con) or trigger_error('Error looking up DB info in ' . __FILE__ . ', function ' . __FUNCTION__ . ', line ' . __LINE__ . ' - Error message: ' . mysql_error());
    $rowCount = mysql_num_rows($result);
    if ($rowCount == 0)
    {
      $sql = "insert into `$dbn`.`client_properties` (`id`, `user_id`, `bot_id`, `name`, `value`)
      values (NULL, $user_id, $bot_id, '$var_name', '$var_value');";
      runDebug(__FILE__, __FUNCTION__, __LINE__, "No value found for $var_name. Inserting $var_value into the table.", 2);
    }
    else
    {
      $sql = "update `$dbn`.`client_properties` set `value` = '$var_value' where `user_id` = $user_id and `bot_id` = $bot_id and `name` = '$var_name';";
      runDebug(__FILE__, __FUNCTION__, __LINE__, "Value found for $var_name. Updating the table to  $var_value.", 2);
    }
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Saving to DB - SQL:\n$sql", 2);
    $result = db_query($sql, $con) or trigger_error('Error saving to db in ' . __FILE__ . ', function ' . __FUNCTION__ . ', line ' . __LINE__ . ' - Error message: ' . mysql_error());
    $rowCount = mysql_affected_rows();
    $response = $var_value;

    runDebug(__FILE__, __FUNCTION__, __LINE__, "Value for $var_name has ben set. Returning $var_value.", 2);
    return $response;
  }

  function parse_think_tag($convoArr, $element, $parentName, $level)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    $response = array();
    $children = $element->children();
    if (!empty ($children))
    {
      $response = parseTemplateRecursive($convoArr, $children, $level + 1);
    }
    return '';
  }

  function parse_bot_tag($convoArr, $element)
  {
    $attributeName = (string)$element->attributes()->name;
    $attributeName = ($attributeName == '*') ? $convoArr['star'][1] : $attributeName;
    $response = (!empty ($convoArr['bot_properties'][$attributeName])) ? $convoArr['bot_properties'][$attributeName] : 'undefined';
    return $response;
  }

  function parse_id_tag($convoArr, $element, $parentName, $level)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    return $convoArr['conversation']['convo_id'];
  }

  function parse_version_tag($convoArr, $element, $parentName, $level)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    return 'Program O version ' . VERSION;
  }

  function parse_uppercase_tag($convoArr, $element, $parentName, $level)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    $response = array();
    $children = $element->children();
    if (!empty ($children))
    {
      $response = parseTemplateRecursive($convoArr, $children, $level + 1);
    }
    else
    {
      $response[] = (string) $element;
    }
    $response_string = implode_recursive(' ', $response, __FILE__, __FUNCTION__, __LINE__);
    return ltrim(strtoupper($response_string), ' ');
  }

  function parse_lowercase_tag($convoArr, $element, $parentName, $level)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    $response = array();
    $children = $element->children();
    if (!empty ($children))
    {
      $response = parseTemplateRecursive($convoArr, $children, $level + 1);
    }
    else
    {
      $response[] = (string) $element;
    }
    $response_string = implode_recursive(' ', $response, __FILE__, __FUNCTION__, __LINE__);
    return ltrim(strtolower($response_string), ' ');
  }

  function parse_sentence_tag($convoArr, $element, $parentName, $level)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    $response = array();
    $children = $element->children();
    if (!empty ($children))
    {
      $response = parseTemplateRecursive($convoArr, $children, $level + 1);
    }
    else
    {
      $response[] = (string) $element;
    }
    $response_string = implode_recursive(' ', $response, __FILE__, __FUNCTION__, __LINE__);
    $response = ucfirst(strtolower($response_string));
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Response string was: $response_string. Transformed to $response.", 2);
    return $response;
  }

  function parse_formal_tag($convoArr, $element, $parentName, $level)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    $response = array();
    $children = $element->children();
    if (!empty ($children))
    {
      $response = parseTemplateRecursive($convoArr, $children, $level + 1);
    }
    else
    {
      $response[] = (string) $element;
    }
    $response_string = implode_recursive(' ', $response, __FILE__, __FUNCTION__, __LINE__);
    $response = ucwords(strtolower($response_string));
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Response string was: $response_string. Transformed to $response.", 2);
    return $response;
  }

  function parse_srai_tag($convoArr, $element, $parentName, $level)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    $response = array();
    $children = $element->children();
    if (!empty ($children))
    {
      $response = parseTemplateRecursive($convoArr, $children, $level + 1);
    }
    else
    {
      $response[] = (string) $element;
    }
    $response_string = implode_recursive(' ', $response, __FILE__, __FUNCTION__, __LINE__);
    $response = run_srai($convoArr, $response_string);
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Finished parsing SRAI tag', 2);
    $response_string = implode_recursive(' ', $response, __FILE__, __FUNCTION__, __LINE__);
    return $response_string;
  }

  function parse_sr_tag($convoArr, $element, $parentName, $level)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    $response = run_srai($convoArr, $convoArr['star'][1]);
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Finished parsing SRAI tag', 2);
    $response_string = implode_recursive(' ', $response, __FILE__, __FUNCTION__, __LINE__);
    return $response_string;
  }

  /*
  * function parse_condition_tag
  * parses the XML contained within the $element variable supplied, returning the apropriate value
  * @param [array] $convoArr
  * @return [string] $response_string
  */

  function parse_condition_tag($convoArr, $element, $parentName, $level)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    global $error_response;
    $response = array();
    $attrName = $element['name'];
    $attributes = (array)$element->attributes();
    $attributesArray = (isset($attributes['@attributes'])) ? $attributes['@attributes'] : array();
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Element attributes:' . print_r($attributesArray, true), 1);
    $attribute_count = count($attributesArray);
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Element attribute count = $attribute_count", 1);
    if ($attribute_count == 0) // Bare condition tag
    {
      runDebug(__FILE__, __FUNCTION__, __LINE__, 'Parsing a CONDITION tag with no attributes. XML = ' . $element->asXML(), 2);
      $liNamePath = 'li[@name]';
      $condition_xPath = '';
      $exclude = array();
      $choices = $element->xpath($liNamePath);
      foreach ($choices as $choice)
      {
        $choice_name = (string)$choice['name'];
        if (in_array($choice_name, $exclude)) continue;
        $exclude[] = $choice_name;
        runDebug(__FILE__, __FUNCTION__, __LINE__, 'Client properties = ' . print_r($convoArr['client_properties'], true), 2);
        $choice_value = get_client_property($convoArr, $choice_name);
        $condition_xPath .= "li[@name=\"$choice_name\"][@value=\"$choice_value\"]|";
      }
      $condition_xPath .= 'li[not(@*)]';
      runDebug(__FILE__, __FUNCTION__, __LINE__, "xpath search = $condition_xPath", 4);
      $pick_search = $element->xpath($condition_xPath);
      runDebug(__FILE__, __FUNCTION__, __LINE__, 'Pick array = ' . print_r($pick_search, true), 2);
      $pick_count = count($pick_search);
      runDebug(__FILE__, __FUNCTION__, __LINE__, "Pick count = $pick_count.", 2);
      $pick = $pick_search[0];
    }
    elseif (array_key_exists('value', $attributesArray) or array_key_exists('contains', $attributesArray) or array_key_exists('exists', $attributesArray)) // condition tag with either VALUE, CONTAINS or EXISTS attributes
    {
      runDebug(__FILE__, __FUNCTION__, __LINE__, 'Parsing a CONDITION tag with 2 attributes.', 2);
      $condition_name = (string)$element['name'];
      $test_value = get_client_property($convoArr, $condition_name);
      switch (true)
      {
        case (isset($element['value'])):
          $condition_value = (string)$element['value'];
          break;
        case (isset($element['value'])):
          $condition_value = (string)$element['value'];
          break;
        case (isset($element['value'])):
          $condition_value = (string)$element['value'];
          break;
        default:
          runDebug(__FILE__, __FUNCTION__, __LINE__, 'Something went wrong with parsing the CONDITION tag. Returning the error response.', 1);
          return $error_response;
      }
      $pick = ($condition_value == $test_value) ? $element : '';
    }
    elseif (array_key_exists('name', $attributesArray)) // this ~SHOULD~ just trigger if the NAME value is present, and ~NOT~ NAME and (VALUE|CONTAINS|EXISTS)
    {
      runDebug(__FILE__, __FUNCTION__, __LINE__, 'Parsing a CONDITION tag with only the NAME attribute.', 2);
      $condition_name = (string)$element['name'];
      $test_value = get_client_property($convoArr, $condition_name);
      $path = "li[@value=\"$test_value\"]|li[not(@*)]";
      runDebug(__FILE__, __FUNCTION__, __LINE__, "search string = $path", 4);
      $choice = $element->xpath($path);
      $pick = $choice[0];
      runDebug(__FILE__, __FUNCTION__, __LINE__, 'Found a match. Pick = ' . print_r($choice, true), 4);
    }
    else // nothing matches
    {
      runDebug(__FILE__, __FUNCTION__, __LINE__, 'No matches found. Returning default error response.', 1);
      return $error_response;
    }
    $children = (is_object($pick)) ? $pick->children() : null;
    if (!empty ($children))
    {
      $response = parseTemplateRecursive($convoArr, $children, $level + 1);
    }
    else
    {
      $response[] = (string) $pick;
    }
    $response_string = implode_recursive(' ', $response);
    return $response_string;
  }

  function parse_person_tag($convoArr, $element, $parentName, $level)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    $response = array();
    $children = $element->children();
    if (!empty ($children))
    {
      $response = parseTemplateRecursive($convoArr, $children, $level + 1);
    }
    else
    {
      $response[] = $convoArr['star'][1];
    }
    $response_string = implode_recursive(' ', $response, __FILE__, __FUNCTION__, __LINE__);
    $response = swapPerson($convoArr, 3, $response_string);
    return $response;
  }

  function parse_person2_tag($convoArr, $element, $parentName, $level)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    $response = array();
    $children = $element->children();
    if (!empty ($children))
    {
      $response = parseTemplateRecursive($convoArr, $children, $level + 1);
    }
    else
    {
      $response[] = $convoArr['star'][1];
    }
    $response_string = implode_recursive(' ', $response, __FILE__, __FUNCTION__, __LINE__);
    $response = swapPerson($convoArr, 2, $response_string);
    return $response;
  }

  function parse_html_tag($convoArr, $element, $parentName, $level)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    return (string) $element->asXML();
  }

  function parse_gender_tag($convoArr, $element, $parentName, $level)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    $response = array();
    $children = $element->children();
    if (!empty ($children))
    {
      $response = parseTemplateRecursive($convoArr, $children, $level + 1);
    }
    else
    {
      $response[] = $convoArr['star'][1];
    }
    $response_string = implode_recursive(' ', $response, __FILE__, __FUNCTION__, __LINE__);
    $response_string = " $response_string";
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Original response string = '$response_string'", 2);
    $nounList = $convoArr['nounList'];
    foreach ($nounList as $noun)
    {
      // This fixes (most) possessives
      $response_string = str_replace(" his $noun ", " x_her $noun ", $response_string);
    }
    $male2tmp = array('he ' => ' x_she ', ' his ' => ' x_hers ', ' him ' => ' x_her ', ' He ' => ' x_She ', ' His ' => ' x_Hers ', ' Him ' => ' x_Her ', 'he!' => ' x_she!', ' his!' => ' x_hers!', ' him!' => ' x_her!', ' He!' => ' x_She!', ' His!' => ' x_Hers!', ' Him!' => ' x_Her!', 'he,' => ' x_she,', ' his,' => ' x_hers,', ' him,' => ' x_her,', ' He,' => ' x_She,', ' His,' => ' x_Hers,', ' Him,' => ' x_Her,', 'he?' => ' x_she?', ' his?' => ' x_hers?', ' him?' => ' x_her?', ' He?' => ' x_She?', ' His?' => ' x_Hers?', ' Him?' => ' x_Her?',);
    $female2male = array(' she ' => ' he ', ' hers ' => ' his ', ' her ' => ' him ', ' She ' => ' He ', ' Hers ' => ' His ', ' Her ' => ' Him ', ' she.' => 'he.', ' hers.' => ' his.', ' her.' => ' him.', ' She.' => ' He.', ' Hers.' => ' His.', ' Her.' => ' Him.', ' she,' => 'he,', ' hers,' => ' his,', ' her,' => ' him,', ' She,' => ' He,', ' Hers,' => ' His,', ' Her,' => ' Him,', ' she!' => 'he!', ' hers!' => ' his!', ' her!' => ' him!', ' She!' => ' He!', ' Hers!' => ' His!', ' Her!' => ' Him!', ' she?' => 'he?', ' hers?' => ' his?', ' her?' => ' him?', ' She?' => ' He?', ' Hers?' => ' His?', ' Her?' => ' Him?',);
    $tmp2male = array(' x_he' => ' he', ' x_she' => ' she', ' x_He' => ' He', ' x_She' => ' She',);
    $m2tSearch = array_keys($male2tmp);
    $m2tReplace = array_values($male2tmp);
    $response_string = str_replace($m2tSearch, $m2tReplace, $response_string);
    $f2mSearch = array_keys($female2male);
    $f2mReplace = array_values($female2male);
    $response_string = str_replace($f2mSearch, $f2mReplace, $response_string);
    $t2mSearch = array_keys($tmp2male);
    $t2mReplace = array_values($tmp2male);
    $response_string = str_replace($t2mSearch, $t2mReplace, $response_string);
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Transformed response string = '$response_string'", 2);
    return $response_string;
  }

  function parse_that_tag($convoArr, $element, $parentName, $level)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    $element = $element->that;
    $index = $element['index'];
    $index = (!empty ($index)) ? $index : 1;
    runDebug(__FILE__, __FUNCTION__, __LINE__, "index = $index.", 4);
    if (!is_numeric($index))
    {
      list($idx1, $idx2) = explode(',', $index, 2);
      $idx2 = ltrim($idx2);
      $response = $convoArr['that'][$idx1][$idx2];
    }
    else
      $response = $convoArr['that'][$index];
    $response_string = implode_recursive(' ', $response, __FILE__, __FUNCTION__, __LINE__);
    return $response_string;
  }

  /*
   * function parse_system_tag
   * Executes system calls, returning the results.
   * @param (array) $convoArr
   * @param (SimpleXMLelement) $element
   * @param (string) $parentName
   * @param (int) $level
   * @return (string) $response_string
   */

  function parse_system_tag($convoArr, $element, $parentName, $level)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    $response = array();
    $children = $element->children();
    if (!empty ($children))
    {
      $response = parseTemplateRecursive($convoArr, $children, $level + 1);
    }
    else
    {
      $response[] = (string) $element;
    }
    $system_call = implode_recursive(' ', $response, __FILE__, __FUNCTION__, __LINE__);
    $response_string = shell_exec($system_call);
    return $response_string;
  }

  /*
   * function parse_learn_tag
   * Loads an AIML file into the DB
   * @param (array) $convoArr
   * @param (SimpleXMLelement) $element
   * @param (string) $parentName
   * @param (int) $level
   * @return (string) $response_string
   */

  function parse_learn_tag($convoArr, $element, $parentName, $level)
  {
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Starting function and setting timestamp.', 2);
    global $dbn, $con;
    $failure = false;
    $fileName = (string) $element['filename'];
    if (empty($fileName)) $falure = 'Filename attribute is empty!';
    $uploaded_file = _UPLOAD_PATH_ . $fileName;
    if (!file_exists($uploaded_file)) $failure = "File $fileName does not exist in the upload path!";
    else
    {
      $aiml = simplexml_load_file($uploaded_file);
      if (!$aiml) $failure = "Could not parse file $uploaded_file as XML!";
      else
      {
        $sqlTemplate = "insert into `$dbn`.`aiml` (`id`, `bot_id`, `aiml`, `pattern`, `thatpattern`, `template`, `topic`, `filename`, `php_code`)
values (NULL, $bot_id, '[aiml]', '[pattern]', '[that]', '[template]', '[topic]', '$fileName', '');";
        foreach ($aiml->topic as $topic)
        {
          $topicName = $topic['name'];
          foreach ($topic as $category)
          {
            $catXML  = $category->asXML();
            $pattern = $category->pattern->asXML();
            $thatpattern = $category->that->asXML();
            $template = $category->template->asXML();
            $sql = str_replace('[aiml]', $catXML, $sqlTemplate);
            $sql = str_replace('[pattern]', $pattern, $sql);
            $sql = str_replace('[that]', $thatpattern, $sql);
            $sql = str_replace('[template]', $template, $sql);
            $sql = str_replace('[topic]', $topicName, $sql);
          }
        }
      }
    }
  }


?>