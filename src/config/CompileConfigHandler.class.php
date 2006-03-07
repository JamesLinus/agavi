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
 * AgaviCompileConfigHandler gathers multiple files and puts them into a single
 * file. Upon creation of the new file, all comments and blank lines are removed.
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
class AgaviCompileConfigHandler extends AgaviConfigHandler
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

		if (!is_readable($config))
		{

			// can't read the configuration
			$error = 'Configuration file "%s" does not exist or is not ' .
				     'readable';
			$error = sprintf($error, $config);

			throw new AgaviUnreadableException($error);

		}

		// load the entire file
		$files = @file($config);

		if ($files === false)
		{

			// configuration couldn't be parsed
			$error = 'Configuration file "%s" could not be parsed';
			$error = sprintf($error, $config);

			throw new AgaviParseException($error);

		}

		// init our data
		$data = '';

		// let's do our fancy work
		foreach ($files as &$file)
		{

			$file = trim($file);

			if (strlen($file) > 0 && substr($file, 0, 1) != ';')
			{

				// we'll assume this is a file since the line does not start
				// with a semi-colon (used for commenting)

				$file = $this->replaceConstants($file);
				$file = $this->replacePath($file);

				if (!is_readable($file))
				{

				    // file doesn't exist
				    $error = 'Configuration file "%s" specifies nonexistent ' .
						     'or unreadable file "%s"';
				    $error = sprintf($error, $config, $file);

				    throw new AgaviParseException($error);

				}

				$contents = @file_get_contents($file);

				// append file data
				$data .= "\n" . $contents;

			}

		}

		// replace windows and mac format with unix format
		$data = str_replace("\r\n", "\n", $data);
		$data = str_replace("\r", "\n", $data);

		// strip php tags
		$data = preg_replace("/<\?php/", '', $data);
		$data = preg_replace("/<\?/", '', $data);
		$data = preg_replace("/\?>/", '', $data);

		// strip comments
		$data = preg_replace("/\/\*(.*?)\*\//s", "\n", $data);
		$data = preg_replace("/\s*\/\/.*?\n/s", "\n", $data);

		// replace multiple new lines with a single newline
		$data = preg_replace("/\n\s+\n/s", "\n", $data);
		$data = preg_replace("/\n+/s", "\n", $data);

		$data = trim($data);

		// compile data
		$retval = "<?php\n" .
				  "// auth-generated by CompileConfigHandler\n" .
				  "// date: %s\n%s\n?>";
		$retval = sprintf($retval, date('m/d/Y H:i:s'), $data);

		return $retval;

	}

}

?>