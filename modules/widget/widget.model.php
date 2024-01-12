<?php
/* Copyright (C) NAVER <http://www.navercorp.com> */
/**
 * @class  widgetModel
 * @author NAVER (developers@xpressengine.com)
 * @version 0.1
 * @brief Model class for widget modules
 */

use Rhymix\Framework\Cache;

class WidgetModel extends Widget
{
	/**
	 * @brief Initialization
	 */
	function init()
	{
	}

	/**
	 * @brief Wanted widget's path
	 */
	function getWidgetPath($widget_name)
	{
		$path = sprintf('./widgets/%s/', $widget_name);
		if(is_dir($path)) return $path;

		return "";
	}

	/**
	 * @brief Wanted widget style path
	 */
	function getWidgetStylePath($widgetStyle_name)
	{
		$path = sprintf('./widgetstyles/%s/', $widgetStyle_name);
		if(is_dir($path)) return $path;

		return "";
	}

	/**
	 * @brief Wanted widget style path
	 */
	function getWidgetStyleTpl($widgetStyle_name)
	{
		$path = $this->getWidgetStylePath($widgetStyle_name);
		$tpl = sprintf('%swidgetstyle.html', $path);
		return $tpl;
	}

	/**
	 * @brief Wanted photos of the type and information
	 * Download a widget with type (generation and other means)
	 */
	function getDownloadedWidgetList()
	{
		$oAutoinstallModel = getModel('autoinstall');

		// 've Downloaded the widget and the widget's list of installed Wanted
		$searched_list = FileHandler::readDir('./widgets');
		$searched_count = count($searched_list);
		if(!$searched_count) return;
		sort($searched_list);
		// D which pertain to the list of widgets loop spins return statement review the information you need
		for($i=0;$i<$searched_count;$i++)
		{
			// The name of the widget
			$widget = $searched_list[$i];
			// Wanted information on the Widget
			$widget_info = $this->getWidgetInfo($widget);

			if(!$widget_info)
			{
				$widget_info = new stdClass();
			}

			// get easyinstall remove url
			$packageSrl = $oAutoinstallModel->getPackageSrlByPath($widget_info->path);
			$widget_info->remove_url = $oAutoinstallModel->getRemoveUrlByPackageSrl($packageSrl);

			// get easyinstall need update
			$package = $oAutoinstallModel->getInstalledPackages($packageSrl);
			$widget_info->need_update = $package[$packageSrl]->need_update;

			// get easyinstall update url
			if ($widget_info->need_update == 'Y')
			{
				$widget_info->update_url = $oAutoinstallModel->getUpdateUrlByPackageSrl($packageSrl);
			}

			$list[] = $widget_info;
		}
		return $list;
	}

	/**
	 * @brief Wanted photos of the type and information
	 * Download a widget with type (generation and other means)
	 */
	function getDownloadedWidgetStyleList()
	{
		// 've Downloaded the widget and the widget's list of installed Wanted
		$searched_list = FileHandler::readDir('./widgetstyles');
		$searched_count = count($searched_list);
		if(!$searched_count) return;
		sort($searched_list);
		// D which pertain to the list of widgets loop spins return statement review the information you need
		for($i=0;$i<$searched_count;$i++)
		{
			// The name of the widget
			$widgetStyle = $searched_list[$i];
			// Wanted information on the Widget
			$widgetStyle_info = $this->getWidgetStyleInfo($widgetStyle);

			$list[] = $widgetStyle_info;
		}
		return $list;
	}

	/**
	 * @brief Modules conf/info.xml wanted to read the information
	 * It uses caching to reduce time for xml parsing ..
	 */
	function getWidgetInfo($widget)
	{
		// Get a path of the requested module. Return if not exists.
		$widget_path = $this->getWidgetPath($widget);
		if(!$widget_path) return;

		$widget_info = self::getWidgetXmlInfo($widget);
		$widget_info->path = $widget_path;
		$widget_info->extra_var_count = count((array) $widget_info->extra_vars) ?? 0;

		return $widget_info;
	}

	/**
	 * @brief Read xml information of the component
	 */
	public static function getWidgetXmlInfo(string $widget): ?\stdClass
	{
		// Get xml file path of the requested components
		$widget = preg_replace('/[^a-zA-Z0-9-_]/', '', $widget);
		$widget_path = sprintf('%s/widgets/%s/conf/', \RX_BASEDIR, $widget);

		$xml_file = sprintf('%sinfo.xml', $widget_path);
		$xml_mtime = max(
			filemtime($xml_file),
			filemtime(\RX_BASEDIR . 'common/framework/parsers/BaseParser.php'),
			filemtime(\RX_BASEDIR . 'common/framework/parsers/WidgetParser.php')
		);
		$lang_type = Context::getLangType();

		// Get from cache
		$cache_key = sprintf('widget:%s:%s:%d', $widget, $lang_type, $xml_mtime);
		$info = Cache::get($cache_key);
		if (!empty($info))
		{
			return $info;
		}

		// Parse XML file
		$info = Rhymix\Framework\Parsers\WidgetParser::loadXML($xml_file, $widget, $lang_type);

		// Sor to cache and return
		Cache::set($cache_key, $info, 0, true);

		return $info;
	}

	/**
	 * @brief Modules conf/info.xml wanted to read the information
	 * It uses caching to reduce time for xml parsing ..
	 */
	function getWidgetStyleInfo($widgetStyle)
	{
		$widgetStyle = preg_replace('/[^a-zA-Z0-9-_]/', '', $widgetStyle);
		$widgetStyle_path = $this->getWidgetStylePath($widgetStyle);
		if(!$widgetStyle_path) return;
		$xml_file = sprintf("%sskin.xml", $widgetStyle_path);
		if(!file_exists($xml_file)) return;
		// If the problem by comparing the cache file and include the return variable $widgetStyle_info
		$cache_file = sprintf(RX_BASEDIR . 'files/cache/widgetstyles/%s.%s.cache.php', $widgetStyle, Context::getLangType());

		if(file_exists($cache_file)&&filemtime($cache_file)>filemtime($xml_file))
		{
			@include($cache_file);
			return $widgetStyle_info;
		}
		// If no cache file exists, parse the xml and then return the variable.
		$oXmlParser = new XeXmlParser();
		$tmp_xml_obj = $oXmlParser->loadXmlFile($xml_file);
		$xml_obj = $tmp_xml_obj->widgetstyle;
		if(!$xml_obj) return;

		$buff = array();
		$buff[] = '<?php if(!defined("__XE__")) exit();';
		$buff[] = '$widgetStyle_info = new stdClass();';

		// Title of the widget, version
		$buff[] = sprintf('$widgetStyle_info->widgetStyle = %s;', var_export($widgetStyle, true));
		$buff[] = sprintf('$widgetStyle_info->path = %s;', var_export($widgetStyle_path, true));
		$buff[] = sprintf('$widgetStyle_info->title = %s;', var_export($xml_obj->title->body, true));
		$buff[] = sprintf('$widgetStyle_info->description = %s;', var_export($xml_obj->description->body, true));
		$buff[] = sprintf('$widgetStyle_info->version = %s;', var_export($xml_obj->version->body, true));
		if($xml_obj->date->body === 'RX_CORE')
		{
			$date = '';
		}
		else
		{
			$date_obj = new stdClass;
			sscanf($xml_obj->date->body, '%d-%d-%d', $date_obj->y, $date_obj->m, $date_obj->d);
			$date = sprintf('%04d%02d%02d', $date_obj->y, $date_obj->m, $date_obj->d);
		}
		$buff[] = sprintf('$widgetStyle_info->date = %s;', var_export($date, true));
		$buff[] = sprintf('$widgetStyle_info->homepage = %s;', var_export($xml_obj->link->body, true));
		$buff[] = sprintf('$widgetStyle_info->license = %s;', var_export($xml_obj->license->body, true));
		$buff[] = sprintf('$widgetStyle_info->license_link = %s;', var_export($xml_obj->license->attrs->link, true));

		// preview
		if(!isset($xml_obj->preview)) $xml_obj->preview = new stdClass;
		if(!isset($xml_obj->preview->body) || !$xml_obj->preview->body) $xml_obj->preview->body = 'preview.jpg';
		$preview_file = sprintf("%s%s", $widgetStyle_path,$xml_obj->preview->body);
		if(file_exists($preview_file)) $buff[] = sprintf('$widgetStyle_info->preview = %s;', var_export($preview_file, true));

		// Author information
		if(!is_array($xml_obj->author)) $author_list[] = $xml_obj->author;
		else $author_list = $xml_obj->author;

		foreach($author_list as $idx => $author)
		{
			$buff[] = sprintf('$widgetStyle_info->author[%d] = new stdClass();', $idx);
			$buff[] = sprintf('$widgetStyle_info->author[%d]->name = %s;', $idx, var_export($author->name->body, true));
			$buff[] = sprintf('$widgetStyle_info->author[%d]->email_address = %s;', $idx, var_export($author->attrs->email_address, true));
			$buff[] = sprintf('$widgetStyle_info->author[%d]->homepage = %s;', $idx, var_export($author->attrs->link, true));
		}

		// Extra vars (user defined variables to use in a template)
		$extra_var_groups = $xml_obj->extra_vars->group;
		if(!$extra_var_groups) $extra_var_groups = $xml_obj->extra_vars;
		if(!is_array($extra_var_groups)) $extra_var_groups = array($extra_var_groups);

		$extra_var_count = 0;
		$buff[] = '$widgetStyle_info->extra_var = $widgetStyle_info->extra_var ?? new stdClass();';
		foreach($extra_var_groups as $group)
		{
			$extra_vars = (!is_array($group->var)) ? array($group->var) : $group->var;

			if($extra_vars[0]->attrs->id || $extra_vars[0]->attrs->name)
			{
				foreach($extra_vars as $var)
				{
					$extra_var_count++;
					$id = ($var->attrs->id) ? $var->attrs->id : $var->attrs->name;
					$name = ($var->name->body) ? $var->name->body : $var->title->body;
					$type = ($var->attrs->type) ? $var->attrs->type : $var->type->body;

					$buff[] = sprintf('$widgetStyle_info->extra_var->%s = new stdClass();', $id);
					$buff[] = sprintf('$widgetStyle_info->extra_var->%s->group = %s;', $id, var_export($group->title->body, true));
					$buff[] = sprintf('$widgetStyle_info->extra_var->%s->name = %s;', $id, var_export($name, true));
					$buff[] = sprintf('$widgetStyle_info->extra_var->%s->type = %s;', $id, var_export($type, true));
					if($type =='filebox')
					{
						$buff[] = sprintf('$widgetStyle_info->extra_var->%s->filter = %s;', $id, var_export($var->attrs->filter, true));
						$buff[] = sprintf('$widgetStyle_info->extra_var->%s->allow_multiple = %s;', $id, var_export($var->attrs->allow_multiple, true));
					}
					$buff[] = sprintf('$widgetStyle_info->extra_var->%s->value = $vars->%s;', $id, $id);
					$buff[] = sprintf('$widgetStyle_info->extra_var->%s->description = %s;', $id, var_export($var->description->body, true));

					if($var->options)
					{
						$var_options = (!is_array($var->options)) ? array($var->options) : $var->options;
						foreach($var_options as $option_item)
						{
							$buff[] = sprintf('$widgetStyle_info->extra_var->%s->options[%s] = %s;', $id, var_export($option_item->value->body, true), var_export($option_item->name->body, true));
						}
					}
				}
			}
		}
		$buff[] = sprintf('$widgetStyle_info->extra_var_count = %d;', $extra_var_count);

		FileHandler::writeFile($cache_file, implode(PHP_EOL, $buff));

		if(file_exists($cache_file)) @include($cache_file);

		return $widgetStyle_info;
	}
}
/* End of file widget.model.php */
/* Location: ./modules/widget/widget.model.php */
