<?php
//https://github.com/satoshibytes/NebulasTools/
/*
 * Note: This code has not been tested but has been taken from a larger repo (with a lot of edits) which has been confirmed as working.
 * This class works with a neb node using it's api via curl.
*/
$fromAddress = 'n1KxWR8ycXg7Kb9CPTtNjTTEpvka269PniB';//The address making the request and must also have it's keystore located on the server to sign the request.
$nodeId = 'everstake';//The node id that you want to check.
$voterData = new voterData();//Set class
$voterData->getVoterData($fromAddress, $nodeId);//Call the function

class voterData
{
	private $totalNAXStakedToNode; //Store the amount of NAX voted for the node
	private $numberOfAddressesVoting = 0;//How many addresses voted
	private $voterData; //The voter data from the getVoterData function

	public function getVoterData($fromAddress, $nodeId)//Get a list of voters and the total amount of NAX staked.
	{
		$data = '{"from":"' . $fromAddress . '","to":"n214bLrE3nREcpRewHXF7qRDWCcaxRSiUdw","value":"0","nonce":1,"gasPrice":"1000000000000","gasLimit":"200000","contract":{"function":"getNodeVoteStatistic","args":"[\"' . $nodeId . '\"]"}}';//getNodeVoteStatistic return a array of voters addresses and the staked quantity.
		$curlRequest = $this->curlRequest('https://mainnet.nebulas.io/v1/user/call', $data, $timeout = 15);
//set the data to an array

		if ($curlRequest['status'] == 'success') {
//Requires multiple trips through the array to get the data we want (for some reason?)
			$dataResult = json_decode($curlRequest['data'], true);
			echo"Data Result:";
			echo print_r($dataResult);
			$dataResult = $dataResult['result']['result'];
			$dataResult = json_decode($dataResult, true);

			foreach ($dataResult as $thisAddressData) {
				$this->numberOfAddressesVoting++;
				$this->voterData[$thisAddressData['address']] = ['votedNax' => $thisAddressData['value']];
				$this->totalNAXStakedToNode += $thisAddressData['value'];
			}
			//$this->verboseLog("Total NAX: {$this->totalNAX}");
			//$this->verboseLog($this->voterData);
			$this->storeMessages('getVoterData()', "Total NAX for node {$nodeId}: {$this->totalNAXStakedToNode}", 'info');
			$this->storeMessages('getVoterData()', "$this->voterData", 'info');
			return array($this->totalNAXStakedToNode, $this->numberOfAddressesVoting);//Place data in array to return it via the initial call. Alternatively, this can be commented out and grab the locally stored variables.
		} else //Something didn't work...
			return null;
	}

	private function curlRequest($url, $req = null, $timeout = 15)
	{//Standard curl call (GET default)
		//	$this->verboseLog("Entered curlRequest() ->$req");
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($req)));
		if ($req != null) {
			//$this->verboseLog("Curl Post Fields: $req");
			//	$curlOptions += [CURLOPT_POSTFIELDS => $req, CURLOPT_POST => true,];
			curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
			curl_setopt($ch, CURLOPT_POST, true);
		}
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		//curl_setopt_array($ch, $curlOptions);
		$data = curl_exec($ch);
		$errors = curl_error($ch);
		$response = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if (curl_errno($ch)) {
			$this->storeMessages('curlRequest()', 'Curl request failed. URL: ' . $url, 'error');
		} else {//Successful response
			$status = 'success';
			$this->storeMessages('curlRequest()', 'Curl request succeeded. URL: ' . $url, 'info');
		}
		curl_close($ch);//close curl
		return ['status' => $status,
		        'data'   => $data, 'error' => $errors];
	}

	private $logEchoNumber;//The log will display the entry number
	private $severityMessageArray = [0 => 'success', 1 => 'info', 2 => 'notify', 3 => 'warn', 4 => 'error'];
	private $severityMessageMax;
	private $messages = []; //store any messages from the processes. All messages contain a result field which can be either success, warn, fail.

	private function storeMessages($function, $message, $severity, $verbose = true)
	{//Simple way to store messages
		//$this->storeMessages($function, $message, $severity);
		$severityId = array_search($severity, $this->severityMessageArray);
		if ($severityId > $this->severityMessageMax)
			$this->severityMessageMax = $severityId;

		$this->messages[] = [
			'function'    => $function,
			'messageRead' => $message,
			'result'      => $severity,
			'time'        => time()
		];
		if ($verbose)
			$this->verboseLog($message, $severity);
	}

	private $config = ['logOutputType' => 'echo', 'logName' => 'thisLog.log'];

	private function verboseLog($val, $severity = 'info')
	{//Primarily used for debugging - can be disabled in the config
		$severityId = array_search($severity, $this->severityMessageArray);
		if ($severityId > $this->severityMessageMax)
			$this->severityMessageMax = $severityId;
		if (is_array($val))
			$val = print_r($val, true);
		$now = date("m j, Y, H:i:s");
		if ($this->config['logOutputType'] != false) {
			$logEntry = $now . ' | ' . $this->logEchoNumber . ' | ' . $val . "\n";
			$this->logEchoNumber++;
			if ($this->config['logOutputType'] == 'echo') {
				echo $logEntry;
			} else {//Write to log
				file_put_contents($this->config['logName'], $logEntry, FILE_APPEND);
			}
		}
	}
}
