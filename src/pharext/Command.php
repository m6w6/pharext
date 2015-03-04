<?php

namespace pharext;

/**
 * Command interface
 */
interface Command
{
	/**
	 * Retrieve command line arguments
	 * @return pharext\CliArgs
	 */
	public function getArgs();
	
	/**
	 * Print info
	 * @param string $fmt
	 * @param string ...$args
	 */
	public function info($fmt);
	
	/**
	 * Print error
	 * @param string $fmt
	 * @param string ...$args
	 */
	public function error($fmt);
	
	/**
	 * Execute the command
	 * @param int $argc command line argument count
	 * @param array $argv command line argument list
	 */
	public function run($argc, array $argv);
}