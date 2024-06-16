<?php
/***************************************
 * http://www.program-o.com
 * PROGRAM O
 * Version: 2.6.5
 * FILE: find_aiml.php
 * AUTHOR: Elizabeth Perreau and Dave Morton
 * DATE: FEB 01 2016
 * DETAILS: Contains functions that find and score the most likely AIML match from the database
 ***************************************/

/**
 * Gets the last word from a sentence
 *
 * @param  string $sentence
 * @return string
 **/
function get_last_word($sentence)
{
    $wordArr = explode(' ', $sentence);
    $last_word = trim($wordArr[count($wordArr) - 1]);
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Sentence: $sentence. Last word:$last_word", 4);

    return $last_word;
}

/**
 * Gets the first word from a sentence
 * @param  string $sentence
 * @return string
 **/
function get_first_word($sentence)
{
    $wordArr = explode(' ', $sentence);
    $first_word = trim($wordArr[0]);
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Sentence: $sentence. First word:$first_word", 4);

    return $first_word;
}

/**
 * function make_like_pattern_regex
 *
 * Creates a REGEXP pattern, rather than a "like" pattern
 *
 * @param string $sentence
 * @param string $field
 *
 * @return string
 */

function make_like_pattern_regex($sentence, $field)
{
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Making a like pattern that uses REGEXP.", 4);
    $cwTmp = file(_CONF_PATH_ . 'commonWords.dat', FILE_IGNORE_NEW_LINES);
    $regExSearch = array();
    $replace = '';
    $out = "\n $field REGEXP ";
    foreach ($cwTmp as $word)
    {
        $regExSearch[] = "~\b{$word}\b~i";
    }
    save_file(_LOG_PATH_ . 'regex.txt', print_r($regExSearch, true));
    // $test = preg_replace($search, $replace, $testSentence);
    $strippedSentence = preg_replace($regExSearch, $replace, $sentence);
    while (strpos($strippedSentence, '  ') !== false)
    {
        $strippedSentence = str_replace('  ', ' ', trim($strippedSentence));
    }
    save_file(_LOG_PATH_ . 'strippedSentence.txt', print_r($strippedSentence, true));

    If (empty($strippedSentence)) return '';
    $append = str_replace(' ', '|', trim($strippedSentence));
    $out .= "'$append'";
    runDebug(__FILE__, __FUNCTION__, __LINE__, "returning like pattern:\n{$out}", 4);
    return $out;

}

/**
 * function make_like_pattern
 * Gets an input sentence from the user and a aiml tag and creates an sql like pattern
 * @param  string $sentence
 * @param  string $field
 * @return string
 **/
function make_like_pattern($sentence, $field)
{
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Making a like pattern to use in the sql", 4);
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Transforming $field: " . print_r($sentence, true), 4);

    $sql_like_pattern = "\n";
    $i = 0;

    //if the sentence is contained in an array extract the actual text sentence
    if (is_array($sentence))
    {
        $sentence = implode_recursive(' ', $sentence, __FILE__, __FUNCTION__, __LINE__);
    }

    $words = explode(" ", $sentence);

    runDebug(__FILE__, __FUNCTION__, __LINE__, "word list:\n" . pretty_print_r($words), 4);
    $count_words = count($words) - 1;
    $first_word = $words[0];

    if ($count_words == 0)
    {
        return " `$field` like '$first_word'\n";
    }

    $last_word = $words[$count_words];
    $tmpLike = '';

    $sql_like_pattern .= "  `$field` like '$first_word % $last_word' OR\n";
    $sql_like_pattern .= "  `$field` like '$first_word %' OR\n";

    $wordsArr = explode(" ", $sentence);
    $totalWordCount = count($wordsArr);
    $likePatternArr = array();

    for ($i = 0; $i < $totalWordCount; $i++)
    {
        $twoUp = $i + 2;
        $oneUp = $i + 1;
        $oneDown = $i - 1;

        if (isset($wordsArr[$twoUp]))
        {
            $middleWord = $wordsArr[$oneUp];
            $likePatternArr[] = "(`$field` LIKE '% " . $middleWord . " %')";
        }

        if ($oneDown >= 0)
        {
            $likePatternOneArr = $wordsArr;
            $likePatternOneArr[$i] = '%';
            $likePatternOne = implode(' ', $likePatternOneArr);
            $likePatternArr[] = "(`$field` LIKE '" . trim(strstr($likePatternOne, '%', true)) . " %')";
        }

        if ($oneUp < $totalWordCount)
        {
            $likePatternOneArr = $wordsArr;
            $likePatternOneArr[$i] = '%';
            $likePatternOne = implode(' ', $likePatternOneArr);
            $likePatternArr[] = "(`$field` LIKE '" . trim(strstr($likePatternOne, '%', false)) . "' )";
        }
    }

    $newSqlPatterns = implode(' OR ', $likePatternArr) . ' OR ';
    $sql_like_pattern .= $newSqlPatterns;

    runDebug(__FILE__, __FUNCTION__, __LINE__, "returning like pattern:\n$sql_like_pattern", 4);

    return rtrim($sql_like_pattern, ' OR ') . '     ';
}

/**
 * Counts the words in a sentence
 * @param  string $sentence
 * @return int
 **/
function wordsCount_inSentence($sentence)
{
    $wordArr = explode(" ", $sentence);
    $wordCount = count($wordArr);
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Sentence: $sentence numWords:$wordCount", 4);

    return $wordCount;
}

/**
 * Takes all the sql results passed to the function and filters out the irrelevant ones
 *
 * @param array $convoArr
 * @param array $allrows
 * @param string $lookingfor
 * @internal param string $current_thatpattern
 * @internal param string $current_topic
 * @return array
 **/

function unset_all_bad_pattern_matches($convoArr, $allrows, $lookingfor)
{
    global $error_response;

    $lookingfor_lc = _strtolower($lookingfor);
    $current_topic = get_topic($convoArr);
    $current_topic_lc = _strtolower($current_topic);
    $current_thatpattern = (isset ($convoArr['that'][1][1])) ? $convoArr['that'][1][1] : '';

    //file_put_contents(_LOG_PATH_ . 'allrows.txt', print_r($allrows, true));
    if (!empty($current_thatpattern))
    {
        runDebug(__FILE__, __FUNCTION__, __LINE__, "Current THAT = $current_thatpattern", 1);
    }

    $default_pattern = $convoArr['conversation']['default_aiml_pattern'];
    $default_pattern_lc = _strtolower($default_pattern);
    $tmp_rows = array();
    $relevantRows = array();
    //if default pattern keep
    //if direct pattern match keep
    //if wildcard or direct pattern match and direct or wildcard thatpattern match keep
    //if wildcard pattern matches found aiml keep
    //the end......
    $nfARc = number_format(count($allrows));
    runDebug(__FILE__, __FUNCTION__, __LINE__, "NEW FUNC Searching through " . $nfARc . " rows to unset bad matches", 4);

    // If no pattern was found, exit early
    if (($allrows[0]['pattern'] == "no results") && (count($allrows) == 1))
    {
        $tmp_rows[0] = $allrows[0];
        $tmp_rows[0]['score'] = 1;
        runDebug(__FILE__, __FUNCTION__, __LINE__, "Returning error as no results where found", 1);

        return $tmp_rows;
    }

    //loop through the results array
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Blue 5 to Blue leader. Starting my run now!', 4);
    $i = 0;

    foreach ($allrows as $subrow)
    {
      //get the pattern
      $aiml_pattern = _strtolower($subrow['pattern']);
      if (strstr($aiml_pattern, '<bot')) $aiml_pattern = get_bot_predicate($aiml_pattern);
      $aiml_pattern_wildcards = build_wildcard_RegEx($aiml_pattern, 'pattern');

      //get the that pattern
      $aiml_thatpattern = _strtolower($subrow['thatpattern']);
      $current_thatpattern = _strtolower($current_thatpattern);
      //get topic pattern
      $topicMatch = FALSE;
      $aiml_topic = _strtolower(trim($subrow['topic']));

        #Check for a matching topic
        $aiml_topic_wildcards = (!empty($aiml_topic)) ? build_wildcard_RegEx($aiml_topic, 'topic') : '';

        if ($aiml_topic == '')
        {
            $topicMatch = TRUE;
        }
        elseif (($aiml_topic == $current_topic_lc))
        {
            $topicMatch = TRUE;
        }
        elseif (!empty($aiml_topic_wildcards))
        {
            preg_match($aiml_topic_wildcards, $current_topic_lc, $matches);
            $topicMatch = (count($matches) > 0) ? true : false;
        }
        else {
            $topicMatch = FALSE;
        }

        # check for a matching pattern
        set_error_handler('wildcard_handler', E_ALL);
        if (false === preg_match($aiml_pattern_wildcards, $lookingfor, $matches))
        {
            save_file(_LOG_PATH_ . 'aiml_pattern_wildcards.txt', print_r($aiml_pattern_wildcards, true) . "\n", true);
        }
        restore_error_handler();
        $aiml_patternmatch = (count($matches) > 0) ? true : false;

        # look for a thatpattern match
        $aiml_thatpattern_wildcards = (!empty($aiml_thatpattern)) ? build_wildcard_RegEx($aiml_thatpattern, 'thatpattern') : '';
        $aiml_thatpattern_wc_matches = (!empty($aiml_thatpattern_wildcards)) ? preg_match_all($aiml_thatpattern_wildcards, $current_thatpattern, $matches) : 0;

        switch (true)
        {
            case ($aiml_thatpattern_wc_matches > 0):
            case ($current_thatpattern == $aiml_thatpattern):
                $aiml_thatpatternmatch = true;
                break;
            default:
                $aiml_thatpatternmatch = false;
        }

        if ($aiml_pattern == $default_pattern_lc)
        {
            //if it is a direct match with our default pattern then add to tmp_rows
            $tmp_rows[$i]['score'] = 1;
            $tmp_rows[$i]['track_score'] = "default pick up line ($aiml_pattern = $default_pattern) ";
        }
        elseif ((!$aiml_thatpattern_wildcards) && ($aiml_patternmatch)) // no thatpattern and a pattern match keep
        {
            $tmp_rows[$i]['score'] = 1;
            $tmp_rows[$i]['track_score'] = " no thatpattern in result and a pattern match";
        }
        elseif (($aiml_thatpattern_wildcards) && ($aiml_thatpatternmatch) && ($aiml_patternmatch)) //pattern match and a wildcard match on the thatpattern keep
        {
            $tmp_rows[$i]['score'] = 1;
            $tmp_rows[$i]['track_score'] = " thatpattern wildcard match and a pattern match";
        }
        elseif (($aiml_thatpatternmatch) && ($aiml_patternmatch)) //pattern match and a generic match on the thatpattern keep
        {
            $tmp_rows[$i]['score'] = 1;
            $tmp_rows[$i]['track_score'] = " thatpattern match and a pattern match";
        }
        elseif ($aiml_pattern == $lookingfor_lc) {
            $tmp_rows[$i]['score'] = 1;
            $tmp_rows[$i]['track_score'] = " direct pattern match";
        }
        else {
            $tmp_rows[$i]['score'] = -1;
            $tmp_rows[$i]['track_score'] = "dismissing nothing is matched";
        }

        if ($topicMatch === FALSE)
        {
            $tmp_rows[$i]['score'] = -1;
            $tmp_rows[$i]['track_score'] = "dismissing wrong topic";
        }

        if ($tmp_rows[$i]['score'] >= 0)
        {
            $relevantRows[] = $subrow;
        }
        $i++;
    }

    $rrCount = count($relevantRows);

    if ($rrCount == 0)
    {
        $i = 0;

        runDebug(__FILE__, __FUNCTION__, __LINE__, "Error: FOUND NO AIML matches in DB", 1);

        $relevantRows[$i]['aiml_id'] = "-1";
        $relevantRows[$i]['bot_id'] = "-1";
        $relevantRows[$i]['pattern'] = "no results";
        $relevantRows[$i]['thatpattern'] = '';
        $relevantRows[$i]['topic'] = '';
        $relevantRows[$i]['score'] = 0;
    }

    sort2DArray("show top scoring aiml matches", $relevantRows, "good matches", 1, 10);

    runDebug(__FILE__, __FUNCTION__, __LINE__, "Found " . count($relevantRows) . " relevant rows", 4);
    //file_put_contents(_LOG_PATH_ . 'relevantRow.txt', print_r($relevantRows, true));

    return $relevantRows;
}

/**
 * function build_wildcard_RegEx
 * Takes a sentence and converts AIML wildcards to Regular Expression wildcards
 * so that it can be matched in php using pcre search functions
 *
 * @param string $item
 * @param string $what
 *
 * @return string
 **/
function build_wildcard_RegEx($item, $what = 'unknown')
{
    runDebug(__FILE__, __FUNCTION__, __LINE__, "building wildcards for '{$what}'.", 2);
    $item = trim($item);
    $item = str_replace("*", ")(.*)(", $item);
    $item = str_replace("_", ")(.*)(", $item);
    $item = str_replace("+", "\+", $item);
    $item = str_replace("/", "\/", $item);
    $item = "(" . str_replace(" ", "\s", $item) . ")";
    $item = str_replace("()", '', $item);
    $matchme = "/^" . $item . "$/ui";

    return $matchme;
}

/**
 * Performs a pattern matching pass on an input string, checking
 * whether it matches a given pattern based on the AIML specification.
 *
 * @param string $pattern Pattern to match.
 * @param string $input Input string to check.
 * @return bool True if the string matches the pattern, false otherwise.
 **/
function aiml_pattern_match($pattern, $input)
{
    if (empty($input)) {
        return false;
    }

    $pattern_regex = build_wildcard_RegEx(_strtolower($pattern), 'pattern');

    return preg_match($pattern_regex, _strtolower($input)) === 1;
}

/**
 * Takes all the relevant sql results and scores them to find the most likely match with the aiml
 *
 * @param array $convoArr
 * @param array $allrows
 * @param string $pattern
 * @internal param int $bot_parent_id
 * @internal param string $current_thatpattern
 * @internal param string $current_topic
 * @return array $allrows
 **/
function score_matches($convoArr, $allrows, $pattern)
{
    global $common_words_array;

    runDebug(__FILE__, __FUNCTION__, __LINE__, "Scoring the matches.", 4);

    # obtain some values to test
    $topic = get_topic($convoArr);
    $that = (isset ($convoArr['that'][1][1])) ? $convoArr['that'][1][1] : '';
    $default_pattern = $convoArr['conversation']['default_aiml_pattern'];
    $bot_parent_id = $convoArr['conversation']['bot_parent_id'];
    $bot_id = $convoArr['conversation']['bot_id'];

    # set the scores for each type of word or sentence to be used in this function

    # full pattern match scores:
    $this_bot_match                 = 250;
    $underscore_match               = 100;
    $topic_underscore_match         = 80;
    $topic_direct_match             = 50;
    $topic_star_match               = 10;
    $thatpattern_underscore_match   = 45;
    $thatpattern_direct_match       = 15;
    $thatpattern_star_match         = 2;
    $direct_pattern_match           = 10;
    $pattern_direct_match           = 7;
    $pattern_star_match             = 1;
    $default_pattern_match          = 5;

    # individual word match scores:
    $uncommon_word_match        = 8;
    $common_word_match          = 1;
    $direct_word_match          = 2;
    $underscore_word_match      = 25;
    $star_word_match            = 1;
    $rejected                   = -1000;

    # loop through all relevant results
    foreach ($allrows as $all => $subrow)
    {
        $category_bot_id        = isset($subrow['bot_id']) ? $subrow['bot_id'] : 1;
        $category_topic         = $subrow['topic'];
        $category_thatpattern   = $subrow['thatpattern'];
        $category_pattern       = $subrow['pattern'];
        $check_pattern_words    = true;

        # make it all lower case, to make it easier to test, and do it using mbstring functions if possible
        $category_pattern_lc        = _strtolower($category_pattern);
        $category_thatpattern_lc    = _strtolower($category_thatpattern);
        $category_topic_lc          = _strtolower($category_topic);
        $default_pattern_lc         = _strtolower($default_pattern);
        $pattern_lc                 = _strtolower($pattern);
        $topic_lc                   = _strtolower($topic);
        $that_lc                    = _strtolower($that);

        // Start scoring here
        $current_score = 0;
        $track_matches = '';

        # 1.) Check for current bot, rather than parent
        if ($category_bot_id == $bot_id)
        {
            $current_score += $this_bot_match;
            $track_matches .= 'current bot, ';
        }
        elseif ($category_bot_id == $bot_parent_id)
        {
            $current_score += 0;
            $track_matches .= 'parent bot, ';
        }
        else # if it's not the current bot and not the parent bot, then reject it and log a debug message (this should never happen)
        {
            $current_score = $rejected;
            runDebug(__FILE__, __FUNCTION__, __LINE__, 'Found an error trying to identify the chatbot.', 1);

            unset($allrows[$all]);
            continue;
        }

        # 2.) test for a non-empty  current topic
        if (!empty($topic))
        {
            # 2a.) test for a non-empty topic in the current category
            if (empty($category_topic) || $category_topic == '*')
            {
                // take no action, as we're not looking for a topic here
                $track_matches .= 'no topic to match, ';
            }
            else
            {
                # 2b.) create a RegEx to test for underscore matches
                if (strpos($category_topic, '_') !== false)
                {
                    $regEx = str_replace('_', '(.*)', $category_topic);
                    if ($regEx != $category_topic && preg_match("/$regEx/i", $topic) === 1)
                    {
                        $current_score += $topic_underscore_match;
                        $track_matches .= 'topic match with underscore, ';
                    }
                } # 2c.) Check for a direct topic match
                elseif ($topic == $category_topic)
                {
                    $current_score += $topic_direct_match;
                    $track_matches .= 'direct topic match, ';
                } # 2d.) Check topic for a star wildcard match
                else
                {
                    $regEx = str_replace(array('*', '_'), '(.*)', $category_topic);

                    if (preg_match("/$regEx/i", $topic))
                    {
                        $current_score += $topic_star_match;
                        $track_matches .= 'topic match with wildcards, ';
                    }
                }
            }
        }
        else
        {
            // take no action, as we're not looking for a topic here
            $track_matches .= 'no topic to match, ';
        } # end topic testing

        # 3.) test for a category thatpattern
        if (empty($category_thatpattern) || $category_thatpattern == '*')
        {
            $current_score += 1;
            $track_matches .= 'no thatpattern to match, ';
        }
        else
        {
            if (strpos($category_thatpattern, '_') !== false)
            {
                # 3a.) Create a RegEx to search for underscore wildcards
                $regEx = str_replace('_', '(.*)', $category_thatpattern);

                if ($regEx !== $category_thatpattern && preg_match("/$regEx/i", $that) === 1)
                {
                    $current_score += $thatpattern_underscore_match;
                    $track_matches .= 'thatpattern match with underscore, ';
                }
            } # 3b.) direct thatpattern match
            elseif ($that_lc == $category_thatpattern_lc)
            {
                $current_score += $thatpattern_direct_match;
                $track_matches .= 'direct thatpattern match, ';
            } # 3c.) thatpattern star matches
            elseif (strstr($category_thatpattern_lc, '*') !== false)
            {
                $regEx = str_replace('*', '(.*)', $category_thatpattern);

                if (preg_match("/$regEx/i", $that))
                {
                    $current_score += $thatpattern_star_match;
                    $track_matches .= 'thatpattern match with star, ';
                }
            } #3d.) no match at all
            else
            {
                $current_score = $rejected;
                $track_matches .= 'no thatpattern match at all, ';
                //runDebug(__FILE__, __FUNCTION__, __LINE__, "Matching '$that_lc' with '$category_thatpattern_lc' failed. Drat!'", 4);
            }
        } # end thatpattern testing

        # 4.) pattern testing

        # 4a.) Create a RegEx to search for underscore wildcards
        if (strpos($category_pattern, '_') !== false)
        {
            $regEx = str_replace('_', '(.*)', $category_pattern);
            //save_file(_LOG_PATH_ . 'regex.txt', "$regEx\n", true);
            if ($regEx != $category_pattern && preg_match("/$regEx/i", $pattern) === 1)
            {
                $current_score += $underscore_match;
                $track_matches .= 'pattern match with underscore, ';
            }
        } # 4b.) direct pattern match
        elseif ($pattern == $category_pattern)
        {
            $current_score += $pattern_direct_match;
            $track_matches .= 'direct pattern match, ';
            //$check_pattern_words  = false;
        } # 4c.) pattern star matches
        else
        {
            $regEx = str_replace(array('*', '_'), '(.*?)', $category_pattern);
            if ($category_pattern == '*')
            {
                $current_score += $pattern_star_match;
                $track_matches .= 'pattern star match, ';
                $check_pattern_words = false;
            }
            elseif ($regEx != $category_pattern && (($category_pattern != '*') || ($category_pattern != '_')) && preg_match("/$regEx/i", $pattern) != 0)
            { }
        } # end pattern testing

        # 4d.) See if the current category is the default category
        if ($category_pattern == $default_pattern_lc)
        {
            runDebug(__FILE__, __FUNCTION__, __LINE__, 'This category is the default pattern!', 4);

            $current_score += $default_pattern_match;
            $track_matches .= 'default pattern match, ';
            $check_pattern_words = false;
        }

        #5.) check to see if we need to score word by word

        if ($check_pattern_words && $category_pattern_lc != $default_pattern_lc)
        {
            # 5a.) first, a little setup
            $pattern_lc = _strtolower($pattern);
            $category_pattern_lc = _strtolower($category_pattern);
            $pattern_words = explode(" ", $pattern_lc);

            # 5b.) break the input pattern into an array of individual words and iterate through the array
            $category_pattern_words = explode(" ", $category_pattern_lc);
            foreach ($category_pattern_words as $word)
            {
                $word = trim($word);
                switch (true)
                {
                    case ($word === '_'):
                        $current_score += $underscore_word_match;
                        $track_matches .= 'underscore word match, ';
                        break;
                    case ($word === '*'):
                        $current_score += $star_word_match;
                        $track_matches .= 'star word match, ';
                        break;
                    case (in_array($word, $pattern_words)):
                        $current_score += $direct_word_match;
                        $track_matches .= "direct word match: $word, ";
                        break;
                    case (in_array($word, $common_words_array)):
                        $current_score += $common_word_match;
                        $track_matches .= "common word match: $word, ";
                        break;
                    default:
                        $current_score += $uncommon_word_match;
                        $track_matches .= "uncommon word match: $word, ";
                }
            }
        }

        $allrows[$all]['score'] += $current_score;
        $allrows[$all]['track_score'] = rtrim($track_matches, ', ');
    }
    //runDebug(__FILE__, __FUNCTION__, __LINE__, "Unsorted array \$allrows:\n" . print_r($allrows, true), 4);
    $allrows = sort2DArray("show top scoring aiml matches", $allrows, "score", 1, 10);
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Sorted array \$allrows:\n" . print_r($allrows, true), 4);

    return $allrows;
}

/**
 * Small helper function to sort a 2d array
 *
 * @param string $opName
 * @param array $thisArr
 * @param $sortByItem
 * @param $sortAsc
 * @param $limit
 * @return array
 **/
function sort2DArray($opName, $thisArr, $sortByItem, $sortAsc = 1, $limit = 10)
{
    $thisCount = count($thisArr);
    /** @noinspection PhpUnusedLocalVariableInspection */
    $showLimit = ($thisCount < $limit) ? $thisCount : $limit;
    $i = 0;
    $tmpSortArr = array();
    $resArr = array();
    /** @noinspection PhpUnusedLocalVariableInspection */
    $last_high_score = 0;

    //loop through the results and put in tmp array to sort
    foreach ($thisArr as $all => $subrow)
    {
        if (isset ($subrow[$sortByItem]))
        {
            $tmpSortArr[] = $subrow[$sortByItem];
        }
    }

    //sort the results
    if ($sortAsc == 1)
    {
        //ascending
        arsort($tmpSortArr);
    }
    else
    {
        //descending
        asort($tmpSortArr);
    }

    //loop through scores
    foreach ($tmpSortArr as $sortedKey => $idValue)
    {
        $resArr[] = $thisArr[$sortedKey];
    }
    //get the limited top results
    $outArr = array_slice($resArr, 0, $limit);

    return $outArr;
}

/**
 * function get_highest_scoring_row()
 * This function takes all the relevant and scored aiml results
 * and saves the highest scoring rows
 * @param array $convoArr - the conversation array
 * @param array $allrows - all the results
 * @param string $lookingfor - the user input
 * @param string $table The database table name where the aiml is stored
 * @return array bestResponseArr - best response and its parts (topic etc)
 **/
function get_highest_scoring_row(& $convoArr, $allrows, $lookingfor, $table = 'aiml')
{
    global $bot_id, $which_response;

    $bestResponse = array();
    $last_high_score = 0;
    $tmpArr = array();
    //loop through the results

    foreach ($allrows as $subrow)
    {
        if (!isset ($subrow['score']))
        {
            continue;
        }
        elseif ($subrow['score'] > $last_high_score)
        {
            //if higher than last score then reset tmp array and store this result
            $tmpArr = array($subrow);
            $last_high_score = $subrow['score'];
        }
        elseif ($subrow['score'] == $last_high_score)
        {
            //if same score as current high score add to array
            $tmpArr[] = $subrow;
        }
    }

/*
     It has been suggested that the "winning" response be the first category found with the highest score,
     rather than a random selection from all high scoring responses. It was also suggested that the most
     recent (e.g. the last) response should be chosen, with newer AIML categories superseding older ones.
     At some point this will be an option that will be placed in the admin pages on a per-bot basis, but
     for now it's just a random pick. That said, however, I'm going to start adding code for the other
     two options now.
*/
    if (!defined('BOT_USE_FIRST_RESPONSE')) $which_response = 0;
    $resultCount = count($tmpArr);
    $use_message = 'No results found, so none to pick from.';
    if ($resultCount > 0)
    {
        switch ($which_response)
        {
            case 0:
                $bestResponse = $tmpArr[array_rand($tmpArr)];
                $use_message = "Will use randomly picked best response chosen out of $resultCount responses with same score: " . $bestResponse['aiml_id'] . " - " . $bestResponse['pattern'];
                break;
            case BOT_USE_FIRST_RESPONSE:
                $bestResponse = $tmpArr[0];
                $use_message = "Will use the first best response chosen out of $resultCount responses with same score: " . $bestResponse['aiml_id'] . " - " . $bestResponse['pattern'];
                break;
            case BOT_USE_LAST_RESPONSE:
                $bestResponse = $tmpArr[$resultCount - 1];
                $use_message = "Will use the most recent best response chosen out of $resultCount responses with same score: " . $bestResponse['aiml_id'] . " - " . $bestResponse['pattern'];
                break;
            case 0:
            default:
                $bestResponse = $tmpArr[array_rand($tmpArr)];
                $use_message = "Will use randomly picked best response chosen out of $resultCount responses with same score: " . $bestResponse['aiml_id'] . " - " . $bestResponse['pattern'];
        }
    }
    else $bestResponse = false;
    //there may be any number of results with the same score so pick any random one
    if (!$bestResponse)
    {
        $bestResponse = array(
            'aiml_id' => -1,
            'bot_id' => $bot_id,
            'pattern' => 'no results',
            'thatpattern' => '',
            'topic' => '',
            'score' => 0,
            'track_score' => 'No Match Found!',
        );
        $use_message = 'Empty Response!';
    }

    if (false !== $bestResponse)
    {
        $bestResponse['template'] = get_winning_category($convoArr, $bestResponse['aiml_id'], $table);
    }

    runDebug(__FILE__, __FUNCTION__, __LINE__, "Best Responses: " . print_r($tmpArr, true), 4);
    runDebug(__FILE__, __FUNCTION__, __LINE__, $use_message, 2);

    // Add row data to the chatbot output
    $data = array();
    foreach ($bestResponse as $key => $value)
    {
        if ($key === 'id')
        {
            $data['aiml_id'] = $value;
            continue;
        }
        $data[$key] = $value;
    }
    unset($data['template']);
    $convoArr['conversation']['data'] = $data;

    //return the best response
    return $bestResponse;
}

/**
 * function get_winning_category
 * Retrieves the AIML template from the selected DB entry
 *
 * @param       $convoArr
 * @param array $id - the id number of the AIML category to get
 * @param string $table The database table name where the aiml is stored
 * @return string $template - the value of the `template` field from the chosen DB entry
 */
function get_winning_category(& $convoArr, $id, $table = 'aiml')
{
    runDebug(__FILE__, __FUNCTION__, __LINE__, "And the winner is... $id!", 2);
    global $dbConn, $dbn, $error_response;

    /** @noinspection SqlDialectInspection */
    $sql = "SELECT `template` FROM `$dbn`.`$table` WHERE `id` = $id limit 1;";
    $row = db_fetch($sql, null, __FILE__, __FUNCTION__, __LINE__);

    if ($row)
    {
        $template = $row['template'];
        $convoArr['aiml']['template_id'] = $id;
    }
    else {
        $template = $error_response;
    }

    runDebug(__FILE__, __FUNCTION__, __LINE__, "Returning the AIML template for id# $id. Value:\n'$template'", 4);
    return $template;
}

/**
 * function get_convo_var()
 * This function takes fetches a variable from the conversation array
 *
 * @param array $convoArr - conversation array
 * @param string $index_1 - convoArr[$index_1]
 * @param string $index_2 - convoArr[$index_1][$index_2]
 * @param int|string $index_3 - convoArr[$index_1][$index_2][$index_3]
 * @param int|string $index_4 - convoArr[$index_1][$index_2][$index_3][$index_4]
 * @return string $value - the value of the element
 *
 *
 * examples
 *
 * $convoArr['conversation']['bot_id'] = $convoArr['conversation']['bot_id']
 * $convoArr['that'][1][1] = get_convo_var($convoArr,'that','',1,1)
 */
function get_convo_var($convoArr, $index_1, $index_2 = '~NULL~', $index_3 = 1, $index_4 = 1)
{
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Get from ConvoArr [$index_1][$index_2][$index_3][$index_4]", 4);

    if ((isset ($convoArr[$index_1])) && (!is_array($convoArr[$index_1])) && ($convoArr[$index_1] != ''))
    {
        $value = $convoArr[$index_1];
    }
    elseif ((isset ($convoArr[$index_1][$index_3])) && (!is_array($convoArr[$index_1][$index_3])) && ($convoArr[$index_1][$index_3] != ''))
    {
        $value = $convoArr[$index_1][$index_3];
    }
    elseif ((isset ($convoArr[$index_1][$index_3][$index_4])) && (!is_array($convoArr[$index_1][$index_3][$index_4])) && ($convoArr[$index_1][$index_3][$index_4] != ''))
    {
        $value = $convoArr[$index_1][$index_3][$index_4];
    }
    elseif ((isset ($convoArr[$index_1][$index_2])) && (!is_array($convoArr[$index_1][$index_2])) && ($convoArr[$index_1][$index_2] != ''))
    {
        $value = $convoArr[$index_1][$index_2];
    }
    elseif ((isset ($convoArr[$index_1][$index_2][$index_3])) && (!is_array($convoArr[$index_1][$index_2][$index_3])) && ($convoArr[$index_1][$index_2][$index_3] != ''))
    {
        $value = $convoArr[$index_1][$index_2][$index_3];
    }
    elseif ((isset ($convoArr[$index_1][$index_2][$index_3][$index_4])) && (!is_array($convoArr[$index_1][$index_2][$index_3][$index_4])) && ($convoArr[$index_1][$index_2][$index_3][$index_4] != ''))
    {
        $value = $convoArr[$index_1][$index_2][$index_3][$index_4];
    }
    else {
        $value = '';
    }

    runDebug(__FILE__, __FUNCTION__, __LINE__, "Get from ConvoArr [$index_1][$index_2][$index_3][$index_4] FOUND: ConvoArr Value = '$value'", 4);
    return $value;
}

/**
 * Function: get_client_property()
 * Summary: Extracts a value from the the client properties subarray within the main conversation array
 * @param array $convoArr - the main conversation array
 * @param string $name - the key of the value to extract from client properties
 * @return string $response - the value of the client property
 **/
function get_client_property($convoArr, $name)
{
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Rummaging through the DB and stuff for a client property.', 2);
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Looking for client property '$name'", 2);
    global $dbConn, $dbn;

    if (isset ($convoArr['client_properties'][$name]))
    {
        $value = $convoArr['client_properties'][$name];
        runDebug(__FILE__, __FUNCTION__, __LINE__, "Found client property '$name' in the conversation array. Returning '$value'", 2);

        return $convoArr['client_properties'][$name];
    }

    runDebug(__FILE__, __FUNCTION__, __LINE__, "Client property '$name' not found in the conversation array. Searching the DB.", 2);

    $user_id = $convoArr['conversation']['user_id'];
    $bot_id = $convoArr['conversation']['bot_id'];
    /** @noinspection SqlDialectInspection */
    $sql = "SELECT `value` FROM `$dbn`.`client_properties` WHERE `user_id` = $user_id AND `bot_id` = $bot_id AND `name` = '$name' limit 1;";

    runDebug(__FILE__, __FUNCTION__, __LINE__, "Querying the client_properties table for $name. SQL:\n$sql", 3);

    $row = db_fetch($sql, null, __FILE__, __FUNCTION__, __LINE__);
    $rowCount = count($row);

    if ($rowCount != 0)
    {
        $response = trim($row['value']);
        $convoArr['client_properties'][$name] = $response;
        runDebug(__FILE__, __FUNCTION__, __LINE__, "Found client property '$name' in the DB. Adding it to the conversation array and returning '$response'", 2);
    }
    else {
        $response = 'undefined';
    }

    return $response;
}

/**
 * function get_aiml_to_parse()
 * This function controls all the process to match the aiml in the db to the user input
 * @param array $convoArr - conversation array
 * @return array $convoArr
 **/
function get_aiml_to_parse($convoArr)
{
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Running all functions to get the correct aiml from the DB", 4);

    $lookingfor = $convoArr['aiml']['lookingfor'];
    $current_thatpattern = (isset ($convoArr['that'][1][1])) ? $convoArr['that'][1][1] : '';
    $current_topic = get_topic($convoArr);
    $aiml_pattern = $convoArr['conversation']['default_aiml_pattern'];
    $bot_parent_id = $convoArr['conversation']['bot_parent_id'];
    $raw_that = (isset ($convoArr['that'])) ? print_r($convoArr['that'], true) : '';
    $table = 'aiml_userdefined';

    //check if match in user defined aiml
    $allrows = find_aiml_matches($convoArr, 'aiml_userdefined');

    //if there is no match in the user defined aiml table
    if ((!isset ($allrows)) || (count($allrows) <= 0))
    {
        //look for a match in the normal aiml tbl
        $allrows = find_aiml_matches($convoArr);
        $table = 'aiml';
    }

    //unset all irrelvant matches
    $allrows = unset_all_bad_pattern_matches($convoArr, $allrows, $lookingfor);
    //score the relevant matches
    $allrows = score_matches($convoArr, $allrows, $lookingfor);
    //get the highest scoring row
    $WinningCategory = get_highest_scoring_row($convoArr, $allrows, $lookingfor, $table);

    // If the selected category's pattern is the default pickup category, store the input in the unknown inputs table
    if ($WinningCategory['pattern'] == $aiml_pattern && $lookingfor != $aiml_pattern)
    {
        $bot_id = $convoArr['conversation']['bot_id'];
        $user_id = $convoArr['conversation']['user_id'];
        $rawSay = $convoArr['conversation']['rawSay'];
        addUnknownInput($convoArr, $rawSay, $bot_id, $user_id);
    }

    //Now we have the results put into the conversation array
    $convoArr['aiml']['pattern'] = $WinningCategory['pattern'];
    $convoArr['aiml']['thatpattern'] = $WinningCategory['thatpattern'];
    $convoArr['aiml']['template'] = $WinningCategory['template'];
    $convoArr['aiml']['html_template'] = '';
    $convoArr['aiml']['topic'] = $WinningCategory['topic'];
    $convoArr['aiml']['score'] = (isset($WinningCategory['score'])) ? $WinningCategory['score'] : 0;
    $convoArr['aiml']['aiml_id'] = (isset($WinningCategory['aiml_id'])) ? $WinningCategory['aiml_id'] : -1;
    //return
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Will be parsing id:" . $WinningCategory['aiml_id'] . " (" . $WinningCategory['template'] . ")", 4);

    return $convoArr;
}

/**
 * function find_aiml_matches()
 * This function builds the sql to use to get a match from the tbl
 * @param array $convoArr - conversation array
 * @param string $table The database table name where the aiml is stored
 * @return array $allrows
 **/
function find_aiml_matches($convoArr, $table = 'aiml')
{
    global $dbConn, $dbn, $error_response;
    $user_id = $convoArr['conversation']['user_id'];
    runDebug(__FILE__, __FUNCTION__, __LINE__, 'Finding the aiml matches from the DB', 4);

    $i = 0;
    $allrows = array();
    //TODO convert to get_it
    $bot_id = $convoArr['conversation']['bot_id'];
    $bot_parent_id = $convoArr['conversation']['bot_parent_id'];
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Bot ID = $bot_id. Bot Parent ID = $bot_parent_id.", 4);
    $default_aiml_pattern = $convoArr['conversation']['default_aiml_pattern'];

    #$lookingfor = get_convo_var($convoArr,"aiml","lookingfor");
    $convoArr['aiml']['lookingfor'] = str_replace('  ', ' ', $convoArr['aiml']['lookingfor']);
    $lookingfor = trim(_strtoupper($convoArr['aiml']['lookingfor']));
    runDebug(__FILE__, __FUNCTION__, __LINE__, "We are looking for $lookingfor", 4);

    //get the first and last words of the cleaned user input
    $lastInputWord = get_last_word($lookingfor);
    $firstInputWord = get_first_word($lookingfor);

    //get the stored topic
    $storedtopic = get_topic($convoArr);
    runDebug(__FILE__, __FUNCTION__, __LINE__, "Stored topic = '$storedtopic'", 4);

    //get the cleaned user input
    $lastthat = (isset ($convoArr['that'][1][1])) ? $convoArr['that'][1][1] : '';

    //build like patterns
    if ($lastthat != '')
    {
        $thatPatternSQL = " OR " . make_like_pattern_regex($lastthat, 'thatpattern');
        $thatPatternSQL = rtrim($thatPatternSQL, ' OR');
    }
    else
    {
        $thatPattern = '';
        $thatPatternSQL = '';
    }

    //get the word count
    $word_count = wordsCount_inSentence($lookingfor);

    if ($bot_parent_id != 0 && $bot_parent_id != $bot_id)
    {
        $sql_bot_select = " (bot_id = '$bot_id' OR bot_id = '$bot_parent_id') ";
    }
    else {
        $sql_bot_select = " bot_id = '$bot_id' ";
    }

    if (!empty($storedtopic))
    {
        $topic_select = "AND (`topic` like '$storedtopic' OR `topic`='' OR `topic` like '%*%' OR `topic` like '%_%')";
    }
    else {
        $topic_select = "AND `topic`='' OR `topic` like '%*%' OR `topic` like '%_%'";
    }

    if ($table != 'aiml')
    {
        $c_id = $convoArr['conversation']['convo_id'];
        $sql_bot_select .= "AND (`user_id` = '$c_id' OR `user_id` = '-1') ";
        $topic_select = ''; // user defined AIML has no topics
    }

    if ($topic_select == '') {
        $topic_select = ' ORDER BY';
        $fieldlist = ', `template`';
    }
    else {
        $topic_select .= ' ORDER BY `topic` DESC,';
        $fieldlist = ', `topic`, `filename`';
    }

    if ($word_count == 1)
    {
        //if there is one word do this
        /** @noinspection SqlDialectInspection */
        $sql = "SELECT `id`, `bot_id`, `pattern`, `thatpattern` $fieldlist FROM `$dbn`.`$table` WHERE
  $sql_bot_select AND (
  `pattern` = '_' OR
  `pattern` = '*' OR
  `pattern` = '$lookingfor' OR
  `pattern` = '$default_aiml_pattern'
  $thatPatternSQL OR `thatpattern` LIKE '%*%' OR `thatpattern` LIKE '%_%'
  ) $topic_select `pattern` ASC, `thatpattern` ASC,`id` ASC;";
    }
    else {
        //otherwise do this
        $sql_add = make_like_pattern($lookingfor, 'pattern');

        /** @noinspection SqlDialectInspection */
        $sql = "SELECT `id`, `bot_id`, `pattern`, `thatpattern` $fieldlist FROM `$dbn`.`$table` WHERE
  $sql_bot_select AND (
  `pattern` = '_' OR
  `pattern` = '*' OR
  `pattern` = '$lookingfor' OR $sql_add OR
  `pattern` = '$default_aiml_pattern'
  $thatPatternSQL OR `thatpattern` LIKE '%*%' OR `thatpattern` LIKE '%_%'
  ) $topic_select `pattern` ASC, `thatpattern` ASC,`id` ASC;";
    }


    runDebug(__FILE__, __FUNCTION__, __LINE__, "Core Match AIML sql: $sql", 3);
    $result = db_fetchAll($sql, null, __FILE__, __FUNCTION__, __LINE__);
    $num_rows = count($result);

    if (($result) && ($num_rows > 0))
    {
        $tmp_rows = number_format($num_rows);
        runDebug(__FILE__, __FUNCTION__, __LINE__, "FOUND: ($tmp_rows) potential AIML matches", 2);
        //loop through results

        $j = 0;
        foreach ($result as $row)
        {
            $row['aiml_id'] = $row['id'];

            if ($table == 'aiml')
            {
                $row['score'] = 0;
                $row['track_score'] = '';
                $allrows[] = $row;
            }
            else
            {
                $row['score'] = 300 - $j;
                $row['thatpattern'] = (isset($row['thatpattern'])) ? $row['thatpattern'] : '';
                $row['topic'] = ''; // User defined AIML has no topic.
                $j++;
                $allrows[] = $row;
            }

            $mu = memory_get_usage(true);

            if ($mu >= MEM_TRIGGER)
            {
                runDebug(__FILE__, __FUNCTION__, __LINE__, 'Current operation exceeds memory threshold. Aborting data retrieval.', 0);
                break;
            }
        }
    }
    else if ($table == 'aiml')
    {
        runDebug(__FILE__, __FUNCTION__, __LINE__, "Error: FOUND NO AIML matches in DB", 1);
        addUnknownInput($convoArr, $lookingfor, $bot_id, $user_id);
        runDebug(__FILE__, __FUNCTION__, __LINE__, "Added input '$lookingfor' to the unknown_inputs table.", 1);

        $allrows[$i]['aiml_id'] = "-1";
        $allrows[$i]['bot_id'] = $bot_id;
        $allrows[$i]['pattern'] = "no results";
        $allrows[$i]['thatpattern'] = '';
        $allrows[$i]['topic'] = '';
    }

    return $allrows;
}

/** get_topic()
 * Extracts the current topic directly from the database
 *
 * @param array $convoArr - the conversation array
 * @return string $retVal - the topic
 */
function get_topic($convoArr)
{
    global $dbConn, $dbn, $bot_id;

    $bot_id = (!empty($convoArr['conversation']['bot_id'])) ? $convoArr['conversation']['bot_id'] : $bot_id;
    $user_id = $convoArr['conversation']['user_id'];

    /** @noinspection SqlDialectInspection */
    $sql = "SELECT `value` FROM `client_properties` WHERE `user_id` = $user_id AND `bot_id` = $bot_id and `name` = 'topic';";
    $row = db_fetch($sql, null, __FILE__, __FUNCTION__, __LINE__);
    $num_rows = count($row);
    $retval = ($num_rows == 0) ? '' : $row['value'];

    return $retval;
}

function get_bot_predicate($input)
{
    $search = '/<bot name="(.*?)"[\/]?>/i';
    if (!preg_match($search, $input, $matches)) return $input;
    global $dbConn, $dbn, $bot_id;
    $name = $matches[1];
    $params = array(':name' => $name, ':bot_id' => $bot_id);
    $sql = 'select value from botpersonality where bot_id = :bot_id and name like :name;';
    $result = db_fetch($sql, $params);
    if (!empty($result) && is_array($result))
    {
        $replace = $result['value'];
    }
    else $replace = '';
    $out = preg_replace($search, $replace, $input);
    return $out;
}
