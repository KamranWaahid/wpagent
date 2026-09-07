<?php
/**
 * 400-class error that can carry structured details for MCP clients.
 *
 * @package WPAgent
 */

class WPAgent_Validation_Exception extends InvalidArgumentException {

	/**
	 * Extra fields merged into the REST error `details` object.
	 *
	 * @var array<string, mixed>
	 */
	public array $details = array();
}
