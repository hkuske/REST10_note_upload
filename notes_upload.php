<?php
// php script zo import a list of files exported from a foreign CRM system.
// The files are containes in a subdirectory of the script $dir
// the files have unique names like 1234_image1.jpg, 1235_image1.jpg
// and should be saved with their real names like image1.jog in Sugar
// A csv file $in_file contains the details of the files to be imported
// These are at least:
//    0       "blobkey": "",                 = unique filename in $dir
//    1       "file_size": 0,                = file size, 0 means no file, only note
//    2       "filename": "",                = real file name 
//    3       "file_ext": "",                = real file extension
//    5       "description": "description",  = long text of note
//    6       "foreign_account_key": "",     = key refernecing a linked Account
//    8       "name": "test",                = subject of the note
//EXIT BREAK - used for testing 5 lines of csv, comment the line for full csv
//FOREIGN RELATION  - custom field in Notes and Accounts to find a relation between both


$in_file = "notes.csv";

// Directory where the BLOBKEY named files reside which are renamed to ID:
$dir = ".\\data\\";


$base_url = "http://localhost/demo910ent/rest/v10";
$username = "jim";
$password = "jim";
$migrator = "1"; 


ini_set('max_execution_time', 0);
$script_start = time();
$time_start = time();					 

//////////////////////////////////////////////////////////
//Login - POST /oauth2/token
//////////////////////////////////////////////////////////

$login_url = $base_url . "/oauth2/token";
$logout_url = $base_url . "/oauth2/logout";										   

$oauth2_token_arguments = array(
    "grant_type" => "password",
    //client id/secret you created in Admin > OAuth Keys
    "client_id" => "sugar",
    "client_secret" => "",
    "username" => $username,
    "password" => $password,
    "platform" => "seco"
);

$oauth2_token_response = call($login_url, '', 'POST', $oauth2_token_arguments);
print_r($oauth2_token_response);
echo "<hr>";

if ($oauth2_token_response->access_token == "") die("No Login");

$time_max = $oauth2_token_response->expires_in - 60;

//////////////////////////////////////////////////////////
//READ CSV file and send Notes
//////////////////////////////////////////////////////////

$row = 0;
if (($handle = fopen($in_file, "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ';', '"')) !== FALSE) {

	    // make sure there is enough time to create the record and start the upload
		if ((time()-$time_start)>$time_max) {
            call($logout_url, '', 'POST', $oauth2_token_arguments);
			$oauth2_token_response = call($login_url, '', 'POST', $oauth2_token_arguments);
			print_r($oauth2_token_response);
			echo "<hr>";		
            $time_start = time();
		}
		
		$row++;

//EXIT BREAK		
		if ($row > 5) break;	// STOP for TEST
//EXIT BREAK		

		$num = count($data);
        $DEBUG = "$num|$row|";
        for ($c=0; $c < $num; $c++) {
            $DEBUG .= $data[$c] . "|";
        }
		$DEBUG .= "|</br>\n";		


// FIELDS OF THE CSV INPUT FILE:
// 0-"BLOBKEY";1-"Filesize";2-"Filename";3-"Extension";4-"CustomerNumber";
// 5-"Description";6-"foreign_account_key";7-"SalesRep";8-"Subject";9-"Subsidiary";
// 10-"Salesman";11-"CompanyName"
// "ZSVK-783E68";"0";"";"";"";"ADAMCIC MARKO s.p. - Company";"ILIK-73YBDQ-001";"";"ADAMCIC MARKO s.p. - Company";"";"";""

// Header
		if ($row == 1) continue;	 

        //////////////////////////////////////////////////////////   			
		//Create note record - POST /<module>/:record
        //////////////////////////////////////////////////////////   			

		$url = $base_url . "/Notes";
		
		$note_arguments = array(
//FOREIGN RELATION 
			"foreign_account_key_c" => $data[6],
//FOREIGN RELATION 
			"set_created_by" => true,
			"description" => $data[5],
			"name" => $data[8],
			"assigned_user_id" => "1",
			"team_id" => "1",
			"team_set_id" => "1",
			"acl_team_set_id" => "1",
            "portal_flag" => false,
            "embed_flag" => false,
            "following" => false,
            "my_favorite" => false,
			"modified_user_id" => $migrator,
			"created_by" => $migrator,
		);
		
		if ($data[1] != 0){
		   $note_arguments["file_size"] = $data[1];
		   $note_arguments["filename"] = $data[2];
		   $note_arguments["file_ext"] = $data[3];
		}
		$DEBUG .= "## CREATE NOTE: ".print_r($note_arguments,true)." ##</br>\n";

		$note_response = call($url, $oauth2_token_response->access_token, 'POST', $note_arguments);
		$DEBUG .= "## CREATED: ".print_r($note_response,true)." ##</br>\n";

        $note_id = $note_response->id;
		$foreign_account_key = $data[6];
		
		if ($note_id != "") {

			//////////////////////////////////////////////////////////   			
			//Search account record - GET /<module>/
			//////////////////////////////////////////////////////////   			
	
			$account_id = "";
			$url = $base_url . '/Accounts';
	
			$acc_arguments = array(
				"filter" => array(
					array(
//FOREIGN RELATION 
						"foreign_account_key_c" => $foreign_account_key,
//FOREIGN RELATION 
					)
				),
				"max_num" => 1,
				"offset" => 0,
				"fields" => "id",
			);
			$DEBUG .= "## SEARCH ACCOUNT: ".print_r($acc_arguments,true)." ##</br>\n";
	
			$acc_response = call($url, $oauth2_token_response->access_token, 'GET', $acc_arguments);
			$DEBUG .= "## SEARCH RESULT: ".print_r($acc_response,true)." ##</br>\n";
		
			if (count($acc_response->records) > 0) {
				$account_id = $acc_response->records[0]->id;
			}
			
			if ($account_id != "") {
					
				$DEBUG .= "##: ".$note_id." ## ".$foreign_account_key." ## ".$account_id." ##</br></br>";
		
				//////////////////////////////////////////////////////////   			
				//Update note record - PUT /<module>/:record
				//////////////////////////////////////////////////////////   			
				
				$url = $base_url . "/Notes/" . $note_id;
				
				$note_arguments2 = array(
					"set_created_by" => true,
					"modified_user_id" => $migrator,
					"created_by" => $migrator,
					"parent_type" => "Accounts",
					"parent_id" => $account_id,
				);
				$DEBUG .= "## SET PARENT: ".print_r($note_arguments2,true)." ##</br>\n";
				$note_response2 = call($url, $oauth2_token_response->access_token, 'PUT', $note_arguments2);
			
			}
			
			//////////////////////////////////////////////////////////   			
			// NOTE record is createed
			// file must be uploaded 
			// 2 Alterenatives:
			// Alternative 1 : rename the file to $note_id and copy it by ftp
			// Alternative 2 : upload the file by REST
			//////////////////////////////////////////////////////////   			
			
			
			// ALTERNATIVE 2
			// send the file via REST
/* ACTIVE */			
			//////////////////////////////////////////////////////////   			
			//Upload note file - POST /Notes/<id>/file/filename
			//////////////////////////////////////////////////////////   			
			
			if ($data[1] != 0) {
				$url = $base_url . "/Notes/".$note_id."/file/filename";
				
				if ((version_compare(PHP_VERSION, '5.5') >= 0)) {
					$filedata = new CURLFile(realpath($dir.$data[0]),"",$data[2]);
				} else {
					$filedata = '@'.realpath($dir.$data[0]);
				}
				$file_arguments = array(
					"format" => "sugar-html-json",
					"delete_if_fails" => true,
					"oauth_token" => $oauth2_token_response->access_token,
					'filename' => $filedata,
				);
				$DEBUG .= "## UPLOAD FILE: ".$note_id. "#" .print_r($file_arguments,true) . "<br>\n";
				$file_response = call($url, $oauth2_token_response->access_token, 'POST', $file_arguments, false,false,true);
				$DEBUG .= "## UPLOAD RESPONSE: ".print_r($file_response,true) . "<br>\n";
				$DEBUG .= "<hr>";	
			
			}
/* ACTIVE */
			
			// ALTERNATIVE 1
			// rename the original file to $note_id
			// currently "unwanted" by cloud support
/* INACTIVE
			if ($data[1] != 0) {
				$cmd = 'rename "'.$dir.$data[0].'" '.$note_id;
				$DEBUG .= "## ".$cmd." ##</br></br>";
				exec($cmd);
			}
   INCATIVE */			

		}	
        echo $DEBUG; $DEBUG="";
			
    }
    fclose($handle);
}

$script_runtime = time()-$script_start;
$DEBUG .= "TIME needed: ".$script_runtime."<br>\n";
echo $DEBUG; $DEBUG="";


////////////////////////////////////////////////////////////////////
// END OF MAIN
////////////////////////////////////////////////////////////////////


/**
 * Generic function to make cURL request.
 * @param $url - The URL route to use.
 * @param string $oauthtoken - The oauth token.
 * @param string $type - GET, POST, PUT, DELETE. Defaults to GET.
 * @param array $arguments - Endpoint arguments.
 * @param array $encodeData - Whether or not to JSON encode the data.
 * @param array $returnHeaders - Whether or not to return the headers.
 * @param array $filenHeader - Whether or not to upload a file
 * @return mixed
 */
function call(
    $url,
    $oauthtoken='',
    $type='GET',
    $arguments=array(),
    $encodeData=true,
    $returnHeaders=false,
	$fileHeader=false
)
{
    $type = strtoupper($type);

    if ($type == 'GET')
    {
        $url .= "?" . http_build_query($arguments);
    }

    $curl_request = curl_init($url);

    if ($type == 'POST')
    {
        curl_setopt($curl_request, CURLOPT_POST, 1);
    }
    elseif ($type == 'PUT')
    {
        curl_setopt($curl_request, CURLOPT_CUSTOMREQUEST, "PUT");
    }
    elseif ($type == 'DELETE')
    {
        curl_setopt($curl_request, CURLOPT_CUSTOMREQUEST, "DELETE");
    }

    curl_setopt($curl_request, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
    curl_setopt($curl_request, CURLOPT_HEADER, $returnHeaders);
    curl_setopt($curl_request, CURLOPT_SSL_VERIFYHOST, 0);  // wichtig
    curl_setopt($curl_request, CURLOPT_SSL_VERIFYPEER, 0);  // wichtig
    curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl_request, CURLOPT_FOLLOWLOCATION, 0);

    if (!empty($oauthtoken)) 
    {
		if ($fileHeader) {
			curl_setopt($curl_request, CURLOPT_HTTPHEADER, array(
				"oauth-token: {$oauthtoken}"));
		} else {
            curl_setopt($curl_request, CURLOPT_HTTPHEADER, array(
				"oauth-token: {$oauthtoken}",
				"Content-Type: application/json"));
		}		
    }
    else
    {
        curl_setopt($curl_request, CURLOPT_HTTPHEADER, array(
			"Content-Type: application/json"));
    }

    if (!empty($arguments) && $type !== 'GET')
    {
        if ($encodeData)
        {
            //encode the arguments as JSON
            $arguments = json_encode($arguments);
        }
        curl_setopt($curl_request, CURLOPT_POSTFIELDS, $arguments);
    }

    $result = curl_exec($curl_request);
	
    if ($returnHeaders)
    {
        //set headers from response
        list($headers, $content) = explode("\r\n\r\n", $result ,2);
        foreach (explode("\r\n",$headers) as $header)
        {
            header($header);
        }

        //return the nonheader data
        return trim($content);
    }

    curl_close($curl_request);

    //decode the response from JSON
    $response = json_decode($result);

    return $response;
}
?>