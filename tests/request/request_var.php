<?php
/**
*
* @package testing
* @copyright (c) 2008 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

require_once 'test_framework/framework.php';
require_once '../phpBB/includes/request/type_cast_helper_interface.php';
require_once '../phpBB/includes/request/type_cast_helper.php';
require_once '../phpBB/includes/request/deactivated_super_global.php';
require_once '../phpBB/includes/request/interface.php';
require_once '../phpBB/includes/request/request.php';
require_once '../phpBB/includes/functions.php';

class phpbb_request_var_test extends phpbb_test_case
{
	/**
	* @dataProvider request_variables
	*/
	public function test_post($variable_value, $default, $multibyte, $expected)
	{
		$variable_name = 'name';
		$this->unset_variables($variable_name);

		$_POST[$variable_name] = $variable_value;
		$_REQUEST[$variable_name] = $variable_value;

		$result = request_var($variable_name, $default, $multibyte);

		$label = 'Requesting POST variable, converting from ' . gettype($variable_value) . ' to ' . gettype($default) . (($multibyte) ? ' multibyte' : '');
		$this->assertEquals($expected, $result, $label);
	}

	/**
	* @dataProvider request_variables
	*/
	public function test_get($variable_value, $default, $multibyte, $expected)
	{
		$variable_name = 'name';
		$this->unset_variables($variable_name);

		$_GET[$variable_name] = $variable_value;
		$_REQUEST[$variable_name] = $variable_value;

		$result = request_var($variable_name, $default, $multibyte);

		$label = 'Requesting GET variable, converting from ' . gettype($variable_value) . ' to ' . gettype($default) . (($multibyte) ? ' multibyte' : '');
		$this->assertEquals($expected, $result, $label);
	}

	/**
	* @dataProvider request_variables
	*/
	public function test_cookie($variable_value, $default, $multibyte, $expected)
	{
		$variable_name = 'name';
		$this->unset_variables($variable_name);

		$_GET[$variable_name] = false;
		$_POST[$variable_name] = false;
		$_REQUEST[$variable_name] = false;
		$_COOKIE[$variable_name] = $variable_value;

		$result = request_var($variable_name, $default, $multibyte, true);

		$label = 'Requesting COOKIE variable, converting from ' . gettype($variable_value) . ' to ' . gettype($default) . (($multibyte) ? ' multibyte' : '');
		$this->assertEquals($expected, $result, $label);
	}

	/**
	* Helper for unsetting globals
	*/
	private function unset_variables($var)
	{
		unset($_GET[$var], $_POST[$var], $_REQUEST[$var], $_COOKIE[$var]);
	}

	/**
	* @dataProvider deep_access
	* Only possible with 3.1.x (later)
	*/
	public function test_deep_multi_dim_array_access($path, $default, $expected)
	{
		$this->unset_variables('var');

		// cannot set $_REQUEST directly because in phpbb_request implementation
		// $_REQUEST = $_POST + $_GET
		$_POST['var'] = array(
			0 => array(
				'b' => array(
					true => array(
						5 => 'c',
						6 => 'd',
					),
				),
			),
			2 => array(
				3 => array(
					false => 5,
				),
			),
		);

		$result = request_var($path, $default);
		$this->assertEquals($expected, $result, 'Testing deep access to multidimensional input arrays: ' . $path);
	}

	public static function deep_access()
	{
		return array(
			// array(path, default, expected result)
			array(array('var', 0, 'b', true, 5), '', 'c'),
			array(array('var', 0, 'b', true, 6), '', 'd'),
			array(array('var', 2, 3, false), 0, 5),
			array(array('var', 0, 'b', true), array(0 => ''), array(5 => 'c', 6 => 'd')),
		);
	}

	public static function request_variables()
	{
		return array(
			// strings
			array('abc', '', false, 'abc'),
			array('  some  spaces  ', '', true, 'some  spaces'),
			array("\r\rsome\rcarriage\r\rreturns\r", '', true, "some\ncarriage\n\nreturns"),
			array("\n\nsome\ncarriage\n\nreturns\n", '', true, "some\ncarriage\n\nreturns"),
			array("\r\n\r\nsome\r\ncarriage\r\n\r\nreturns\r\n", '', true, "some\ncarriage\n\nreturns"),
			array("we\xC2\xA1rd\xE1\x9A\x80ch\xCE\xB1r\xC2\xADacters", '', true, "we\xC2\xA1rd\xE1\x9A\x80ch\xCE\xB1r\xC2\xADacters"),
			array("we\xC2\xA1rd\xE1\x9A\x80ch\xCE\xB1r\xC2\xADacters", '', false, "we??rd???ch??r??acters"),
			array("Some <html> \"entities\" like &", '', true, "Some &lt;html&gt; &quot;entities&quot; like &amp;"),

			// integers
			array('1234', 0, false, 1234),
			array('abc', 12, false, 0),
			array('324abc', 0, false, 324),

			// string to array
			array('123', array(0), false, array()),
			array('123', array(''), false, array()),

			// 1 dimensional arrays
			array(
				// input:
				array('123', 'abc'),
				// default:
				array(''),
				false,
				// expected:
				array('123', 'abc')
			),
			array(
				// input:
				array('123', 'abc'),
				// default:
				array(999),
				false,
				// expected:
				array(123, 0)
			),
			array(
				// input:
				array('xyz' => '123', 'abc' => 'abc'),
				// default:
				array('' => ''),
				false,
				// expected:
				array('xyz' => '123', 'abc' => 'abc')
			),
			array(
				// input:
				array('xyz' => '123', 'abc' => 'abc'),
				// default:
				array('' => 0),
				false,
				// expected:
				array('xyz' => 123, 'abc' => 0)
			),

			// 2 dimensional arrays
			array(
				// input:
				'',
				// default:
				array(array(0)),
				false,
				// expected:
				array()
			),
			array(
				// input:
				array(
					'xyz' => array('123', 'def'),
					'abc' => 'abc'
				),
				// default:
				array('' => array('')),
				false,
				// expected:
				array(
					'xyz' => array('123', 'def'),
					'abc' => array()
				)
			),
			array(
				// input:
				array(
					'xyz' => array('123', 'def'),
					'abc' => 'abc'
				),
				// default:
				array('' => array(0)),
				false,
				// expected:
				array(
					'xyz' => array(123, 0),
					'abc' => array()
				)
			),
			array(
				// input:
				array(
					0 => array(0 => array(3, '4', 'ab'), 1 => array()),
					1 => array(array(3, 4)),
				),
				// default:
				array(0 => array(0 => array(0))),
				false,
				// expected:
				array(
					0 => array(0 => array(3, 4, 0), 1 => array()),
					1 => array(array(3, 4))
				)
			),
			array(
				// input:
				array(
					'ü' => array(array('c' => 'd')),
					'ä' => array(4 => array('a' => 2, 'ö' => 3)),
				),
				// default:
				array('' => array(0 => array('' => 0))),
				false,
				// expected:
				array(
					'??' => array(4 => array('a' => 2, '??' => 3)),
				)
			),
			array(
				// input:
				array(
					'ü' => array(array('c' => 'd')),
					'ä' => array(4 => array('a' => 2, 'ö' => 3)),
				),
				// default:
				array('' => array(0 => array('' => 0))),
				true,
				// expected:
				array(
					'ü' => array(array('c' => 0)),
					'ä' => array(4 => array('a' => 2, 'ö' => 3)),
				)
			),
		);
	}

}
