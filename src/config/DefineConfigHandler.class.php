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
 * AgaviDefineConfigHandler allows you to turn ini categories and key/value
 * pairs into defined PHP values.
 *
 * <b>Optional initialization parameters:</b>
 *
 * # <b>prefix</b> - The text prepended to all defined constant names.
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
class AgaviDefineConfigHandler extends AgaviIniConfigHandler
{

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
	public function & execute ($config)
	{

		// parse the ini
		$ini = $this->parseIni($config);

		// get our prefix
		$prefix = $this->getParameter('prefix');

		if ($prefix == null)
		{

			// no prefix has been specified
			$prefix = '';

		}

		// init our data array
		$data = array();

		// let's do our fancy work
		foreach ($ini as $category => &$keys)
		{

			// categories starting without a period will be prepended to the key
			if ($category{0} != '.')
			{

				$category = $prefix . $category . '_';

			} else
			{

				$category = $prefix;

			}

			// loop through all key/value pairs
			foreach ($keys as $key => &$value)
			{

				// prefix the key
				$key = $category . $key;

				// replace constant values
				$value = $this->replaceConstants($value);

				// literalize our value
				$value = $this->literalize($value);

				// append new data
				$tmp    = "define('%s', %s);";
				$data[] = sprintf($tmp, $key, $value);

			}

		}

		// compile data
		$retval = "<?php\n" .
				  "// auth-generated by DefineConfigHandler\n" .
				  "// date: %s\n%s\n?>";
		$retval = sprintf($retval, date('m/d/Y H:i:s'), implode("\n", $data));

		return $retval;

	}

}

?>