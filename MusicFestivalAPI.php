<?php
// Call API to get raw data
$curl = curl_init("https://eacp.energyaustralia.com.au/codingtest/api/v1/festivals");
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
$response = curl_exec($curl);
curl_close($curl);
switch($response) {
    case "Too many requests, throttling":
        $statusCode = 429;
        $data = "Too many requests, throttling";
        break;
    case "\"\"":
        $statusCode = 204;
        $data = "The server returns no content";
        break;
    default:
        $jsonString = stripslashes(html_entity_decode($response));
        $result = json_decode($jsonString, true);
        /*
        Process raw data into array of bands for easy sorting and search by recordLabel
        $bands[
                'name': String
                'recordLabel': String
                'festivals': Array []
              ]
         */

        $bands = array();
        foreach ($result as $key => $festival) {
            // Check if festival name is null
            if (isset($festival['name']))
                $festivalName = $festival['name'];
            else
                $festivalName = "";
            foreach ($festival['bands'] as $band) {
                /*
                    Only take bands with a record label since we are
                 returning a payload of record labels
                 */
                if (isset($band['recordLabel']) && $band['recordLabel'] != "") {
                    // Check if band already exists in $bands
                    $key = array_search($band['name'], array_column($bands, 'name'));
                    // If yes then add festival to that band
                    if ($key != false &&
                        isset($festivalName) &&
                        $festivalName != "") {
                        $bands[$key]['festivals'][] = array('name' => $festivalName);
                    } // If not then add a new band
                    else {
                        if (isset($festivalName) && $festivalName != "") {
                            $band['festivals'] = array();
                            array_push($band['festivals'], array('name' => $festivalName));
                        }
                        array_push($bands, $band);
                    }
                }
            }
        }


        // Now process $bands into return format
        $labels = array();
        foreach ($bands as $band) {
            if (isset($band['festivals']))
                asort($band['festivals']);
            // Check if label already existed in $labels
            $key = array_search($band['recordLabel'], array_column($labels, 'label'));
            if ($key != false) {
                unset($band['recordLabel']);
                $labels[$key]['bands'][] = $band;
            } // If no create new label
            else {
                $label = array();
                $label['label'] = $band['recordLabel'];
                unset($band['recordLabel']);
                $label['bands'] = array($band);
                array_push($labels, $label);
            }
        }

// Get rid of indexes within $label['bands'] to get the payload
// with the right format and also sort
        foreach ($labels as $label) {
            $label['bands'] = array_values($label['bands']);
            asort($label['bands']);
        }
        $statusCode = 200;
        $data = json_encode(array_values($labels));
}

// Return result
header("HTTP/1.1 ".$statusCode);
echo $data;