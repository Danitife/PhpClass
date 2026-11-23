<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle browser preflight (OPTIONS) requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


// // echo  "<br>";
// // echo  "Curl is a library that allows you to make HTTP requests from your PHP code";
try {
    $ch = curl_init();

$url = "https://api.restcountries.com/countries/v5";

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Content-Type: application/json",
    "Authorization: Bearer rc_live_2f9b01c25d2e4e88927e6535edbb0d94"
));
// // echo  "<br>";
// // echo  "Making a GET request to $url";
$response = curl_exec($ch);
if(curl_errno($ch)){
    // // echo  "<br>";

    // echo  "Curl Error: " . curl_error($ch);
}else{
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if($http_code == 200){
        // echo  "Request successful";
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        // // echo  "<br>";
        // echo  "All the countries in the world are: ";
        $body = substr($response, $header_size);
        $countries = json_decode($body, true);
        // // echo  "<br />";
        
        // echo  count($countries['data']);
        // // echo  "<br />";
        // print_r($countries['data']['objects']);
        $cl = [];
        foreach ($countries['data']['objects'] as $key => $value) {
            // json_decode($value);
            // echo  $key + 1 . " => " . $value['names']['common'];
            // $cl[] = ["id" => $key + 1, "name" => $value['names']['common']];
            array_push($cl, $value['names']['common']);
            // print_r($cl);
            
            // print_r($value['names']['common']);
        }
            http_response_code(200);
            echo json_encode($cl);
        // foreach($countries['data'] as $country){
        //     // echo  "<h1>Country Name: " . $country['objects'][0]['common'] . "</h1>";
        // }
    }else{
        // echo  "Request failed with HTTP code: $http_code";
        // // echo  "<br>";
        // echo  "Response: ";
        print_r($response);
    }
}
} catch (\Exception $e) {
    // echo  "Exception: " . $e->getMessage();
}
?>
