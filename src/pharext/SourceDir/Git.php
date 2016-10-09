<?php

namespace pharext\SourceDir;

use pharext\Cli\Args;
use pharext\Exception;
use pharext\ExecCmd;
use pharext\License;
use pharext\PackageInfo;
use pharext\SourceDir;
use pharext\Tempfile;

/**
 * Extension source directory which is a git repo
 */
class Git implements \IteratorAggregate, SourceDir
{
	use License;
	use PackageInfo;
	
	/**
	 * Base directory
	 * @var string
	 */
	private $path;
	
	/**
	 * @inheritdoc
	 * @see \pharext\SourceDir::__construct()
	 */
	public function __construct($path) {
		$this->path = $path;
	}

	/**
	 * @inheritdoc
	 * @see \pharext\SourceDir::getBaseDir()
	 */
	public function getBaseDir() {
		return $this->path;
	}

	/**
	 * @inheritdoc
	 * @return array
	 */
	public function getPackageInfo() {
		return $this->findPackageInfo($this->getBaseDir());
	}

	/**
	 * @inheritdoc
	 * @return string
	 */
	public function getLicense() {
		if (($file = $this->findLicense($this->getBaseDir()))) {
			return $this->readLicense($file);
		}
		return "UNKNOWN";
	}

	/**
	 * @inheritdoc
	 * @return array
	 */
	public function getArgs() {
		return [];
	}

	/**
	 * @inheritdoc
	 */
	public function setArgs(Args $args) {
	}

	/**
	 * Generate a list of files by `git ls-files`
	 * @return Generator
	 */
	private function generateFiles() {
		$pwd = getcwd();
		chdir($this->path);
		if (($pipe = popen("git ls-tree -r --name-only HEAD", "r"))) {
			$path = realpath($this->path);
			while (!feof($pipe)) {
				if (strlen($file = trim(fgets($pipe)))) {
					/* there may be symlinks, so no realpath here */
					yield "$path/$file";
				}
			}
			pclose($pipe);
		}
		chdir($pwd);
	}

	/**
	 * Implements IteratorAggregate
	 * @see IteratorAggregate::getIterator()
	 */
	public function getIterator() {
		return $this->generateFiles();
	}
}
