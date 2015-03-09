<?php
#
#  Use this class to get a listing of files sorted by creation date
#
#	Basic usage:
#	Create an instance: 	$myFiles = new RecentFiles();
#	Set directory or directories to search by either:
#				$myFiles->directory = 'documents/'; 	// note that the path can be
#									// relative from webroot or absolute
#				OR
#				$myFiles->directories[] = 'documents';	// as an array will search all dirs
#				$myFiles->directories[] = 'oldDocs';
#		NOTE: Directories without a leading slash will be treated as relative to the webserver's root dir (ie: /var/www/localhost/htdocs/)
#			
#	Note: If BOTH $myFiles->directory AND $myFiles->directories are set, $myFiles->directory will be ignored
#
#	Set the maximum number of files to list:
#				$myFiles->numFiles = 5;
#	Get the files:		$myFiles->get();
#	$myFiles->files will now contain an associative array of the files with their last modified timestamps as the key and the filename (including path) as the value
#
#	Use the urlEncode() method to encode the filename for links
#	

class RecentFiles 
{
	public $directory;	// Directory to search, if more than one directory is desired, add each directory to the directories array instead
	public $directories;	// Array of directories to search. Overrides $directory. Use the addDirectory(directory) method to add directories
	public $numFiles;	// Max number of listings to retrieve. Set to -1 for no limit
	public $files;		// array of filenames
	public $maxAge;		// only look at files less than x seconds old
	public $minAge;		// only files >= this age (in seconds). maxAge trumps this if both are set.
	public $subdirs;	// boolean.  true = recursive;
	public $newestFirst; 	// True to sort by date descending
	public $inWebDir;	// set to false if the directory or directories are outside of the webserver's webroot

	private $documentPath;	// internally used storage for path information
	private $_subdir;	// temporary directory setting for internal use
	private $_excludes;	// Array list of filenames to exclude

	public function __construct()
	{
		$this->directory = "";
		$this->directories = array();
		$this->numFiles = 5;
		$this->files = array();
		$this->subdirs = true;
		$this->newestFirst = true;
		$this->maxAge = -1;
		$this->minAge = -1;
		$this->inWebDir = true;
		$this->showFullPath = false;

		$this->webroot = $_SERVER['DOCUMENT_ROOT'];
		$this->_excludes = array();

	}

	// Method to exclude files. Must be exact name (no wildcards/pattern matching)
	public function excludeFile($f)
	{
		if(strlen($f) < 1)
			return false;

		$this->_excludes[] = $f;
	}

	// Add directory to search 
	// Note that the path can be given relative to the webroot by ommitting the leading slash
	// Paths beginning with a forward slash are assumed to be absolute paths
	public function addDirectory($d)
	{
		$this->directories[] = $d;
	}

	public function urlEncode($f)
	{
		// return a string URL Encoding $f but not encoding forward slash
		return str_replace('%2F','/',rawurlencode($f));
	}

	private function addTrailingSlash()
	{
		if(substr($this->directory, strlen($this->directory), 1) != '/')
			$this->directory .= '/';
	}

	public function stripWebRoot()
	{
		// strip the /var/www/localhost from file paths
		$n = strlen($_SERVER['DOCUMENT_ROOT']);
		foreach($this->files as $k=>$v)
		{
			$this->files[$k] = substr($v,$n);
		}
	}
	private function _get()
	{
		if (!strlen($this->directory))
			return false;

		// Check if the directory is relative or absolute
		if(substr($this->directory,0,1) !== '/')
		{
			// Prepend the DOCUMENT ROOT if it's relative
			$this->directory = $_SERVER['DOCUMENT_ROOT'] . '/' . $this->directory;
		}

		if (!is_dir($this->directory))
			return false;

		$h = opendir($this->directory);

		if ($h === false)
		{
			// error!
			echo "Error opening directory" . $this->directory;
			exit();
		}

		while (false !== ($entry = readdir($h))) 
		{
			if ($entry == "." || $entry == "..")
				continue;

			if(is_dir($this->directory . $entry))
			{
				if(!$this->subdirs)
				{
					continue;
				}
				
				// Save the upper level dir
				$_dir = $this->directory;
				$this->_subdir = $entry;
				$this->directory .= $entry . '/';

				$this->_get();

				$this->directory = $_dir;
				$this->_subdir = '';
			}
			elseif ($entry != "." && $entry != ".." && !in_array($entry, $this->_excludes))
			{
				// Only run this part if the item is not reference to current directory, 
				//	upper level directory, and is not in the excludes array
				$m = @filemtime($this->directory . $entry);
				// strip the document_root off of the path
				if(!$this->showFullPath)
					$n = strlen($this->documentPath);
				else	
					$n = 0;
				
				// verify that there's not a file with the same mtime - if so, increment
				while(array_key_exists($m, $this->files))
					$m++;

				if($this->maxAge == -1 && $this->minAge > 0)
				{
					if($m <= (time() - $this->minAge))
						$this->files[$m] = substr($this->directory . $entry, $n);
				}
				elseif($this->maxAge < 0 || $m > (time() - $this->maxAge))
				{
					$this->files[$m] = substr($this->directory . $entry, $n);
				}
				elseif($this->maxAge == -1 && $this->minAge == -1)
				{
					$this->files[$m] = substr($this->directory . $entry, $n);
				}
			}
		}
		closedir($h);


	}

	public function get()
	{
		if($this->directory == '' && count($this->directories) == 0)
			return false;
		if(count($this->directories) == 0)
			$this->directories[0] = $this->directory;

		// clear out any stale files
		$this->files = array();

		foreach($this->directories as $d)
		{
			// if it's a relative path, add the webserver's root path
			if (strpos($d, $this->webroot) !== 0 && $this->inWebDir)
				$d = $this->webroot . '/' . $d;

			$this->addTrailingSlash();
			$this->directory = $d;	
			$this->documentPath = $this->directory;

			$this->_get();
		}

		// sort, and then truncate the array
		if ($this->newestFirst)
			krsort($this->files);   // sort descending
		else
			ksort($this->files);	// descending

		if ($this->numFiles > 0)
			$this->files = array_slice($this->files, 0, $this->numFiles, true);
	}

	public function getNewestTimeStamp()
	{
		// make a copy of the object to reset parameters back
		$_tmpObj = clone $this;
		
		$this->numFiles = 1;
		$this->maxAge = -1;
		$this->minAge = -1;
		$this->newestFirst = true;
		$this->files = array();

		$this->get();

		if(count($this->files) == 0)
			return false;
		$newest = array_keys($this->files)[0];

		$this->numFiles = $_tmpObj->numFiles;
		$this->maxAge = $_tmpObj->maxAge;
		$this->minAge = $_tmpObj->minAge;
		$this->files = $_tmpObj->files;
		$this->newestFirst = $_tmpObj->newestFirst;

		return $newest;
	}


}
