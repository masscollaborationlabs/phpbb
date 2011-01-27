<?php
/**
*
* @package testing
* @copyright (c) 2010 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

require_once 'test_framework/framework.php';
require_once 'functions_acp/user_mock.php';
require_once '../phpBB/includes/functions_acp.php';

class phpbb_functions_acp_validate_config_vars_test extends phpbb_test_case
{
	/**
	* Helper function which returns a string in a given length.
	*/
	static public function return_string($length)
	{
		$string = '';
		for ($i = 0; $i < $length; $i++)
		{
			$string .= 'a';
		}
		return $string;
	}

	/**
	* Data sets that don't throw an error.
	*/
	public function validate_config_vars_fit_data()
	{
		return array(
			array(
				array(
					'test_bool'				=> array('lang' => 'TEST_BOOL',			'validate' => 'bool'),
					'test_string'			=> array('lang' => 'TEST_STRING',		'validate' => 'string'),
					'test_string_128'		=> array('lang' => 'TEST_STRING_128',	'validate' => 'string:128'),
					'test_string_32_64'		=> array('lang' => 'TEST_STRING_32_64',	'validate' => 'string:32:64'),
					'test_int'				=> array('lang' => 'TEST_INT',			'validate' => 'int'),
					'test_int_32'			=> array('lang' => 'TEST_INT',			'validate' => 'int:32'),
					'test_int_32_64'		=> array('lang' => 'TEST_INT',			'validate' => 'int:32:64'),
					'test_lang'				=> array('lang' => 'TEST_LANG',			'validate' => 'lang'),
					/*
					'test_sp'				=> array('lang' => 'TEST_SP',			'validate' => 'script_path'),
					'test_rpath'			=> array('lang' => 'TEST_RPATH',		'validate' => 'rpath'),
					'test_rwpath'			=> array('lang' => 'TEST_RWPATH',		'validate' => 'rwpath'),
					'test_path'				=> array('lang' => 'TEST_PATH',			'validate' => 'path'),
					'test_wpath'			=> array('lang' => 'TEST_WPATH',		'validate' => 'wpath'),
					*/
				),
				array(
					'test_bool'			=> true,
					'test_string'		=> self::return_string(255),
					'test_string_128'	=> self::return_string(128),
					'test_string_32_64'	=> self::return_string(48),
					'test_int'			=> 128,
					'test_int_32'		=> 32,
					'test_int_32_64'	=> 48,
					'test_lang'			=> 'en',
				),
			),
		);
	}

	/**
	* @dataProvider validate_config_vars_fit_data
	*/
	public function test_validate_config_vars_fit($test_data, $cfg_array)
	{
		global $user;

		$user->lang = new phpbb_mock_lang();

		$phpbb_error = array();
		validate_config_vars($test_data, $cfg_array, $phpbb_error);

		$this->assertEquals(array(), $phpbb_error);
	}

	/**
	* Data sets that throw the error.
	*/
	public function validate_config_vars_error_data()
	{
		return array(
			array(
				array('test_string_32_64'		=> array('lang' => 'TEST_STRING_32_64',	'validate' => 'string:32:64')),
				array('test_string_32_64'	=> self::return_string(20)),
				array('SETTING_TOO_SHORT'),
			),
			array(
				array('test_string'		=> array('lang' => 'TEST_STRING',	'validate' => 'string')),
				array('test_string'		=> self::return_string(256)),
				array('SETTING_TOO_LONG'),
			),
			array(
				array('test_string_32_64'	=> array('lang' => 'TEST_STRING_32_64',	'validate' => 'string:32:64')),
				array('test_string_32_64'	=> self::return_string(65)),
				array('SETTING_TOO_LONG'),
			),

			array(
				array('test_int_32'		=> array('lang' => 'TEST_INT',			'validate' => 'int:32')),
				array('test_int_32'		=> 31),
				array('SETTING_TOO_LOW'),
			),
			array(
				array('test_int_32_64'	=> array('lang' => 'TEST_INT',			'validate' => 'int:32:64')),
				array('test_int_32_64'	=> 31),
				array('SETTING_TOO_LOW'),
			),
			array(
				array('test_int_32_64'	=> array('lang' => 'TEST_INT',			'validate' => 'int:32:64')),
				array('test_int_32_64'	=> 65),
				array('SETTING_TOO_BIG'),
			),
			array(
				array(
					'test_int_min'	=> array('lang' => 'TEST_INT_MIN',		'validate' => 'int:32:64'),
					'test_int_max'	=> array('lang' => 'TEST_INT_MAX',		'validate' => 'int:32:64'),
				),
				array(
					'test_int_min'	=> 52,
					'test_int_max'	=> 48,
				),
				array('SETTING_TOO_LOW'),
			),
			array(
				array('test_lang'		=> array('lang' => 'TEST_LANG',			'validate' => 'lang')),
				array('test_lang'		=> 'this_is_no_language'),
				array('WRONG_DATA_LANG'),
			),
		);
	}

	/**
	* @dataProvider validate_config_vars_error_data
	*/
	public function test_validate_config_vars_error($test_data, $cfg_array, $expected)
	{
		global $user;

		$user->lang = new phpbb_mock_lang();

		$phpbb_error = array();
		validate_config_vars($test_data, $cfg_array, $phpbb_error);

		$this->assertEquals($expected, $phpbb_error);
	}
}
