<?php

namespace pharext;

/**
 * Command line arguments
 */
class CliArgs implements \ArrayAccess
{
	/**
	 * Optional option
	 */
	const OPTIONAL = 0x000;
	
	/**
	 * Required Option
	 */
	const REQUIRED = 0x001;
	
	/**
	 * Only one value, even when used multiple times
	 */
	const SINGLE = 0x000;
	
	/**
	 * Aggregate an array, when used multiple times
	 */
	const MULTI = 0x010;
	
	/**
	 * Option takes no argument
	 */
	const NOARG = 0x000;
	
	/**
	 * Option requires an argument
	 */
	const REQARG = 0x100;
	
	/**
	 * Option takes an optional argument
	 */
	const OPTARG = 0x200;
	
	/**
	 * Option halts processing
	 */
	const HALT = 0x10000000;
	
	/**
	 * Original option spec
	 * @var array
	 */
	private $orig;
	
	/**
	 * Compiled spec
	 * @var array
	 */
	private $spec = [];
	
	/**
	 * Parsed args
	 * @var array
	 */
	private $args = [];

	/**
	 * Compile the original spec
	 * @param array $spec
	 */
	public function __construct(array $spec = null) {
		$this->compile($spec);
	}
	
	/**
	 * Compile the original spec
	 * @param array $spec
	 * @return pharext\CliArgs self
	 */
	public function compile(array $spec = null) {
		$this->orig = $spec;
		$this->spec = [];
		foreach ((array) $spec as $arg) {
			$this->spec["-".$arg[0]] = $arg;
			$this->spec["--".$arg[1]] = $arg;
		}
		return $this;
	}
	
	/**
	 * Parse command line arguments according to the compiled spec.
	 * 
	 * The Generator yields any parsing errors.
	 * Parsing will stop when all arguments are processed or the first option
	 * flagged CliArgs::HALT was encountered.
	 * 
	 * @param int $argc
	 * @param array $argv
	 * @return Generator
	 */
	public function parse($argc, array $argv) {
		for ($i = 0; $i < $argc; ++$i) {
			$o = $argv[$i];
			
			if (!isset($this->spec[$o])) {
				yield sprintf("Unknown option %s", $argv[$i]);
			} elseif (!$this->optAcceptsArg($o)) {
				$this[$o] = true;
			} elseif ($i+1 < $argc && !isset($this->spec[$argv[$i+1]])) {
				$this[$o] = $argv[++$i];
			} elseif ($this->optNeedsArg($o)) {
				yield sprintf("Option --%s needs an argument", $this->optLongName($o));
			} else {
				// OPTARG
				$this[$o] = $this->optDefaultArg($o);
			}
			
			if ($this->optHalts($o)) {
				return;
			}
		}
	}
	
	/**
	 * Validate that all required options were given.
	 * 
	 * The Generator yields any validation errors.
	 * 
	 * @return Generator
	 */
	public function validate() {
		$required = array_filter($this->orig, function($spec) {
			return $spec[3] & self::REQUIRED;
		});
		foreach ($required as $req) {
			if (!isset($this[$req[0]])) {
				yield sprintf("Option --%s is required", $req[1]);
			}
		}
	}
	
	/**
	 * Output command line help message
	 * @param string $prog
	 */
	public function help($prog) {
		printf("\nUsage:\n\n  $ %s", $prog);
		$flags = [];
		$required = [];
		$optional = [];
		foreach ($this->orig as $spec) {
			if ($spec[3] & self::REQARG) {
				if ($spec[3] & self::REQUIRED) {
					$required[] = $spec;
				} else {
					$optional[] = $spec;
				}
			} else {
				$flags[] = $spec;
			}
		}
		
		if ($flags) {
			printf(" [-%s]", implode("|-", array_column($flags, 0)));
		}
		foreach ($required as $req) {
			printf(" -%s <arg>", $req[0]);
		}
		if ($optional) {
			printf(" [-%s <arg>]", implode("|-", array_column($optional, 0)));
		} 
		printf("\n\n");
		foreach ($this->orig as $spec) {
			printf("    -%s|--%s %s", $spec[0], $spec[1], ($spec[3] & self::REQARG) ? "<arg>  " : (($spec[3] & self::OPTARG) ? "[<arg>]" : "       "));
			printf("%s%s %s", str_repeat(" ", 16-strlen($spec[1])), $spec[2], ($spec[3] & self::REQUIRED) ? "(REQUIRED)" : "");
			if (isset($spec[4])) {
				printf(" [%s]", $spec[4]);
			}
			printf("\n");
		}
		printf("\n");
	}
	
	/**
	 * Retreive the default argument of an option
	 * @param string $o
	 * @return mixed
	 */
	private function optDefaultArg($o) {
		$o = $this->opt($o);
		if (isset($this->spec[$o][4])) {
			return $this->spec[$o][4];
		}
		return null;
	}
	
	/**
	 * Retrieve the help message of an option
	 * @param string $o
	 * @return string
	 */
	private function optHelp($o) {
		$o = $this->opt($o);
		if (isset($this->spec[$o][2])) {
			return $this->spec[$o][2];
		}
		return "";
	}

	/**
	 * Check whether an option is flagged for halting argument processing
	 * @param string $o
	 * @return boolean
	 */
	private function optHalts($o) {
		$o = $this->opt($o);
		return $this->spec[$o][3] & self::HALT;
	}
	
	/**
	 * Check whether an option needs an argument
	 * @param string $o
	 * @return boolean
	 */
	private function optNeedsArg($o) {
		$o = $this->opt($o);
		return $this->spec[$o][3] & self::REQARG;
	}
	
	/**
	 * Check wether an option accepts any argument
	 * @param string $o
	 * @return boolean
	 */
	private function optAcceptsArg($o) {
		$o = $this->opt($o);
		return $this->spec[$o][3] & 0xf00;
	}
	
	/**
	 * Check whether an option can be used more than once
	 * @param string $o
	 * @return boolean
	 */
	private function optIsMulti($o) {
		$o = $this->opt($o);
		return $this->spec[$o][3] & self::MULTI;
	}
	
	/**
	 * Retreive the long name of an option
	 * @param string $o
	 * @return string
	 */
	private function optLongName($o) {
		$o = $this->opt($o);
		return $this->spec[$o][1];
	}
	
	/**
	 * Retreive the short name of an option
	 * @param string $o
	 * @return string
	 */
	private function optShortName($o) {
		$o = $this->opt($o);
		return $this->spec[$o][0];
	}
	
	/**
	 * Retreive the canonical name (--long-name) of an option
	 * @param string $o
	 * @return string
	 */
	private function opt($o) {
		if ($o{0} !== '-') {
			if (strlen($o) > 1) {
				$o = "-$o";
			}
			$o = "-$o";
		}
		return $o;
	}
	
	/**@+
	 * Implements ArrayAccess and virtual properties
	 */
	function offsetExists($o) {
		$o = $this->opt($o);
		return isset($this->args[$o]);
	}
	function __isset($o) {
		return $this->offsetExists($o);
	}
	function offsetGet($o) {
		$o = $this->opt($o);
		if (isset($this->args[$o])) {
			return $this->args[$o];
		}
		return $this->optDefaultArg($o);
	}
	function __get($o) {
		return $this->offsetGet($o);
	}
	function offsetSet($o, $v) {
		if ($this->optIsMulti($o)) {
			$this->args["-".$this->optShortName($o)][] = $v;
			$this->args["--".$this->optLongName($o)][] = $v;
		} else {
			$this->args["-".$this->optShortName($o)] = $v;
			$this->args["--".$this->optLongName($o)] = $v;
		}
	}
	function __set($o, $v) {
		$this->offsetSet($o, $v);
	}
	function offsetUnset($o) {
		unset($this->args["-".$this->optShortName($o)]);
		unset($this->args["--".$this->optLongName($o)]);
	}
	function __unset($o) {
		$this->offsetUnset($o);
	}
	/**@-*/
}
