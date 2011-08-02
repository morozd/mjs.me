<?php
/**
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package    Fuel
 * @version    1.0
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2011 Fuel Development Team
 * @link       http://fuelphp.com
 */

namespace Fuel\Core;

/**
 * The Asset class allows you to easily work with your apps assets.
 * It allows you to specify multiple paths to be searched for the
 * assets.
 *
 * You can configure the paths by copying the core/config/asset.php
 * config file into your app/config folder and changing the settings.
 *
 * @package     Fuel
 * @subpackage  Core
 */
class Asset {

	/**
	 * @var  array  the asset paths to be searched
	 */
	protected static $_asset_paths = array();

	/**
	 * @var  string  the URL to be prepended to all assets
	 */
	protected static $_asset_url = '/';

	/**
	 * @var  bool  whether to append the file mtime to the url
	 */
	protected static $_add_mtime = true;

	/**
	 * @var  string  the folder names
	 */
	protected static $_folders = array(
		'css'  =>  'css/',
		'js'   =>  'js/',
		'img'  =>  'img/',
	);

	/**
	 * @var  array  Holds the groups of assets
	 */
	protected static $_groups = array();

	/**
	 * @var  bool  Get this baby going
	 */
	public static $initialized = false;

	/**
	 * This is called automatically by the Autoloader.  It loads in the config
	 * and gets things going.
	 *
	 * @return  void
	 */
	public static function _init()
	{
		// Prevent multiple initializations
		if (static::$initialized)
		{
			return;
		}

		\Config::load('asset', true);

		$paths = \Config::get('asset.paths');

		foreach($paths as $path)
		{
			static::add_path($path);
		}

		static::$_add_mtime = \Config::get('asset.add_mtime', true);
		static::$_asset_url = \Config::get('asset.url');

		static::$_folders = array(
			'css'	=>	\Config::get('asset.css_dir'),
			'js'	=>	\Config::get('asset.js_dir'),
			'img'	=>	\Config::get('asset.img_dir')
		);

		static::$initialized = true;
	}

	/**
	 * Adds the given path to the front of the asset paths array.  It adds paths
	 * in a way so that asset paths are used First in Last Out.
	 *
	 * @param   string  the path to add
	 * @return  void
	 */
	public static function add_path($path)
	{
		array_unshift(static::$_asset_paths, str_replace('../', '', $path));
	}

	/**
	 * Removes the given path from the asset paths array
	 *
	 * @param   string  the path to remove
	 * @return  void
	 */
	public static function remove_path($path)
	{
		if (($key = array_search(str_replace('../', '', $path), static::$_asset_paths)) !== false)
		{
			unset(static::$_asset_paths[$key]);
		}
	}

	/**
	 * Renders the given group.  Each tag will be separated by a line break.
	 * You can optionally tell it to render the files raw.  This means that
	 * all CSS and JS files in the group will be read and the contents included
	 * in the returning value.
	 *
	 * @param   mixed   the group to render
	 * @param   bool    whether to return the raw file or not
	 * @return  string  the group's output
	 */
	public static function render($group, $raw = false)
	{
		if (is_string($group))
		{
			$group = isset(static::$_groups[$group]) ? static::$_groups[$group] : array();
		}

		$css = '';
		$js = '';
		$img = '';
		foreach ($group as $key => $item)
		{
			$type = $item['type'];
			$filename = $item['file'];
			$attr = $item['attr'];

			if ( ! preg_match('|^(\w+:)?//|', $filename))
			{
				if ( ! ($file = static::find_file($filename, static::$_folders[$type])))
				{
					throw new \Fuel_Exception('Could not find asset: '.$filename);
				}

				$raw or $file = static::$_asset_url.$file.(static::$_add_mtime ? '?'.filemtime($file) : '');
			}
			else
			{
				$file = $filename;
			}

			switch($type)
			{
				case 'css':
					if ($raw)
					{
						return '<style type="text/css">'.PHP_EOL.file_get_contents($file).PHP_EOL.'</style>';
					}
					$attr['rel'] = 'stylesheet';
					$attr['type'] = 'text/css';
					$attr['href'] = $file;

					$css .= html_tag('link', $attr).PHP_EOL;
				break;
				case 'js':
					if ($raw)
					{
						return html_tag('script', array('type' => 'text/javascript'), PHP_EOL.file_get_contents($file).PHP_EOL).PHP_EOL;
					}
					$attr['type'] = 'text/javascript';
					$attr['src'] = $file;

					$js .= html_tag('script', $attr, '').PHP_EOL;
				break;
				case 'img':
					$attr['src'] = $file;
					$attr['alt'] = isset($attr['alt']) ? $attr['alt'] : '';

					$img .= html_tag('img', $attr );
				break;
			}

		}

		// return them in the correct order
		return $css.$js.$img;
	}

	// --------------------------------------------------------------------

	/**
	 * CSS
	 *
	 * Either adds the stylesheet to the group, or returns the CSS tag.
	 *
	 * @access	public
	 * @param	mixed	The file name, or an array files.
	 * @param	array	An array of extra attributes
	 * @param	string	The asset group name
	 * @return	string
	 */
	public static function css($stylesheets = array(), $attr = array(), $group = NULL, $raw = false)
	{
		static $temp_group = 1000000;

		$render = false;
		if ($group === NULL)
		{
			$group = (string) (++$temp_group);
			$render = true;
		}

		static::_parse_assets('css', $stylesheets, $attr, $group);

		if ($render)
		{
			return static::render($group, $raw);
		}

		return '';
	}

	// --------------------------------------------------------------------

	/**
	 * JS
	 *
	 * Either adds the javascript to the group, or returns the script tag.
	 *
	 * @access	public
	 * @param	mixed	The file name, or an array files.
	 * @param	array	An array of extra attributes
	 * @param	string	The asset group name
	 * @return	string
	 */
	public static function js($scripts = array(), $attr = array(), $group = NULL, $raw = false)
	{
		static $temp_group = 2000000;

		$render = false;
		if ( ! isset($group))
		{
			$group = (string) $temp_group++;
			$render = true;
		}

		static::_parse_assets('js', $scripts, $attr, $group);

		if ($render)
		{
			return static::render($group, $raw);
		}

		return '';
	}

	// --------------------------------------------------------------------

	/**
	 * Img
	 *
	 * Either adds the image to the group, or returns the image tag.
	 *
	 * @access	public
	 * @param	mixed	The file name, or an array files.
	 * @param	array	An array of extra attributes
	 * @param	string	The asset group name
	 * @return	string
	 */
	public static function img($images = array(), $attr = array(), $group = NULL)
	{
		static $temp_group = 3000000;

		$render = false;
		if ( ! isset($group))
		{
			$group = (string) $temp_group++;
			$render = true;
		}

		static::_parse_assets('img', $images, $attr, $group);

		if ($render)
		{
			return static::render($group);
		}

		return '';
	}

	// --------------------------------------------------------------------

	/**
	 * Parse Assets
	 *
	 * Pareses the assets and adds them to the group
	 *
	 * @access	private
	 * @param	string	The asset type
	 * @param	mixed	The file name, or an array files.
	 * @param	array	An array of extra attributes
	 * @param	string	The asset group name
	 * @return	string
	 */
	protected static function _parse_assets($type, $assets, $attr, $group)
	{
		if ( ! is_array($assets))
		{
			$assets = array($assets);
		}

		foreach ($assets as $key => $asset)
		{
			static::$_groups[$group][] = array(
				'type'	=>	$type,
				'file'	=>	$asset,
				'attr'	=>	(array) $attr
			);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Find File
	 *
	 * Locates a file in all the asset paths.
	 *
	 * @access	public
	 * @param	string	The filename to locate
	 * @param	string	The sub-folder to look in (optional)
	 * @return	mixed	Either the path to the file or false if not found
	 */
	public static function find_file($file, $folder = '')
	{
		foreach (static::$_asset_paths as $path)
		{
			empty($folder) or $folder = trim($folder, '/').'/';

			if (is_file($path.$folder.ltrim($file, '/')))
			{
				return $path.$folder.ltrim($file, '/');
			}
		}

		return false;
	}
}


