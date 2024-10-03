<?php


require_once("/home/uesp/secrets/ipinfo.secrets");

$action = "countcurrent";
$foundRoutes = [];


function ReportError($msg)
{
	print($msg . "\n");
	return false;
}


function getIpInfo($ip)
{
	global $IPINFO_TOKEN;
	
	$url = "https://ipinfo.io/$ip/json?token=$IPINFO_TOKEN";
	//print($url . "\n");
	
	$text = file_get_contents($url);
	if (!$text) return ReportError("Error: Failed to received response from '$url'!");
	
	$json = json_decode($text, true);
	if (!$json) return ReportError("Error: Failed to parse JSON response from '$url'!");
	
	return $json;
}


function doCountCurrent()
{
	global $foundRoutes;
	
	$cmd = "netstat -an | egrep \"^tcp|^udp\" | grep \"ESTABLISHED\" | awk '{print $5}' | egrep \":[0-9]+$\" | cut -d: -f1 | sort | uniq -c | sort -n";
	
	$output = shell_exec($cmd);
	if (!$output) return ReportError("Error: Failed to run netstat command!");
	
	$lines = preg_split('/[\r\n]+/', $output);
	$count = count($lines);
	
	//print($output);
	
	print("Found $count lines\n");
	
	foreach ($lines as $line)
	{
		$cols =	preg_split("/\s+/", $line);
		$count = count($cols);
		if ($count < 3) continue;
		
		$connCount = $cols[1];
		$connIp = $cols[2];
		
		if (preg_match('/^10\.12\./', $connIp)) continue;
		if (preg_match('/^127\.0\./', $connIp)) continue;
		
		$ipInfo = getIpInfo($connIp);
		if (!$ipInfo) continue;
		
		$asn = $ipInfo['asn'];
		
		if (!$asn) 
		{
			print("\t$connIp: No ASN information!\n");
			print_r($ipInfo);
			continue;
		}
		
		$asnName = $asn['name'];
		$asnRoute = $asn['route'];
		$foundRoutes[$asnRoute] += 1;
		
		print("\t$connIp: $asnName, $asnRoute!\n");
		//print_r($cols);
	}
	
	print("Found Routes:\n");
	
	foreach ($foundRoutes as $route => $count)
	{
		print("\t$count: $route\n");
	}
	
	return true;
}


function doAction($action)
{
	if ($action == "countcurrent") return doCountCurrent();
	
	return ReportError("Error: Unknown action '$action'!");
}

doAction($action);