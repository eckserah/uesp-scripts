<?php


$UESP_UPGRADING_MW = 1;




class CUespUpgradeMW
{
	public $inputVersion = "";
	public $inputSrcWikiPath = "";
	public $inputDestWikiPath = "";
	public $realSrcWikiPath = "";
	public $realDestWikiPath = "";
	
	
	public $FILES_TO_COPY = [
			"LocalSettings.php",
			"config",
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
				$this->realSrcWikiPath = realpath($arg);
			}
			elseif ($argCount == 2)
			{
				$this->inputDestWikiPath = $arg;
				$this->realDestWikiPath = realpath($arg);
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
		global $UESP_SKIN_INFO;
		global $UESP_EXT_DEFAULT;
		global $UESP_EXT_UPGRADE;
		global $UESP_EXT_OTHER;
		global $UESP_EXT_NONE;
		global $UESP_EXT_IGNORE;
		global $UESP_EXT_SECONDARY;
		
		if ($this->inputVersion == "") return $this->ReportError("Missing required VERSION input!");
		if ($this->inputSrcWikiPath == "") return $this->ReportError("Missing required SRCWIKIPATH input!");
		if ($this->inputDestWikiPath == "") return $this->ReportError("Missing required DESTWIKIPATH input!");
		
		if (!preg_match('/[0-9]_[0-9][0-9]/', $this->inputVersion)) return $this->ReportError("Version '{$this->inputVersion}' does not match expected format of #_##!");
		
		if (!is_dir($this->inputSrcWikiPath)) return $this->ReportError("Source wiki path '{$this->inputSrcWikiPath}' is not a valid directory!");
		if (!is_dir($this->inputDestWikiPath)) return $this->ReportError("Destination wiki path '{$this->inputDestWikiPath}' is not a valid directory!");
		
		$file = $this->inputSrcWikiPath . "/config/Extensions.php";
		if (!file_exists($file)) return $this->ReportError("Missing file '$file' in source wiki path!");
		
		include($file);
		
		if ($UESP_EXTENSION_INFO == NULL) return $this->ReportError("Missing global UESP_EXTENSION_INFO data from '$file'!");
		if ($UESP_SKIN_INFO == NULL) return $this->ReportError("Missing global UESP_SKIN_INFO data from '$file'!");
		
		$count = count($UESP_EXTENSION_INFO);
		print("\tLoaded info for $count extensions from '$file'.\n");
		$skincount = count($UESP_SKIN_INFO);
		print("\tLoaded info for $skincount skins from '$file'.\n");
		
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
		
		$src  = $this->realSrcWikiPath  . "/extensions/" . $extName;
		$dest = $this->realDestWikiPath . "/extensions/" . $extName;
		$cmd = "cp -Rp \"$src\" \"$dest\"";
		
		$result = exec($cmd, $output, $resultCode);
		
		if ($result === false || $resultCode != 0) 
		{
			$output = implode("\n", $output);
			print("\t\tError: Failed to copy extension $extName ($resultCode)!\n$output");
			return false;
		}
		
		return true;
	}
	
	protected function CopySkin($skinName)
	{
		print("\t$skinName: Copying from source wiki\n");
		
		$src  = $this->realSrcWikiPath  . "/skins/" . $skinName;
		$dest = $this->realDestWikiPath . "/skins/" . $skinName;
		$cmd = "cp -Rp \"$src\" \"$dest\"";
		
		$result = exec($cmd, $output, $resultCode);
		
		if ($result === false || $resultCode != 0) 
		{
			$output = implode("\n", $output);
			print("\t\tError: Failed to copy skin $skinName ($resultCode)!\n$output");
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
		global $UESP_EXT_IGNORE;
		global $UESP_EXT_SECONDARY;
		
		if ($extType == $UESP_EXT_IGNORE)
		{
			return true;
		}
		
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
			print("\t\t$extName: WARNING: Must be upgraded manually!\n");
			return $this->CopyExtension($extName);
		}
		
		print("\t$extName: Upgrading...\n");
		
		$cmd = "uesp-getmwext \"$extName\" {$this->inputVersion}";
		
		if ($extType == $UESP_EXT_SECONDARY)
		{
			$cmd .= " 1";
		}
		
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
	
	protected function UpgradeSkin($skinName, $skinType)
	{
		global $UESP_EXT_DEFAULT;
		global $UESP_EXT_UPGRADE;
		global $UESP_EXT_OTHER;
		global $UESP_EXT_NONE;
		global $UESP_EXT_IGNORE;
		global $UESP_EXT_SECONDARY;
		
		if ($skinType == $UESP_EXT_IGNORE)
		{
			return true;
		}
		
		if ($skinType == $UESP_EXT_NONE)
		{
			return $this->CopySkin($skinName);
		}
		
		if ($skinType == $UESP_EXT_DEFAULT)
		{
			return true;
		}
		
		if ($skinType == $UESP_EXT_OTHER)
		{
			print("\t\t$skinName: WARNING: Must be upgraded manually!\n");
			return $this->CopySkin($skinName);
		}
		
		print("\t$skinName: Upgrading...\n");
		
		$cmd = "uesp-getmwext \"$skinName\" {$this->inputVersion} 1";
		
		$result = exec($cmd, $output, $resultCode);
		
		if ($result === false || $resultCode != 0) 
		{
			$output = implode("\n", $output);
			print("\t\tError: Failed to upgrade skin! $output\n");
			$this->CopySkin($skinName);
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
	
	
	protected function FindMissingExtensions($extPath)
	{
		global $UESP_EXTENSION_INFO;
		
		$dir = new DirectoryIterator($extPath);
		$dirs = [];
		$foundDirs = [];
		$displayWarning = false;
		
		foreach ($dir as $fileInfo)
		{
			if ($fileInfo->isDir() && !$fileInfo->isDot()) 
			{
				$dirs[] = $fileInfo->getFilename();
				$foundDirs[$fileInfo->getFilename()] = true;
			}
		}
		
		foreach ($dirs as $dir)
		{
			$dirInfo = $UESP_EXTENSION_INFO[$dir];
			
			if ($dirInfo === null)
			{
				print("\tMissing '$dir' extension in UESP_EXTENSION_INFO data!\n");
				$displayWarning = true;
			}
		}
		
		foreach ($UESP_EXTENSION_INFO as $dir => $dirData)
		{
			$dirInfo = $foundDirs[$dir];
			
			if ($dirInfo === null)
			{
				print("\tExtension '$dir' not found in source wiki path!\n");
				$displayWarning = true;
			}
		}
		
		if ($displayWarning)
		{
			$input = readline("Extension issues found! Enter 'dev' if you wish to proceed:");
			if ($input != "dev") exit();
		}
		
		return true;
	}
	
	protected function FindMissingSkins($extPath)
	{
		global $UESP_SKIN_INFO;
		
		$dir = new DirectoryIterator($extPath);
		$dirs = [];
		$foundDirs = [];
		$displayWarning = false;
		
		foreach ($dir as $fileInfo)
		{
			if ($fileInfo->isDir() && !$fileInfo->isDot()) 
			{
				$dirs[] = $fileInfo->getFilename();
				$foundDirs[$fileInfo->getFilename()] = true;
			}
		}
		
		foreach ($dirs as $dir)
		{
			$dirInfo = $UESP_SKIN_INFO[$dir];
			
			if ($dirInfo === null)
			{
				print("\tMissing '$dir' skin in UESP_SKIN_INFO data!\n");
				$displayWarning = true;
			}
		}
		
		foreach ($UESP_SKIN_INFO as $dir => $dirData)
		{
			$dirInfo = $foundDirs[$dir];
			
			if ($dirInfo === null)
			{
				print("\tSkin '$dir' not found in source wiki path!\n");
				$displayWarning = true;
			}
		}
		
		if ($displayWarning)
		{
			$input = readline("Skin issues found! Enter 'dev' if you wish to proceed:");
			if ($input != "dev") exit();
		}
		
		return true;
	}
	
	
	protected function DoUpgrade()
	{
		global $UESP_EXTENSION_INFO;
		global $UESP_SKIN_INFO;
		
		$this->CopyFiles();
		
		$cwd = getcwd();
		// Check and upgrade skins
		$skinDir = $this->inputDestWikiPath . "/skins";
		$this->FindMissingSkins($this->inputSrcWikiPath . "/skins");
		
		if (!chdir($skinDir)) return $this->ReportError("Failed to change to '$skinDir'!");
		print("\n\tAttempting to upgrade skins\n");
		foreach ($UESP_SKIN_INFO as $skinName => $skinType)
		{
			$this->UpgradeSkin($skinName, $skinType);
		}
		
		chdir($cwd);
		
		// Check and upgrade extensions
		$extDir = $this->inputDestWikiPath . "/extensions";
		$this->FindMissingExtensions($this->inputSrcWikiPath . "/extensions");
		
		if (!chdir($extDir)) return $this->ReportError("Failed to change to '$extDir'!");
		print("\n\tAttempting to upgrade extensions\n");
		foreach ($UESP_EXTENSION_INFO as $extName => $extType)
		{
			$this->UpgradeExtension($extName, $extType);
		}
		
		chdir($cwd);
		return true;
	}
	
	protected function DoComposerUpdate()
	{
		$cmd = "uesp-updatecomposer \"".$this->inputDestWikiPath."\"";
		print("\n\tUpdating Composer for wiki, extensions, and skins\n");
		$result = exec($cmd, $output, $resultCode);
		
		if ($result === false || $resultCode != 0) 
		{
			$output = implode("\n", $output);
			print("\t\tError: Failed to run composer updates! $output\n");
			return false;
		}
		
		return true;
		
	}
	
	
	public function Upgrade()
	{
		if (!$this->CheckArgs()) return $this->ReportError("Aborting upgrade!");;
		if (!$this->PromptUser()) return $this->ReportError("Aborting upgrade!");;
		
		$this->DoUpgrade();
		
		$this->DoComposerUpdate();
		
		return true;
	}
	
};


$upgradeMW = new CUespUpgradeMW();
$upgradeMW->Upgrade();