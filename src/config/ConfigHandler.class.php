<?php

// +---------------------------------------------------------------------------+
// | This file is part of the Agavi package.                                   |
// | Copyright (c) 2003-2006 the Agavi Project.                                |
// | Based on the Mojavi3 MVC Framework, Copyright (c) 2003-2005 Sean Kerr.    |
// |                                                                           |
// | For the full copyright and license information, please view the LICENSE   |
// | file that was distributed with this source code. You can also view the    |
// | LICENSE file online at http://www.agavi.org/LICENSE.txt                   |
// |   vi: set noexpandtab:                                                    |
// |   Local Variables:                                                        |
// |   indent-tabs-mode: t                                                     |
// |   End:                                                                    |
// +---------------------------------------------------------------------------+

/**
 * AgaviConfigHandler allows a developer to create a custom formatted 
 * configuration file pertaining to any information they like and still 
 * have it auto-generate PHP code.
 *
 * @package    agavi
 * @subpackage config
 *
 * @author     Sean Kerr <skerr@mojavi.org>
 * @copyright  (c) Authors
 * @since      0.9.0
 *
 * @version    $Id$
 */
abstract class AgaviConfigHandler extends AgaviParameterHolder
{

	/**
	 * Add a set of replacement values.
	 *
	 * @param      string The old value.
	 * @param      string The new value which will replace the old value.
	 *
	 * @return     void
	 *
	 * @author     Sean Kerr <skerr@mojavi.org>
	 * @since      0.9.0
	 */
	public function addReplacement ($oldValue, $newValue)
	{

		$this->oldValues[] = $oldValue;
		$this->newValues[] = $newValue;

	}

	/**
	 * Execute this configuration handler.
	 *
	 * @param      string An absolute filesystem path to a configuration file.
	 *
	 * @return     string Data to be written to a cache file.
	 *
	 * @throws     <b>AgaviUnreadableException</b> If a requested configuration file
	 *                                             does not exist or is not readable.
	 * @throws     <b>AgaviParseException</b> If a requested configuration file is
	 *                                        improperly formatted.
	 *
	 * @author     Sean Kerr <skerr@mojavi.org>
	 * @since      0.9.0
	 */
	abstract function & execute ($config);

	/**
	 * Initialize this ConfigHandler.
	 *
	 * @param      array An associative array of initialization parameters.
	 *
	 * @return     bool true, if initialization completes successfully, 
	                    otherwise false.
	 *
	 * @throws     <b>AgaviInitializationException</b> If an error occurs while
	 *                                                 initializing the ConfigHandler
	 *
	 * @author     Sean Kerr <skerr@mojavi.org>
	 * @since      0.9.0
	 */
	public function initialize ($parameters = null)
	{

		if ($parameters != null)
		{

			$this->parameters = array_merge($this->parameters, $parameters);

		}

	}

	/**
	 * Literalize a string value.
	 *
	 * @param      string The value to literalize.
	 *
	 * @return     string A literalized value.
	 *
	 * @author     Sean Kerr <skerr@mojavi.org>
	 * @since      0.9.0
	 */
	public static function literalize ($value)
	{

		static
			$keys = array("\\", "%'", "'"),
			$reps = array("\\\\", "\"", "\\'");

		if ($value == null)
		{

			// null value
			return 'null';

		}

		// lowercase our value for comparison
		$value  = trim($value);
		$lvalue = strtolower($value);

		if ($lvalue == 'on' || $lvalue == 'yes' || $lvalue == 'true')
		{

			// replace values 'on' and 'yes' with a boolean true value
			return 'true';

		} else if ($lvalue == 'off' || $lvalue == 'no' || $lvalue == 'false')
		{

			// replace values 'off' and 'no' with a boolean false value
			return 'false';

		} else if (!is_numeric($value))
		{

			$value = str_replace($keys, $reps, self::replaceConstants($value));

			return "'" . $value . "'";

		}

		// numeric value
		return $value;

	}

	/**
	 * Replace constant identifiers in a string.
	 *
	 * @param      string The value on which to run the replacement procedure.
	 *
	 * @return     string The new value.
	 *
	 * @author     Sean Kerr <skerr@mojavi.org>
	 * @author     Johan Mjones(johan.mjones@ongame.co)
	 * @since      0.9.0
	 */
	public static function & replaceConstants ($value)
	{
		$newvalue = $value;
		do {
			$value = $newvalue;
			$newvalue = preg_replace_callback(
				'/\%(\w+?)\%/',
				create_function(
					'$match',
					'$constant = $match[1]; ' .
					'return (defined($constant) ? constant($constant) : "%".$constant."%");'
				),
				$value,
				1
			);
		} while ($newvalue != $value);

		return $value;
	}

	/**
	 * Replace a relative filesystem path with an absolute one.
	 *
	 * @param      string A relative filesystem path.
	 *
	 * @return     string The new path.
	 *
	 * @author     Sean Kerr <skerr@mojavi.org>
	 * @since      0.9.0
	 */
	public static function & replacePath ($path)
	{

		if (!AgaviToolkit::isPathAbsolute($path))
		{

			// not an absolute path so we'll prepend to it
			$path = AG_WEBAPP_DIR . '/' . $path;

		}

		return $path;

	}

}

?>