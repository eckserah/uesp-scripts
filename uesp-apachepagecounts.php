<?php

//50.65.238.201 - - [20/May/2024:10:49:58 -0400] "GET /resources/esoSkillClient.css?ver=6.5.3 HTTP/1.1" 200 614 "https://deltiasgaming.com/" "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:126.0) Gecko/20100101 Firefox/126.0"

class CApacheLogParse
{
	
	public $LOGFILE = "/var/log/httpd/access_log";
	public $LINECOUNT = 10000;
	
	public $lines = [];
	public $ipAddresses = [];
	public $startTime = 0;
	public $startDate = "";
	public $minCount = 5 ;
	
	
	public function __construct()
	{
	}
	
	
	public function LoadLines()
	{
		$cmd = "tail {$this->LOGFILE} -n {$this->LINECOUNT}";
		//print ($cmd . "\n");
		$this->lines = explode("\n", shell_exec($cmd));
		$count = count($this->lines);
		print ("Loaded $count lines from {$this->LOGFILE}!\n");
	}
	
	
	public function ParseLines()
	{
		$matchCount = 0;
		$isFirst = true;
		
		foreach ($this->lines as $line)
		{
			$isMatched = preg_match('/^([0-9.]+) - - \[(.*)\] "GET/', $line, $matches);
			if (!$isMatched) continue;
			
			$matchCount++;
			$ipAddress = $matches[1];
			$reqDate = $matches[2];
			
			if ($isFirst)
			{
				$this->startDate = $reqDate;		//20/May/2024:10:49:58 -0400
				$d = DateTime::createFromFormat('j/M/Y:H:i:s O', $reqDate);
				$this->startTime = $d->getTimestamp();
				$isFirst = false;
			}
			
			$this->ipAddresses[$ipAddress] += 1;
		}
		
		
		if ($this->startDate)
		{
			$deltaTime = time() - $this->startTime;
			print("Found $matchCount matching lines since {$this->startDate} ($deltaTime secs)!\n");
		}
		else
		{
			print("Found $matchCount matching lines!\n");
		}
		
		arsort($this->ipAddresses);
	}
	
	
	public function OutputResults()
	{
		$count = count($this->ipAddresses);
		print("Found $count unique IP addresses (showing all with more than {$this->minCount} counts):\n");
		
		foreach ($this->ipAddresses as $ipAddress => $count)
		{
			if ($count <= $this->minCount) continue;
			print("\t$count = $ipAddress\n");
		}
	}
	
	
	public function Run()
	{
		$this->LoadLines();
		$this->ParseLines();
		
		$this->OutputResults();
	}
};


$parser = new CApacheLogParse();
$parser->Run();