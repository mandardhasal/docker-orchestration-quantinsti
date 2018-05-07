<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');

session_start();

$paths = explode('/', $_SERVER['REQUEST_URI']);
unset($paths[0]);
$paths = array_values($paths);



// $output = array();
// $exec = exec("docker ps |  cut -d ' ' -f1", $output, $return);
// unset($output[0]);
// echo "<pre>"; print_r($output); echo " </pre>";
//if( !file_exists(__DIR__.'/database.json' ))
//{
//	echo file_put_contents(__DIR__.'/database.json', json_encode($output));	
//}

if(empty($paths[0])){
	die('path not fund');	
}

switch ($paths[0]) {
	case 'map':
		if(isset($paths[1]) && $paths[1]!='/'){
			die('path not found');
		}
		
		$db = file_get_contents(__DIR__.'/database.json');
		$db = json_decode($db, true);
		
				
		$containerIds = array(); 	
		
		$exec = exec("docker ps |  cut -d ' ' -f1", $containerIds, $return);	
		
		if(isset($containerIds[0])) unset($containerIds[0]);

		
		$containerPorts = getCotainerPorts($containerIds);

		$mappings = array();
		foreach ($containerPorts as $conId => $conPort) {
			$mappings[] = array('con_id' => $conId, 'port' => $conPort);
		}
		$db['mappings'] = $mappings;

		echo "<pre>";
		print_r($db);

		file_put_contents(__DIR__.'/database.json', json_encode($db));
		break;


		case 'instance':

			$db = file_get_contents(__DIR__.'/database.json');
			$db = json_decode($db, true);

			//allocate with sessions
			if(empty($paths[1]) || $paths[1] == '/' ){

				if($_SERVER["REQUEST_METHOD"]!=='GET'){
					die('method not allowed');
				}	

				$allocated = false;

				foreach ($db['mappings'] as $uid => &$server) {
					

					if( empty($server['sess_id']) )
					{
						$server['sess_id'] = session_id();
						file_put_contents(__DIR__.'/database.json', json_encode($db));
						$allocated = true;
						echo "Allocation done. Your id = ".($uid+1);
						break;

					}elseif($server['sess_id'] == session_id() ){
						$allocated = true;
						echo "Allocation already done. Your id = ".($uid+1);
						break;
					
					}
				}
				
				if(!$allocated){
					die("No resource left for allocation");
				}

			}else{


				if( $_SERVER["REQUEST_METHOD"]=='DELETE' && is_numeric($paths[1]) && empty($paths[2]) )
				{
					$id = $paths[1] - 1;
					if( isset($db['mappings'][$id]) ){

						exec('docker rm -f '.$db['mappings'][$id]['con_id'], $out, $returnVar );

						//new container
						if($returnVar == 0){

							exec('docker run -d -p '.$db['mappings'][$id]['port'].':80  tutum/hello-world', $newOut);

							//update mappings
							$db['mappings'][$id]['con_id'] = substr($newOut[0], 0,12);
							file_put_contents(__DIR__.'/database.json', json_encode($db));

							die('resource deleted and  mappings updated');

							
						}
						else{
							die("resouce not deleted");
						}


					}else{

						die("no resource with this id");
					}
					exit;
				}

				//reverse prxy
				if(  is_numeric($paths[1]) ){

					$id = $paths[1] - 1;

					if( isset($db['mappings'][$id]) && isset($db['mappings'][$id]['sess_id']) && $db['mappings'][$id]['sess_id'] == session_id()   ){

						$curl = curl_init();
						$proxy_uri = $paths;
						unset($proxy_uri[0]);unset($proxy_uri[1]);

						$url = 'http://127.0.0.1'.':'.$db['mappings'][$id]['port'].'/'.implode('/', $proxy_uri);
						//echo $url;
						//exit;
						curl_setopt_array($curl, array(

						    CURLOPT_RETURNTRANSFER => 1,
						    CURLOPT_URL => $url,
						    CURLOPT_USERAGENT => 'Codular Sample cURL Request',
						));
						$resp = curl_exec($curl);
						curl_close($curl);

						echo $resp;
						exit;
					
					}else{

						die("No resource is allocated to you");
					}

				}



			}

			break;

	
	default:
		die('path not found');
		break;
}

function getCotainerPorts($containerIds=array()){
	
	$return = array();
	foreach ($containerIds as $id ) {
		$out =  exec('docker port '.$id);
		$return[$id] = substr($out, strrpos($out, ':')+1); 
	}
	return $return;
}

?>
