<?php
/*
 * uesp-checkservers.php -- Dave Humphrey, 3 Oct 2024 (dave@uesp.net)
 * 
 * Simple script to check basic functionality of servers and services.
 * 
 */

require_once("/home/uesp/secrets/uespservers.secrets");

class CUespCheckServers
{
	
	public function __construct()
	{
	}
	
	
	public function ReportLog($msg)
	{
		print($msg . "\n");
	}
	
	
	public function ReportError($msg)
	{
		print($msg . "\n");
	}
	
	
	public function PingPort($host, $port = 80, $name = "Unknown")
	{
		//https://github.com/geerlingguy/Ping/blob/1.x/JJG/Ping.php
		
		$start = microtime(true);
		$fp = @fsockopen($host, $port, $errno, $errstr, 20);
		
		if (!$fp) 
		{
			$latency = false;
    	}
		else
		{
			$latency = microtime(true) - $start;
			$latency = round($latency * 1000, 4);
		}
		
		if ($latency)
			$this->ReportLog("\t$host: Successfully pinged $name on port $port!");
		else
			$this->ReportError("\t$host: ERROR - Failed to ping $name on port $port!");
		
		return $latency;
	}
	
	
	public function Ping($host)
	{
		$host = escapeshellcmd($host);
		$cmd = "ping -n -c 1 $host";
		exec($cmd, $output, $return);
		
		$commandOutput = implode('', $output);
    	$output = array_values(array_filter($output));
    	$latency = false;
    	
    	//print_r($output);
    	
		if (!empty($output[1]))
		{
				//64 bytes from 10.12.222.25: icmp_seq=1 ttl=64 time=0.194 ms
			$response = preg_match("/time(?:=|<)(?<time>[\.0-9]+)(?:|\s)ms/", $output[1], $matches);
			if ($response > 0 && isset($matches['time'])) $latency = round($matches['time'], 4);
		}
		
		return $latency;
	}
	
	
	public function CheckServer ($ip, $name)
	{
		$result = $this->Ping($ip);
		
		if ($result)
			$this->ReportLog("\t$name: Successfully pinged IP address $ip (latency $result ms)!");
		else
			$this->ReportError("\t$name: ERROR - Failed to ping IP address $ip!");
		
		$result = $this->Ping("$name.uesp.net");
		
		if ($result)
			$this->ReportLog("\t$name: Successfully pinged domain $name.uesp.net (latency $result ms)!");
		else
			$this->ReportError("\t$name: ERROR - Failed to ping domain $name.uesp.net!");
		
	}
	
	
	public function CheckMount($path, $dir, $name)
	{
		$tmpFilename = $path . "UESPTESTFILE.tmp";
		$subDir = $path . $dir . "/";
		
		if (is_dir($subDir))
			$this->ReportLog("\t$path: Successfully accessed mount path $subDir for reading!");
		else
			$this->ReportError("\t$path: ERROR - Failed to access mount path $subDir for reading!");
		
		$result = touch($tmpFilename);
		
		if ($result)
			$this->ReportLog("\t$path: Successfully accessed mount $name with read/write!");
		else
			$this->ReportError("\t$path: ERROR - Failed to access mount $name with read/write!");
	}
	
	
	public function CheckAllServers()
	{
		global $UESP_SERVER_DB1;
		global $UESP_SERVER_DB2;
		global $UESP_SERVER_CONTENT1;
		global $UESP_SERVER_CONTENT2;
		global $UESP_SERVER_CONTENT3;
		global $UESP_SERVER_SQUID1;
		global $UESP_SERVER_SEARCH1;
		global $UESP_SERVER_FILES1;
		global $UESP_SERVER_BACKUP1;
		global $UESP_SERVER_MEMCACHED;
		global $UESP_SERVER_SEARCH;
		
		$this->CheckServer($UESP_SERVER_DB1, "db1");
		$this->CheckServer($UESP_SERVER_DB2, "db2");
		$this->CheckServer($UESP_SERVER_CONTENT1, "content1");
		$this->CheckServer($UESP_SERVER_CONTENT2, "content2");
		$this->CheckServer($UESP_SERVER_CONTENT3, "content3");
		$this->CheckServer($UESP_SERVER_SQUID1, "squid1");
		$this->CheckServer($UESP_SERVER_SEARCH1, "search1");
		$this->CheckServer($UESP_SERVER_FILES1, "files1");
		$this->CheckServer($UESP_SERVER_BACKUP1, "backup1");
		
		$this->PingPort($UESP_SERVER_CONTENT1, 80, "Apache");
		$this->PingPort($UESP_SERVER_CONTENT2, 80, "Apache");
		$this->PingPort($UESP_SERVER_CONTENT3, 80, "Apache");
		$this->PingPort($UESP_SERVER_FILES1, 80, "Lighttpd");
		$this->PingPort($UESP_SERVER_SEARCH1, 80, "Lighttpd");
		
		$this->PingPort($UESP_SERVER_CONTENT1, 443, "SSL Apache");
		$this->PingPort($UESP_SERVER_CONTENT2, 443, "SSL Apache");
		$this->PingPort($UESP_SERVER_CONTENT3, 443, "SSL Apache");
		$this->PingPort($UESP_SERVER_FILES1, 443, "SSL Lighttpd");
		$this->PingPort($UESP_SERVER_SEARCH1, 443, "SSL Lighttpd");
		
		$this->PingPort($UESP_SERVER_SQUID1, 80, "Varnish");
		$this->PingPort($UESP_SERVER_SQUID1, 443, "Nginx");
		$this->PingPort($UESP_SERVER_MEMCACHED, 11000, "Memcached");
		$this->PingPort($UESP_SERVER_SEARCH1, 9202, "ElasticSearch 2");
		$this->PingPort($UESP_SERVER_SEARCH1, 9205, "ElasticSearch 5");
		$this->PingPort($UESP_SERVER_SEARCH1, 9206, "ElasticSearch 6");
		$this->PingPort($UESP_SERVER_SEARCH1, 9207, "ElasticSearch 7");
		
		$this->PingPort($UESP_SERVER_DB1, 3306, "MySQL");
		$this->PingPort($UESP_SERVER_DB2, 3306, "MySQL");
		$this->PingPort($UESP_SERVER_BACKUP1, 3306, "MySQL");
		
		$this->CheckMount("/mnt/uesp/", "wikiimages", "UESP Files");
		$this->CheckMount("/mnt/sfwiki/", "wikiimages", "SFWiki Files");
	}
	
	
	public function Run()
	{
		//$UESP_SERVER_SEARCH = $UESP_SERVER_CONTENT2;
		//$UESP_SERVER_MEMCACHED = $UESP_SERVER_CONTENT1;
		$this->CheckAllServers();
		
	}
};

$checkServers = new CUespCheckServers();
$checkServers->Run();