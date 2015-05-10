<?php/* ------------------------------------------------------------------------------------- *  Improve debugging * *  Turn on all debugging for developing purpose * ------------------------------------------------------------------------------------- */ini_set('display_errors',1);ini_set('display_startup_errors',1);error_reporting(-1);/* ------------------------------------------------------------------------------------- *  Allow huge memory * *  Since a lot of data is handled, the allowed memory in increased significantly * ------------------------------------------------------------------------------------- */ini_set('memory_limit', '1024M');set_time_limit(600);/* ------------------------------------------------------------------------------------- *  Config * ------------------------------------------------------------------------------------- */define('MINIMUM_NUMBER_OF_FUNDING_ROUNDS', 3);define('MINIMUM_NUMBER_DATAPOINTS', 3);define('FIRST_ROUND_START', 1995);define('FIRST_ROUND_END', 2010);define('ACQUIRE_GROUP', 'vc'); // vc, anglesdefine('EXCLUDE_INSIDE_ROUNDS', true);/* -------------------------------------------------------------------------------------*  Import the data**  Import the data from Zephyr and make it readable in PHP* ------------------------------------------------------------------------------------- */descriptive('Import the data');// Import Excel data (csv)$code = file_get_contents('dataset.csv');// Convert csv to arrays$rows = explode('', $code);foreach ($rows as &$row) {    $row = explode(';', $row);}// Remove the table headingunset($rows[0]);/* ------------------------------------------------------------------------------------- *  Restructure the data into funding rounds * ------------------------------------------------------------------------------------- */descriptive('Restructure the into rounds');$rounds = [];$dealNumber = $completed = $completedAssumed = 0;foreach ($rows as $key => &$row) {    $dealNumber = empty($row[1]) ? $dealNumber : $row[1];    $targetName = $row[2];    $dealType = $row[3];    $acquirerName = $row[4];    $vendorName = $row[5];    $targetSic = $row[6];    $targetRegion  = $row[7];    if(!empty($row[9]) || !empty($row[10]))        $completed = empty($row[10]) ? $row[9] : $row[10];    if(trim($row[8]) == '6799'){        $sic_6799 = true;    } else {        $sic_6799 = false;    }    // Target name    if (!empty($targetName))        $rounds[$dealNumber]['name'] = $targetName;    // Target SIC    if (!empty($targetSic))        $rounds[$dealNumber]['sic'] = $targetSic;    // Target region    if (!empty($targetRegion))        $rounds[$dealNumber]['region'] = $targetRegion;    $rounds[$dealNumber]['type'] = $dealType;    $rounds[$dealNumber]['dealNumber'] = $dealNumber;    $rounds[$dealNumber]['date'] = $completed;    if (!empty($acquirerName))        $rounds[$dealNumber]['acquirers'][] = ['name' => $acquirerName, 'type' => $dealType, 'sic_6799' => $sic_6799];    if (!empty($vendorName))        $rounds[$dealNumber]['vendors'][] = $vendorName;}/* ------------------------------------------------------------------------------------ *  Restructure the data into rounds * ------------------------------------------------------------------------------------- */descriptive('Restructure the rounds into target companies');$targets = [];foreach ($rounds as $key => $round) {    $targetKey = $round['name'];    unset($round['name']);    $round['acquirers'] = (isset($round['acquirers'])) ? $round['acquirers'] : [];    $round['vendors'] = (isset($round['vendors'])) ? $round['vendors'] : [];    $targets[$targetKey]['rounds'][$key] = $round;    if(!empty($round['sic']))        $targets[$targetKey]['sic'] = $round['sic'];    if(!empty($round['region']))        $targets[$targetKey]['region'] = $round['region'];}/* ------------------------------------------------------------------------------------- *  Divide rounds into developing capital or exit rounds * ------------------------------------------------------------------------------------- */descriptive('Divide rounds into developing capital or exit rounds');$exitRoundTypes = [];$excludes = ['Minority', 'buyback', 'Capital Increase'];foreach ($targets as $targetKey => $target) {    foreach ($target['rounds'] as $roundKey => $round) {        // If there are vendors in the round, it should be classified as an exit round...        if (count($round['vendors'])) {            $include = true;            // ..unless the round type is excluded            foreach ($excludes as $exclude) {                if (stripos($round['type'], $exclude) !== false) {                    $include = false;                }            }            if ($include) {                $exitRoundTypes[] = $round['type'];                $targets[$targetKey]['exitRounds'][] = $round;            }        } else {            $targets[$targetKey]['fundingRounds'][] = $round;        }    }    unset($targets[$targetKey]['rounds']);}/* ------------------------------------------------------------------------------------- *  Order the funding rounds * ------------------------------------------------------------------------------------- */descriptive('Order the funding rounds');foreach($targets as &$target){    if(isset($target['fundingRounds'])){        usort($target['fundingRounds'],"cmp");    }}function cmp($a, $b) {    if ($a['date'] == $b['date']) {        return 0;    }    return ($a['date'] < $b['date']) ? -1 : 1;}calculate($targets, 'Initial data set before time scope');// print_pre($targets);/* ------------------------------------------------------------------------------------- *  Time frame* ------------------------------------------------------------------------------------- */foreach($targets as $targetKey => $target){    if(isset($target['fundingRounds'][0]['date']) && !empty($target['fundingRounds'][0]['date'])){        $year = (int) substr($target['fundingRounds'][0]['date'], 0, 4);        if($year < FIRST_ROUND_START || $year > FIRST_ROUND_END){            unset($targets[$targetKey]);        }    }elseif(!isset($target['fundingRounds'])){        unset($targets[$targetKey]);    }}filter('Removing companies with its first round outside '. FIRST_ROUND_START . ' - ' . FIRST_ROUND_END);calculate($targets, 'After implementing time scope');/* ------------------------------------------------------------------------------------- *  Remove all target companies with too few rounds * ------------------------------------------------------------------------------------- */filter('Remove all target companies with too few rounds');foreach ($targets as $key => $target) {    if (count($target['fundingRounds']) < MINIMUM_NUMBER_OF_FUNDING_ROUNDS) {        unset($targets[$key]);    }}/* ------------------------------------------------------------------------------------- *  Remove all target companies with too few rounds * ------------------------------------------------------------------------------------- */filter('Remove all target companies missing meta data in one or more rounds');foreach ($targets as $targetKey => $target) {    foreach ($target['fundingRounds'] as $round) {        if (count($round['acquirers']) == 0 && count($round['vendors']) == 0) {            unset($targets[$targetKey]);        }    }}calculate($targets, 'After filtering companies with to few rounds including info (less than ' . MINIMUM_NUMBER_OF_FUNDING_ROUNDS . ')');/* ------------------------------------------------------------------------------------- *  Remove all inside rounds * ------------------------------------------------------------------------------------- */$insideRoundsCount = 0;$outsideRoundsCount = 0;foreach ($targets as $targetKey => &$target) {    $acquirersWithInsights = [];    $companyInsideRounds = 0;    foreach ($target['fundingRounds'] as $roundKey => $round) {        $insideRound = true;        foreach ($round['acquirers'] as $acquirer) {            // Investeraren is new            if (!in_array($acquirer['name'], $acquirersWithInsights)) {                $insideRound = false;            }            // Add the acquirer to the list of "inside investors"            $acquirersWithInsights[] = $acquirer['name'];        }        if ($insideRound) {            $insideRoundsCount++;            $companyInsideRounds++;            if(EXCLUDE_INSIDE_ROUNDS) unset($target['fundingRounds'][$roundKey]);        } else {            $outsideRoundsCount++;        }    }    $target['insideRounds'] = $companyInsideRounds;}info("<b>Inside rounds:</b> $insideRoundsCount (" . ($insideRoundsCount / ($insideRoundsCount + $outsideRoundsCount)) . ")<br>      <b>Outside rounds:</b> $outsideRoundsCount (" . ($outsideRoundsCount / ($insideRoundsCount + $outsideRoundsCount)) . ")");filter('Remove all inside rounds');calculate($targets, 'After remove all inside rounds');/* ------------------------------------------------------------------------------------- *  Remove all acquirers that is angles * ------------------------------------------------------------------------------------- */filter('Remove unprofessionals');$sic = [];$notSic = [];$businessAngels = [];$sicInv = 0;$notSicInv = 0;$businessAngelsInv = 0;foreach ($targets as $targetKey => $target) {    if (isset($target['fundingRounds'])) {        foreach ($target['fundingRounds'] as $roundKey => $round) {            foreach ($round['acquirers'] as $acquirerKey => $acquirer) {                if (startsWith($acquirer['name'], 'MR ') || startsWith($acquirer['name'], 'MS ') || startsWith($acquirer['name'], 'MRS ')) {                    if(ACQUIRE_GROUP != 'angle') unset($targets[$targetKey]['fundingRounds'][$roundKey]['acquirers'][$acquirerKey]);                    $businessAngels[] = $acquirer['name'];                    $businessAngelsInv++;                }                elseif (!$acquirer['sic_6799']) {                    if(ACQUIRE_GROUP != 'vc') unset($targets[$targetKey]['fundingRounds'][$roundKey]['acquirers'][$acquirerKey]);                    $notSic[] = $acquirer['name'];;                    $notSicInv++;                } else {                    $sic[] = $acquirer['name'];;                    $sicInv++;                }            }        }    }}$sic = count(array_unique($sic));$notSic = count(array_unique($notSic));$businessAngels = count(array_unique($businessAngels));info(    "<b>Business angles: </b> $businessAngels ($businessAngelsInv) <br>    <b>Not SIC: </b>  $notSic ($notSicInv) <br>    <b>SIC: </b> $sic ($sicInv) <br>");calculate($targets, 'After removing all unprofessionals');/* ------------------------------------------------------------------------------------- *  Degree of reinvestment * ------------------------------------------------------------------------------------- */descriptive('Define each target companies\' degree of reinvestments');$tooLittleData = 0;foreach ($targets as $targetKey => &$target) {    $reinvestments = 0;    $reinvestmentsOpportunities = 0;    $acquirersWithInsights = [];    foreach ($target['fundingRounds'] as $roundKey => $round) {        if (isset($lastRoundKey) && isset($target['fundingRounds'][$lastRoundKey]['acquirers'])) {            foreach ($target['fundingRounds'][$lastRoundKey]['acquirers'] as $acquirer) {                $acquirersPreviousRound[] = $acquirer['name'];            }        } else {            $acquirersPreviousRound = [];        }        $reinvestmentsOpportunities = $reinvestmentsOpportunities + count($acquirersPreviousRound);        foreach ($round['acquirers'] as $acquirer) {            if (in_array($acquirer['name'], $acquirersPreviousRound)) {                $reinvestments++;            } else {                $acquirersPreviousRound[] = $acquirer['name'];            }        }        $acquirersWithInsights = array_merge($acquirersWithInsights, $acquirersPreviousRound);        $acquirersPreviousRound = []; // Clear all $acquirersPreviousRound        $lastRoundKey = $roundKey;    }    $target['reinvestments'] = $reinvestments;    $target['reinvestmentsOpportunities'] = $reinvestmentsOpportunities;    unset($lastRoundKey);    $acquirersWithInsights = []; // Unset}foreach ($targets as $targetKey => &$target) {    if ($target['reinvestmentsOpportunities'] > MINIMUM_NUMBER_DATAPOINTS)        $target['reinvestmentsDegree'] = round($target['reinvestments'] / $target['reinvestmentsOpportunities'], 3);    else {        $tooLittleData++;        unset($targets[$targetKey]);    }}filter('Remove companies with too few decisions from professional investors (' . $tooLittleData . ')');calculate($targets, 'After removing companies with too few data points rounds  (less than ' . (MINIMUM_NUMBER_DATAPOINTS + 1) .')');/* ------------------------------------------------------------------------------------- *  Success * ------------------------------------------------------------------------------------- */descriptive('Define each target companies\' success or no sucess');foreach ($targets as &$target) {    $success = 0;    if (isset($target['exitRounds'])) {        if (count($target['exitRounds'])) {            $success = 1;        }    } else {        $target['exitRounds'] = [];    }    $target['success'] = $success;}info('Get average time between funding rounds');foreach ($targets as $targetKey => $target) {    $rounds = $target['fundingRounds'];    $numberOfRounds = count($rounds);    $firstRound = $rounds[0];    $lastRound = end($rounds);    $daysBetweenRound = '';    if(isset($firstRound['date']) && isset($lastRound['date']))    {        $datediff =  strtotime($lastRound['date']) - strtotime($firstRound['date']);        $days =  floor($datediff/(60*60*24));        $daysBetweenRound = round($days/($numberOfRounds-1));    }    $targets[$targetKey]['daysBetweenRound'] = $daysBetweenRound;}// -------------------------------------------------------------------------------------//     PRINT THE RESULT// -------------------------------------------------------------------------------------$check = ['ZOSANO PHARMA INC.','ALVINE PHARMACEUTICALS INC.','HAKIA INC.','WHITTMANHART INC.'];foreach ($targets as $name => $target) {    if(in_array($name, $check))        print_pre($target);}echo '<table border="1">';echo '<thead>';echo '<tr>';echo '<th>name</th>';// echo '<th>reinvestments</th>';// echo '<th>reinvestmentsOpportunities</th>';echo '<th>reinvestmentdegree</th>';echo '<th>success</th>';echo '<th>insideRounds</th>';echo '<th>fundingRounds</th>';echo '<th>averageTimeBetweenRounds</th>';echo '<th>industry</th>';echo '<th>region</th>';echo '</tr>';echo '</thead>';foreach ($targets as $name => $target) {    if(!isset($target['region']))        $target['region'] = '';    echo '<tr>';    echo '<td>' . $name . '</td>';    // echo '<td>' . $target['reinvestments'] . '</td>';    // echo '<td>' . $target['reinvestmentsOpportunities'] . '</td>';    echo '<td>' . $target['reinvestmentsDegree'] . '</td>';    echo '<td>' . $target['success'] . '</td>';    echo '<td>' . $target['insideRounds'] . '</td>';    echo '<td>' . count($target['fundingRounds']) . '</td>';    echo '<td>' . $target['daysBetweenRound'] . ' </td>';    echo '<td>' . substr($target['sic'], 0,2) . '</td>';    echo '<td>' . $target['region'] . '</td>';    echo '</tr>';}echo '</table>';function calculate($targets, $title = ''){    $numberOfTargets = count($targets);    $numberOfRounds = 0;    $numberOfAcquirers = 0;    foreach ($targets as $target) {        if (isset($target['fundingRounds'])) {            foreach ($target['fundingRounds'] as $round) {                $numberOfAcquirers += count($round['acquirers']);            }            $numberOfRounds += count($target['fundingRounds']);        }    }    echo '<div style="padding:20px; background: #aef2e9; border: solid 2px #4ecdbc; margin-bottom: 10px;"><b>COUNT - ' . $title . '</b><br>    Targets: ' . $numberOfTargets . '<br>    Rounds: ' . $numberOfRounds . '<br>    Investment decisions: ' . $numberOfAcquirers . '<br>    </div>';}function info($str){    echo '<div style="padding:20px; background: #b0d7fc; border: solid 2px #7aacdb; margin-bottom: 10px;">' . $str . '</div>';}function descriptive($str){    echo '<div style="padding:10px 20px; background: #f1f1f1; border: solid 2px #cccccc; margin-bottom: 10px;">' . $str . '</div>';}function filter($str){    echo '<div style="padding:10px 20px; background: #fcb0b2; border: solid 2px #ff5256; margin-bottom: 10px;">' . $str . '</div>';}function startsWith($haystack, $needle){    // search backwards starting from haystack length characters from the end    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;}function print_pre($str){    echo '<div style="padding:10px 20px; background: #f7f7f7; border: solid 2px #e3e3e3; margin-bottom: 10px; max-height: 300px; overflow: scroll">';    echo '<pre>';    print_r($str);    echo '</pre>';    echo '</div>';}