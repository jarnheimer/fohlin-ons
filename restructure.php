<?php/* ------------------------------------------------------------------------------------- *  Improve debugging * *  Turn on all debugging for developing purpose * ------------------------------------------------------------------------------------- */error_reporting(E_ALL);ini_set('display_errors', 1);/* ------------------------------------------------------------------------------------- *  Allow huge memory * *  Since a lot of data is handled, the allowed memory in increased significantly * ------------------------------------------------------------------------------------- */ini_set('memory_limit', '512M');set_time_limit(300);/* ------------------------------------------------------------------------------------- *  Config * ------------------------------------------------------------------------------------- */include 'successvariables.php';/* -------------------------------------------------------------------------------------*  Import the data**  Import the data from Zephyr and make it readable in the language currently used* ------------------------------------------------------------------------------------- */descriptive('Import the data');// Import Excel data (csv)$code = file_get_contents('Zephyr_Export_22april.csv');// Convert csv to arrays$rows = explode('', $code);// $rows = array_slice($rows,0, 500);foreach ($rows as &$row) {    $row = explode(';', $row);}// print_pre($rows);print_pre($rows[0]);// Remove the table headingunset($rows[0]);/* ------------------------------------------------------------------------------------- *  Restructure the data into funding rounds * ------------------------------------------------------------------------------------- */descriptive('Restructure the into rounds');$rounds = [];foreach ($rows as $key => &$row) {    if (!empty($row[2]))        $rounds[$row[1]]['name'] = $row[2];    $rounds[$row[1]]['type'] = $row[3];    if (!empty($row[4]))        $rounds[$row[1]]['acquirers'][] = ['name' => $row[4], 'type' => $row[6]];    if (!empty($row[5]))        $rounds[$row[1]]['vendors'][] = $row[5];}info('<b>Rounds in the rawdata:</b> ' . count($rounds));/* ------------------------------------------------------------------------------------ *  Restructure the data into rounds * ------------------------------------------------------------------------------------- */descriptive('Restructure the rounds into target companies');$targets = [];foreach ($rounds as $key => $round) {    $targetKey = minimizeCompanyName($round['name']);    unset($round['name']);    $round['acquirers'] = (isset($round['acquirers'])) ? $round['acquirers'] : [];    $round['vendors'] = (isset($round['vendors'])) ? $round['vendors'] : [];    $targets[$targetKey]['rounds'][$key] = $round;}info('<b>Target companies in the raw data:</b> ' . count($targets));/* ------------------------------------------------------------------------------------- *  Divide rounds into developing capital or exit rounds * ------------------------------------------------------------------------------------- */descriptive('Divide rounds into developing capital or exit rounds');$exitRoundTypes = [];$excludes = ['Minority', 'buyback', 'Capital Increase'];foreach ($targets as $targetKey => $target) {    foreach ($target['rounds'] as $roundKey => $round) {        if (count($round['vendors'])) {            $include = true;            foreach ($excludes as $exclude) {                if (stripos($round['type'], $exclude) !== false) {                    $include = false;                }            }            if($include){                $exitRoundTypes[] = $round['type'];                $targets[$targetKey]['exitRounds'][] = $round;            }        } else            $targets[$targetKey]['fundingRounds'][] = $round;    }    unset($targets[$targetKey]['rounds']);}/* ------------------------------------------------------------------------------------- *  Remove acquirers who is business angles * ------------------------------------------------------------------------------------- */descriptive('Find all business angles');$businessAngles = [];foreach ($targets as $targetKey => $target) {    if(isset($target['fundingRounds'])) {        foreach ($target['fundingRounds'] as $round) {            foreach ($round['acquirers'] as $acquirer ) {                if( startsWith($acquirer['name'], 'MR ') ||startsWith($acquirer['name'], 'MS ') || startsWith($acquirer['name'], 'MRS ') ){                    $businessAngles[] = $acquirer['name'];                }            }        }    }}info('<b>Business angels: </b>' . count($businessAngles));/* ------------------------------------------------------------------------------------- *  Find acquirers without reinvestments * ------------------------------------------------------------------------------------- */descriptive('Find acquirers without reinvestments');$aquirersDoingReinvestment = [];$aquirersNotDoingReinvestment = [];foreach ($targets as $targetKey => $target) {    $targetAquirers = [];    if(isset($target['fundingRounds'])){        foreach ($target['fundingRounds'] as $round) {            foreach ($round['acquirers'] as $acquirer) {                $targetAquirers[] = $acquirer['name'];            }        }        $targetAquirersCount = array_count_values($targetAquirers);        foreach ($targetAquirersCount as $aquire => $count) {            if ($count > 1) {                $aquirersDoingReinvestment[] = $aquire;            }else{                $aquirersNotDoingReinvestment[] = $aquire;            }        }    }}$aquirersDoingReinvestment = array_unique($aquirersDoingReinvestment);$aquirersNotDoingReinvestment = array_unique($aquirersNotDoingReinvestment);info('<b>Acquirers doing reinvestmenst: </b>' . count($aquirersDoingReinvestment) .'<br> <b>Not doing reinvestments:</b> ' . count($aquirersNotDoingReinvestment));/* ------------------------------------------------------------------------------------- *  Remove all target companies with too few rounds * ------------------------------------------------------------------------------------- */filter('Remove all target companies with too few rounds');foreach ($targets as $key => $target) {    if (!isset($target['fundingRounds'])) {        unset($targets[$key]);        continue;    }    if (count($target['fundingRounds']) < 3) {        unset($targets[$key]);    }}info('<b>Target companies left after filtering:</b> ' . count($targets));/* ------------------------------------------------------------------------------------- *  Remove all target companies with too few rounds * ------------------------------------------------------------------------------------- */filter('Remove all target companies missing meta data in one or more rounds');foreach ($targets as $targetKey => $target) {    foreach ($target['fundingRounds'] as $round) {        if (count($round['acquirers']) == 0 && count($round['vendors']) == 0) {            unset($targets[$targetKey]);        }    }}info('<b>Target companies left after filtering:</b> ' . count($targets));/* ------------------------------------------------------------------------------------- *  Remove all acquirers that is angles * ------------------------------------------------------------------------------------- */filter('Remove all acquirers that is angles');foreach ($targets as $targetKey => $target) {    foreach ($target['fundingRounds'] as $roundKey => $round) {        foreach($round['acquirers'] as $acquirerKey => $acquirer){            if( in_array($acquirer['name'], $businessAngles)){                unset($targets[$targetKey]['fundingRounds'][$roundKey]['acquirers'][$acquirerKey]);            }        }    }}/* ------------------------------------------------------------------------------------- *  Degree of reinvestment * ------------------------------------------------------------------------------------- */descriptive('Define each target companies\' degree of reinvestments');$tooLittleData = 0;foreach ($targets as $targetKey => &$target) {    $reinvestments = 0;    $reinvestmentsOpportunities = 0;    foreach ($target['fundingRounds'] as $roundKey => $round) {        if (isset($lastRoundKey) && isset($target['fundingRounds'][$lastRoundKey]['acquirers'])){            foreach ($target['fundingRounds'][$lastRoundKey]['acquirers'] as $aquirer) {                $aquirersWithInsight[] = $aquirer['name'];            }        }else{            $aquirersWithInsight = [];        }        $reinvestmentsOpportunities = $reinvestmentsOpportunities + count($aquirersWithInsight);        foreach ($round['acquirers'] as $aquirer) {            if (in_array($aquirer['name'], $aquirersWithInsight)) {                $reinvestments++;            } else {                $aquirersWithInsight[] = $aquirer['name'];            }        }        $lastRoundKey = $roundKey;    }    $target['reinvestments'] = $reinvestments;    $target['reinvestmentsOpportunities'] = $reinvestmentsOpportunities;    if ($reinvestmentsOpportunities > 4)        $target['reinvestmentsDegree'] = round($reinvestments / $reinvestmentsOpportunities, 3);    else{        $tooLittleData++;        unset($targets[$targetKey]);    }    unset($lastRoundKey);}filter('Remove comapnies with too littleData ' . $tooLittleData );/* ------------------------------------------------------------------------------------- *  Success * ------------------------------------------------------------------------------------- */descriptive('Define each target companies\' success or no sucess');foreach ($targets as &$target) {    $success = 0;    if (isset($target['exitRounds'])) {        if (count($target['exitRounds'])) {            $success = 1;        }    } else {        $target['exitRounds'] = [];    }    $target['success'] = $success;}// -------------------------------------------------------------------------------------//     PRINT THE RESULT// -------------------------------------------------------------------------------------/*foreach ($targets as $name => $target) {    echo '<pre>';    print_r($target);    echo '</pre><br>';}*/echo '<table border="1">';echo '<thead>';echo '<tr>';echo '<th>name</th>';echo '<th>reinvestmentdegree</th>';echo '<th>success</th>';echo '<th>fundingRounds</th>';echo '<th>exitRounds</th>';echo '</tr>';echo '</thead>';foreach ($targets as $name => $target) {    echo '<tr>';    echo '<td>' . $name . '</td>';    echo '<td>' . $target['reinvestmentsDegree'] . '</td>';    echo '<td>' . $target['success'] . '</td>';    echo '<td>' . count($target['fundingRounds']) . '</td>';    echo '<td>' . count($target['exitRounds']) . '</td>';    echo '</tr>';}echo '</table>';function minimizeCompanyName($name){    return strtolower(preg_replace("/[^a-zA-Z]+/", "", $name));}function info($str){    echo '<div style="padding:20px; background: #b0d7fc; border: solid 2px #7aacdb; margin-bottom: 10px;">' . $str . '</div>';}function descriptive($str){    echo '<div style="padding:10px 20px; background: #f1f1f1; border: solid 2px #cccccc; margin-bottom: 10px;">' . $str . '</div>';}function filter($str){    echo '<div style="padding:10px 20px; background: #fcb0b2; border: solid 2px #ff5256; margin-bottom: 10px;">' . $str . '</div>';}function startsWith($haystack, $needle) {    // search backwards starting from haystack length characters from the end    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;}function print_pre($str){    echo '<pre>';    print_r($str);    echo '</pre>';}