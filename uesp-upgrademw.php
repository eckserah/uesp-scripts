<?php


$UESP_UPGRADING_MW = 1;




class CUespUpgradeMW
{
	public $inputVersion = "";
	public $inputSrcWikiPath = "";
	public $inputDestWikiPath = "";
	
	
	public $FILES_TO_COPY = [
			"LocalSettings.php",
			"config",
			"skins/UespMonoBook",
			"skins/UespVector",
	];
	
	
	public function __construct()
	{
		
		if (!$this->ParseArgs()) 
		{
			$this->ShowHelp();
			exit();
		}
	}
	
	
	
	protected function ReportError($msg)
	{
		print("Error: $msg\n");
		return false;
	}
	
	
	protected function ShowHelp()
	{
		print("Format: uesp-upgrademw VERSION SRCWIKIPATH DESTWIKIPATH\n");
		print("           VERSION: 1_27 (or similar depending on wiki version)\n");
		print("           SRCWIKIPATH: Full path to existing dev wiki\n");
		print("           DESTWIKIPATH: Full path to new dev wiki with default MW files\n");
	}
	
	
	protected function ParseArgs()
	{
		global $argv;
		
		$argCount = 0;
		
		for ($i = 1; $i < count($argv); ++$i)
		{
			$arg = trim($argv[$i]);
			
			if ($arg == "") continue;
			
			if ($argCount == 0)
			{
				$this->inputVersion = $arg;
			}
			elseif ($argCount == 1)
			{
				$this->inputSrcWikiPath = $arg;
			}
			elseif ($argCount == 2)
			{
				$this->inputDestWikiPath = $arg;
			}
			else
			{
				return $this->ReportError("Unknown argument '$arg' found!"); 
			}
			
			++$argCount;
		}
		
		$this->inputSrcWikiPath = rtrim($this->inputSrcWikiPath, "/");
		$this->inputDestWikiPath = rtrim($this->inputDestWikiPath, "/");
		return true;
	}
	
	
	protected function CheckArgs()
	{
		global $UESP_UPGRADING_MW;
		global $UESP_EXTENSION_INFO;
		global $UESP_EXT_DEFAULT;
		global $UESP_EXT_UPGRADE;
		global $UESP_EXT_OTHER;
		global $UESP_EXT_NONE;
		
		if ($this->inputVersion == "") return $this->ReportError("Missing required VERSION input!");
		if ($this->inputSrcWikiPath == "") return $this->ReportError("Missing required SRCWIKIPATH input!");
		if ($this->inputDestWikiPath == "") return $this->ReportError("Missing required DESTWIKIPATH input!");
		
		if (!preg_match('/[0-9]_[0-9][0-9]/', $this->inputVersion)) return $this->ReportError("Version '{$this->inputVersion}' does not match expected format of #_##!");
		
		if (!is_dir($this->inputSrcWikiPath)) return $this->ReportError("Source wiki path '{$this->inputSrcWikiPath}' is not a valid directory!");
		if (!is_dir($this->inputDestWikiPath)) return $this->ReportError("Destination wiki path '{$this->inputDestWikiPath}' is not a valid directory!");
		
		$file = $this->inputSrcWikiPath . "/config/Extensions.php";
		if (!file_exists($file)) return $this->ReportError("Missing file '$file' in source wiki path!");
		
		include($file);
		
		if ($UESP_EXTENSION_INFO == NULL) return $this->ReportError("Missing global $$UESP_EXTENSION_INFO data from '$file'!");
		
		$count = count($UESP_EXTENSION_INFO);
		print("\tLoaded info for $count extensions from '$file'.\n");
		
		return true;
	}
	
	
	protected function PromptUser()
	{
		print("\t Current Wiki: {$this->inputSrcWikiPath}\n");
		print("\tInstalling to: {$this->inputDestWikiPath}\n");
		print("\t Wiki Version: {$this->inputVersion}\n");
		
		$input = readline("You should only be upgrading a newly installed MediaWiki directory on dev. Enter 'dev' if you wish to proceed:");
		if ($input != "dev") return false;
		
		return true;
	}
	
	
	protected function CopyExtension($extName)
	{
		print("\t$extName: Copying from source wiki\n");
		
		$src  = $this->inputSrcWikiPath  . "/extensions/" . $extName;
		$dest = $this->inputDestWikiPath . "/extensions/" . $extName;
		
		$result = exec("cp -Rp \"$src\" \"$dest\"", $output, $resultCode);
		
		if ($result === false || $resultCode != 0) 
		{
			$output = implode("\n", $output);
			print("\t\tError: Failed to copy extension!\n$output");
			return false;
		}
		
		return true;
	}
	
	
	protected function UpgradeExtension($extName, $extType)
	{
		global $UESP_EXT_DEFAULT;
		global $UESP_EXT_UPGRADE;
		global $UESP_EXT_OTHER;
		global $UESP_EXT_NONE;
		
		if ($extType == $UESP_EXT_NONE) 
		{
			return $this->CopyExtension($extName);
		}
		
		if ($extType == $UESP_EXT_DEFAULT) 
		{
			return true;
		}
		
		if ($extType == $UESP_EXT_OTHER) 
		{
			print("\t$extName: WARNING: Must be upgraded manually!\n");
			return $this->CopyExtension($extName);
		}
		
		print("\t$extName: Upgrading...\n");
		
		$cmd = "uesp-getmwext \"$extName\" {$this->inputVersion}";
		$result = exec($cmd, $output, $resultCode);
		
		if ($result === false || $resultCode != 0) 
		{
			$output = implode("\n", $output);
			print("\t\tError: Failed to upgrade extension! $output\n");
			$this->CopyExtension($extName);
			return false;
		}
		
		return true;
	}
	
	
	protected function CopyFiles()
	{
		foreach ($this->FILES_TO_COPY as $filename)
		{
			print("\tCopying: $filename\n");
			
			$src  = $this->inputSrcWikiPath  . "/" . $filename;
			$dest = $this->inputDestWikiPath . "/" . $filename;
			
			$result = exec("cp -Rp \"$src\" \"$dest\"", $output, $resultCode);
			
			if ($result === false || $resultCode != 0) 
			{
				$output = implode("\n", $output);
				print("\t\tError: Failed to copy files!\n$output");
			}
		}
		
		return true;
	}
	
	
	protected function DoUpgrade()
	{
		global $UESP_EXTENSION_INFO;
		
		$this->CopyFiles();
		
		$cwd = getcwd();
		$extDir = $this->inputDestWikiPath . "/extensions";
		
		if (!chdir($extDir)) return $this->ReportError("Failed to change to '$extDir'!");
		
		foreach ($UESP_EXTENSION_INFO as $extName => $extType)
		{
			$this->UpgradeExtension($extName, $extType);
		}
		
		chdir($cwd);
		return true;
	}
	
	
	public function Upgrade()
	{
		if (!$this->CheckArgs()) return $this->ReportError("Aborting upgrade!");;
		if (!$this->PromptUser()) return $this->ReportError("Aborting upgrade!");;
		
		$this->DoUpgrade();
		
		return true;
	}
	
};


$upgradeMW = new CUespUpgradeMW();
$upgradeMW->Upgrade();