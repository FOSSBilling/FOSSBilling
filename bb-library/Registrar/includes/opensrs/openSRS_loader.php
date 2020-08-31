<?php
require_once ("openSRS_config.php");

// Load / Include all files from specified folders
loadOpenSRS (OPENSRSURI);
loadOpenSRS (OPENSRSURI . OPENSRSDOMAINS, "php", true);
loadOpenSRS (OPENSRSURI . OPENSRSMAIL);
loadOpenSRS (OPENSRSURI . OPENSRSFASTLOOKUP);


function loadOpenSRS ($local, $extension="php", $subfolders=false){

	if (!is_dir($local)){
             trigger_error("OSRS Error - Unable to find library directory $local, please check the OPENSRSURI path in openSRS_config.php");
       } else {
            $dir = opendir($local);
            while(false != ($file = readdir($dir))) {
            	// Files in root directory
                    if($file != "." && $file != ".." && !is_dir($local . $file ."/")) {
                    	$fileArr = explode(".", $file);
                    	$lastExt = count ($fileArr) - 1;
			if( substr($fileArr[0], 0, 1) != "_"  && $fileArr[$lastExt] == $extension){
                            if (!is_file($local . $file))
                                trigger_error("OSRS Error - Unable to find library file $file, please check the paths in openSRS_config.php");
                            else
                                require_once ($local . $file);
                        }
		}
		
		// Files in subfolders
		if($file != "." && $file != ".." && is_dir($local . $file ."/") && $subfolders) {
			$subPath = $local . $file ."/";
                        if (!is_dir($subPath)){
                            trigger_error("OSRS Error - Unable to find library directory $subPath, please check path constants in openSRS_config.php");
                        } else {
                            $subDir = opendir($subPath);

                            while(false != ($subFile = readdir($subDir))) {
                                    // Files in subdirectory
                                    if($subFile != "." && $subFile != ".." && !is_dir($subPath . $subFile ."/")) {
                                            $subfileArr = explode(".", $subFile);
                                            $sublastExt = count ($subfileArr) - 1;
                                            if( substr($subfileArr[0], 0, 1) != "_"  && $subfileArr[$sublastExt] == $extension) {
                                                if (!is_file($subPath . $subFile))
                                                    trigger_error("OSRS Error - Unable to find library file $file, please check the paths in openSRS_config.php");
                                                else
                                                    require_once ($subPath . $subFile);
                                            }
                                    }
                            }
                            closedir($subDir);
                        }
		}
	}
	closedir($dir);
      
        }
}


// Array -> Object -> Array conversion
function array2object($data) {
   if(!is_array($data)) return $data;
   $object = new stdClass();
   if (is_array($data) && count($data) > 0) {
      foreach ($data as $name=>$value) {
         $name = strtolower(trim($name));
         if (!empty($name)) {
            $object->$name = array2object($value);
         }
      }
   }
   return $object;
}

function object2array($data){
   if(!is_object($data) && !is_array($data)) return $data;
   if(is_object($data)) $data = get_object_vars($data);
   return array_map('object2array', $data);
}


// Call parsers and functions of openSRS
function processOpenSRS ($type="", $data="") {
	if ($type != "" && $data != ""){
		if ($type == "array") $dataArray = $data;					// ARRAY
		if ($type == "json"){										// JSON
			$json = str_replace("\\\"", "\"", $data);   //  Replace  \"  with " for JSON that comes from Javascript
			$dataArray = json_decode($json, true);
		}
		if ($type == "yaml") $dataArray = Spyc::YAMLLoad($data);	// YAML

		// Convert associative array to object
		$dataObject = array2object($dataArray);
	}
	
	// Call appropriate class
	if (class_exists($dataObject->func)){
		$classCall = new $dataObject->func($type, $dataObject);
	} else {
		$classCall = null;
                trigger_error("OSRS Error - Unable to find the function passed.  Either the function is misspelled or there are incorrect file paths set in openSRS_config.php.");
	}
	return $classCall;
}

function convertArray2Formated ($type="", $data="") {
	$resultString = "";
	if ($type == "json") $resultString = json_encode($data);
	if ($type == "yaml") $resultString = Spyc::YAMLDump($data);
	return $resultString;
}

function convertFormated2array ($type="", $data="") {
	$resultArray = "";
	if ($type == "json") $resultArray = json_decode($data, true);
	if ($type == "yaml") $resultArray = Spyc::YAMLLoad($data);;
	return $resultArray;
}
