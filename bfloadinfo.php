<?php
/**
* @package      System plugin to load information into page
* @version      1.0.0
* @author       http://www.brainforge.co.uk
* @copyright    Copyright (C) 2022 Jonathan Brain. All rights reserved.
* @license      GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
*/

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;

class plgSystemBfloadinfo extends CMSPlugin
	{
	protected static $_data = null;

	/*
	 */
	public function __construct(&$subject, $config = array())
	{
		parent::__construct($subject, $config);
		self::$_data = (array)$this->params->get('data');
	}

	/*
	 */
	public static function prepare(&$text)
	{
		$found = false;
		$matches = [];
		preg_match_all('/{(bfloadinfo)\s*(.*?)}/i', $text, $matches, PREG_SET_ORDER);

		foreach ($matches as $match)
		{
			$params = explode('|', $match[2]);
			$label = $params[0];
			if (empty($label)) continue;

			foreach(self::$_data as $data)
			{
				if ($data->label != $label) continue;

				$args = array_slice($params, 1);

				switch($data->type)
				{
					case 'TEXT':
						$output = $data->value;
						break;
					case 'TRANS':
						$output = Text::_($data->value);
						break;
					case 'LINK':
						$output = self::makeLink($data->value, 'http', '', $args);
						break;
					case 'LINK-EMAIL':
						$output = self::makeLink($data->value, 'email', 'icon-mail', $args);
						break;
					case 'LINK-PHONE':
						$output = self::makeLink($data->value, 'tel', 'icon-phone', $args);
						break;
					case 'MODULE-POSN':
						$modules = ModuleHelper::getModules($data->value);
						if (empty($modules)) continue 2;
						$output = self::loadModules($modules);
						break;
					case 'MODULE-ID':
						$module = ModuleHelper::getModuleById($data->value);
						if (empty($module->id)) continue 2;
						$output = self::loadModules(array($module));
						break;
					default:
						continue 2;
				}

				while(self::prepare($output));

				$text  = str_replace($match[0], $output, $text);
				$found = true;

				break;
			}
		}

		return $found;
	}

	/*
	 */
	protected static function makeLink($value, $type='http', $icon=null, $args=[])
	{
		if (empty($value)) return '';

		if (!empty($args[0]))
		{
			$icon = $args[0];
		}

		$value = Text::_($value);
		while (self::prepare($value));

		switch($type)
		{
			case 'http':
				if (strpos($value, 'http://') !== 0 && strpos($value, 'https://') !== 0)
				{
					$value = $type . '://' . $value;
				}
				$output = '<a href="' . $value . '" ' . htmlspecialchars_decode(@$args[1]) . '>';

				if (!empty($icon))
				{
					$value = '<span class="' . $icon . '" aria-hidden="true"></span> ' . $value;
					$icon = null;
				}

				break;
			default:
				$output = '<a href="' . $type . ':' . $value . '">';
				break;
		}

		if (!empty($icon))
		{
			$output .= '<span class="' . $icon . '"></span> ';
		}

		return $output . $value . '</a>';
	}

	/*
	 */
	protected static function loadModules($modules, $style='none')
	{
		$params   = array('style' => $style);
		$renderer = Factory::getApplication()->getDocument()->loadRenderer('module');

		ob_start();

		foreach ($modules as $module) {
			if (empty($module->id)) continue;
			echo $renderer->render($module, $params);
		}

		return ob_get_clean();
	}

	/*
	 */
	public function onContentPrepare($context, &$article, &$params, $limitstart){
		self::prepare($article->text);
	}

	/*
	 */
	public function onAfterRender()
	{
		$app = Factory::getApplication();

		if ($app->isClient('administrator'))
		{
			return;
		}

		$body = $app->getBody();

		if (self::prepare($body))
		{
			$app->setBody($body);
		}
	}
}
?>