<?php
/* Copyright (C) NAVER <http://www.navercorp.com> */
/**
 * @class  moduleController
 * @author NAVER (developers@xpressengine.com)
 * @brief controller class of the module module
 */
class ModuleController extends Module
{
	/**
	 * @brief Initialization
	 */
	function init()
	{
	}

	/**
	 * @brief Add action forward
	 * Action forward finds and forwards if an action is not in the requested module
	 * This is used when installing a module
	 */
	function insertActionForward($module, $type, $act, $route_regexp = null, $route_config = null, $global_route = 'N')
	{
		$args = new stdClass();
		$args->module = $module;
		$args->type = $type;
		$args->act = $act;
		$args->route_regexp = is_scalar($route_regexp) ? $route_regexp : serialize($route_regexp);
		$args->route_config = is_scalar($route_config) ? $route_config : serialize($route_config);
		$args->global_route = $global_route === 'Y' ? 'Y' : 'N';

		$oDB = DB::getInstance();
		$oDB->begin();
		$output = executeQuery('module.deleteActionForward', ['act' => $act]);
		$output = executeQuery('module.insertActionForward', $args);
		$oDB->commit();

		Rhymix\Framework\Cache::delete('action_forward');
		return $output;
	}

	/**
	 * @brief Delete action forward
	 */
	function deleteActionForward($module, $type, $act)
	{
		$args = new stdClass();
		$args->module = $module;
		$args->type = $type;
		$args->act = $act;
		$output = executeQuery('module.deleteActionForward', $args);

		Rhymix\Framework\Cache::delete('action_forward');
		return $output;
	}

	/**
	 * @brief Add trigger callback function
	 *
	 * @param string $trigger_name
	 * @param string $called_position
	 * @param callable $callback_function
	 */
	function addTriggerFunction($trigger_name, $called_position, $callback_function)
	{
		$GLOBALS['__trigger_functions__'][$trigger_name][$called_position][] = $callback_function;
		return true;
	}

	/**
	 * @brief Add module trigger
	 * module trigger is to call a trigger to a target module
	 *
	 */
	function insertTrigger($trigger_name, $module, $type, $called_method, $called_position)
	{
		$args = new stdClass();
		$args->trigger_name = $trigger_name;
		$args->module = $module;
		$args->type = $type;
		$args->called_method = $called_method;
		$args->called_position = $called_position;

		$output = executeQuery('module.deleteTrigger', $args);
		$output = executeQuery('module.insertTrigger', $args);
		if($output->toBool())
		{
			//remove from cache
			$GLOBALS['__triggers__'] = NULL;
			Rhymix\Framework\Cache::delete('triggers');
		}

		return $output;
	}

	/**
	 * @brief Delete module trigger
	 *
	 */
	function deleteTrigger($trigger_name, $module, $type, $called_method, $called_position)
	{
		$args = new stdClass();
		$args->trigger_name = $trigger_name;
		$args->module = $module;
		$args->type = $type;
		$args->called_method = $called_method;
		$args->called_position = $called_position;

		$output = executeQuery('module.deleteTrigger', $args);
		if($output->toBool())
		{
			//remove from cache
			$GLOBALS['__triggers__'] = NULL;
			Rhymix\Framework\Cache::delete('triggers');
		}

		return $output;
	}

	/**
	 * @brief Delete module trigger
	 *
	 */
	function deleteModuleTriggers($module)
	{
		$args = new stdClass();
		$args->module = $module;

		$output = executeQuery('module.deleteModuleTriggers', $args);
		if($output->toBool())
		{
			//remove from cache
			$GLOBALS['__triggers__'] = NULL;
			Rhymix\Framework\Cache::delete('triggers');
		}

		return $output;
	}

	/**
	 * @brief Add module extend
	 *
	 */
	function insertModuleExtend($parent_module, $extend_module, $type, $kind = '')
	{
		if(!in_array($type, array('model', 'controller', 'view', 'api', 'mobile')))
		{
			return false;
		}

		if(in_array($parent_module, array('module', 'addon', 'widget', 'layout')))
		{
			return false;
		}

		$args = new stdClass;
		$args->parent_module = $parent_module;
		$args->extend_module = $extend_module;
		$args->type = $type;
		$args->kind = $kind == 'admin' ? 'admin' : '';

		$output = executeQuery('module.insertModuleExtend', $args);
		if($output->toBool())
		{
			//remove from cache
			unset($GLOBALS['__MODULE_EXTEND__']);
			FileHandler::removeFile('files/cache/common/module_extend.php');
		}

		return $output;
	}

	/**
	 * @brief Delete module extend
	 *
	 */
	function deleteModuleExtend($parent_module, $extend_module, $type, $kind='')
	{
		$cache_file = './files/cache/common/module_extend.php';
		FileHandler::removeFile($cache_file);

		$args = new stdClass;
		$args->parent_module = $parent_module;
		$args->extend_module = $extend_module;
		$args->type = $type;
		$args->kind = $kind;

		$output = executeQuery('module.deleteModuleExtend', $args);

		return $output;
	}

	public function updateModuleConfig($module, $config)
	{
		$origin_config = ModuleModel::getModuleConfig($module) ?: new stdClass;
		foreach($config as $key => $val)
		{
			$origin_config->{$key} = $val;
		}

		return $this->insertModuleConfig($module, $origin_config);
	}

	public function updateModuleSectionConfig($module, $section, $config)
	{
		$origin_config = ModuleModel::getModuleSectionConfig($module, $section) ?: new stdClass;
		foreach($config as $key => $val)
		{
			$origin_config->{$key} = $val;
		}

		return $this->insertModuleSectionConfig($module, $section, $origin_config);
	}

	public function updateModulePartConfig($module, $module_srl, $config)
	{
		$origin_config = ModuleModel::getModulePartConfig($module, $module_srl) ?: new stdClass;
		foreach($config as $key => $val)
		{
			$origin_config->{$key} = $val;
		}

		return $this->insertModulePartConfig($module, $module_srl, $origin_config);
	}

	/**
	 * Save global config for a module.
	 *
	 * @param string $module
	 * @param object $config
	 * @return BaseObject
	 */
	public function insertModuleConfig($module, $config)
	{
		$args =new stdClass();
		$args->module = $module;
		$args->config = serialize($config);

		$oDB = DB::getInstance();
		$oDB->begin();

		$output = executeQuery('module.deleteModuleConfig', $args);
		if(!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		$output = executeQuery('module.insertModuleConfig', $args);
		if(!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		$oDB->commit();

		//remove from cache
		unset($GLOBALS['__ModuleConfig__'][$module]);
		Rhymix\Framework\Cache::clearGroup('site_and_module');
		return $output;
	}

	/**
	 * Save an independent section of module config.
	 *
	 * @param string $module
	 * @param string $section
	 * @param object $config
	 * @return BaseObject
	 */
	public function insertModuleSectionConfig($module, $section, $config)
	{
		return $this->insertModuleConfig("$module:$section", $config);
	}

	/**
	 * Save module config for a specific module_srl.
	 *
	 * @param string $module
	 * @param int $module_srl
	 * @param object $config
	 * @return BaseObject
	 */
	public function insertModulePartConfig($module, $module_srl, $config)
	{
		$args = new stdClass();
		$args->module = $module;
		$args->module_srl = $module_srl;
		$args->config = serialize($config);

		$oDB = DB::getInstance();
		$oDB->begin();

		$output = executeQuery('module.deleteModulePartConfig', $args);
		if(!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		$output = executeQuery('module.insertModulePartConfig', $args);
		if(!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		$oDB->commit();

		//remove from cache
		unset($GLOBALS['__ModulePartConfig__'][$module][$module_srl]);
		Rhymix\Framework\Cache::clearGroup('site_and_module');
		return $output;
	}

	/**
	 * @brief create virtual site
	 */
	function insertSite($domain, $index_module_srl)
	{
		throw new Rhymix\Framework\Exceptions\FeatureDisabled;
	}

	/**
	 * @brief modify virtual site
	 */
	function updateSite($args)
	{
		throw new Rhymix\Framework\Exceptions\FeatureDisabled;
	}

	/**
	 * @brief Arrange module information
	 */
	function arrangeModuleInfo(&$args, &$extra_vars)
	{
		// Remove unnecessary information
		unset($args->body);
		unset($args->act);
		unset($args->page);
		unset($args->site_srl);
		// Test mid value
		if(!preg_match("/^[a-z][a-z0-9_]+$/i", $args->mid)) return new BaseObject(-1, 'msg_limit_mid');
		// Test variables (separate basic vars and other vars in modules)
		$extra_vars = clone($args);
		unset($extra_vars->module_srl);
		unset($extra_vars->module);
		unset($extra_vars->module_category_srl);
		unset($extra_vars->domain_srl);
		unset($extra_vars->layout_srl);
		unset($extra_vars->mlayout_srl);
		unset($extra_vars->use_mobile);
		unset($extra_vars->menu_srl);
		unset($extra_vars->site_srl);
		unset($extra_vars->mid);
		unset($extra_vars->is_skin_fix);
		unset($extra_vars->skin);
		unset($extra_vars->is_mskin_fix);
		unset($extra_vars->mskin);
		unset($extra_vars->browser_title);
		unset($extra_vars->description);
		unset($extra_vars->is_default);
		unset($extra_vars->content);
		unset($extra_vars->mcontent);
		unset($extra_vars->open_rss);
		unset($extra_vars->header_text);
		unset($extra_vars->footer_text);
		$args = delObjectVars($args, $extra_vars);

		return new BaseObject();
	}

	/**
	 * @brief Insert module
	 */
	function insertModule($args)
	{
		$isMenuCreate = $args->isMenuCreate ?? true;

		$output = $this->arrangeModuleInfo($args, $extra_vars);
		if(!$output->toBool())
		{
			return $output;
		}

		// Check whether the module name already exists
		if(ModuleModel::isIDExists($args->mid, $args->module))
		{
			return new BaseObject(-1, 'msg_module_name_exists');
		}

		// Fill default values
		if (empty($args->module_srl))
		{
			$args->module_srl = getNextSequence();
		}
		$args->browser_title = escape($args->browser_title ?? '', false, true);
		$args->description = isset($args->description) ? escape($args->description, false) : null;
		if(!isset($args->skin) || $args->skin == '/USE_DEFAULT/')
		{
			$args->is_skin_fix = 'N';
		}
		else
		{
			if(isset($args->is_skin_fix))
			{
				$args->is_skin_fix = ($args->is_skin_fix != 'Y') ? 'N' : 'Y';
			}
			else
			{
				$args->is_skin_fix = 'Y';
			}
		}
		if(!isset($args->mskin) || $args->mskin == '/USE_DEFAULT/' || $args->mskin == '/USE_RESPONSIVE/')
		{
			$args->is_mskin_fix = 'N';
		}
		else
		{
			if(isset($args->is_mskin_fix))
			{
				$args->is_mskin_fix = ($args->is_mskin_fix != 'Y') ? 'N' : 'Y';
			}
			else
			{
				$args->is_mskin_fix = 'Y';
			}
		}

		// begin transaction
		$oDB = DB::getInstance();
		$oDB->begin();

		if($isMenuCreate)
		{
			$menuArgs = new stdClass;
			$menuArgs->menu_srl = $args->menu_srl;
			$menuOutput = executeQuery('menu.getMenu', $menuArgs);

			// if menu is not created, create menu also. and does not supported that in virtual site.
			if(!$menuOutput->data)
			{
				$oMenuAdminController = getAdminController('menu');
				$menuSrl = $oMenuAdminController->getUnlinkedMenu();

				$menuArgs->menu_srl = $menuSrl;
				$menuArgs->menu_item_srl = getNextSequence();
				$menuArgs->parent_srl = 0;
				$menuArgs->open_window = 'N';
				$menuArgs->url = $args->mid;
				$menuArgs->expand = 'N';
				$menuArgs->is_shortcut = 'N';
				$menuArgs->name = $args->browser_title;
				$menuArgs->listorder = $args->menu_item_srl * -1;

				$menuItemOutput = executeQuery('menu.insertMenuItem', $menuArgs);
				if(!$menuItemOutput->toBool())
				{
					$oDB->rollback();
					return $menuItemOutput;
				}

				$oMenuAdminController->makeXmlFile($menuSrl);
			}
		}

		// Insert a module
		$args->menu_srl = $menuArgs->menu_srl;
		$output = executeQuery('module.insertModule', $args);
		if(!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}
		// Insert module extra vars
		$this->insertModuleExtraVars($args->module_srl, $extra_vars);

		// commit
		$oDB->commit();

		Rhymix\Framework\Cache::clearGroup('site_and_module');
		ModuleModel::$_mid_map = ModuleModel::$_module_srl_map = [];
		$output->add('module_srl',$args->module_srl);
		return $output;
	}

	/**
	 * @brief Modify module information
	 */
	function updateModule($args)
	{
		$isMenuCreate = $args->isMenuCreate ?? true;

		$output = $this->arrangeModuleInfo($args, $extra_vars);
		if(!$output->toBool())
		{
			return $output;
		}

		// Check whether the module name already exists
		$module_info = ModuleModel::getModuleInfoByModuleSrl($args->module_srl);
		if($args->mid !== $module_info->mid && ModuleModel::isIDExists($args->mid))
		{
			if ($args->module !== $args->mid)
			{
				return new BaseObject(-1, 'msg_module_name_exists');
			}
		}

		$args->browser_title = escape($args->browser_title ?? $module_info->browser_title, false, true);
		$args->description = isset($args->description) ? escape($args->description, false) : null;

		// default value
		if(!isset($args->skin) || $args->skin == '/USE_DEFAULT/')
		{
			$args->is_skin_fix = 'N';
		}
		else
		{
			if(isset($args->is_skin_fix))
			{
				$args->is_skin_fix = ($args->is_skin_fix != 'Y') ? 'N' : 'Y';
			}
			else
			{
				$args->is_skin_fix = 'Y';
			}
		}

		if(!isset($args->mskin) || $args->mskin == '/USE_DEFAULT/' || $args->mskin == '/USE_RESPONSIVE/')
		{
			$args->is_mskin_fix = 'N';
		}
		else
		{
			if(isset($args->is_mskin_fix))
			{
				$args->is_mskin_fix = ($args->is_mskin_fix != 'Y') ? 'N' : 'Y';
			}
			else
			{
				$args->is_mskin_fix = 'Y';
			}
		}

		// begin transaction
		$oDB = DB::getInstance();
		$oDB->begin();

		if($isMenuCreate)
		{
			$menuArgs = new stdClass;
			$menuArgs->url = $module_info->mid;
			$menuOutput = executeQueryArray('menu.getMenuItemByUrl', $menuArgs);
			if($menuOutput->data && count($menuOutput->data))
			{
				$oMenuAdminController = getAdminController('menu');
				foreach($menuOutput->data as $itemInfo)
				{
					$itemInfo->url = $args->mid;

					$updateMenuItemOutput = $oMenuAdminController->updateMenuItem($itemInfo);
					if(!$updateMenuItemOutput->toBool())
					{
						$oDB->rollback();
						return $updateMenuItemOutput;
					}
				}
			}
		}

		$output = executeQuery('module.updateModule', $args);
		if(!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		// if mid changed, change mid of success_return_url to new mid
		if($module_info->mid != $args->mid && Context::get('success_return_url'))
		{
			changeValueInUrl('mid', $args->mid, $module_info->mid);
		}

		// Insert module extra vars
		$this->insertModuleExtraVars($args->module_srl, $extra_vars);

		$oDB->commit();

		$output->add('module_srl',$args->module_srl);

		//remove from cache
		Rhymix\Framework\Cache::clearGroup('site_and_module');
		ModuleModel::$_mid_map = ModuleModel::$_module_srl_map = [];
		return $output;
	}

	/**
	 * @brief 업데이트 기록 저장
	 * @param string $update_id
	 * @return Boolean
	 */
	public function insertUpdatedLog($update_id)
	{
		$args = new stdClass();
		$args->update_id = $update_id;
		$output = executeQuery('module.insertModuleUpdateLog', $args);

		if(!!$output->error) return false;

		return true;
	}

	/**
	 * Change the module's virtual site
	 *
	 * @deprecated
	 */
	function updateModuleSite($module_srl, $site_srl = 0, $layout_srl = 0)
	{

	}

	/**
	 * Delete module
	 * Attempt to delete all related information when deleting a module.
	 * Origin method is changed. because menu validation check is needed
	 */
	function deleteModule($module_srl)
	{
		if(!$module_srl) return new BaseObject(-1,'msg_invalid_request');

		$site_module_info = Context::get('site_module_info');

		$output = ModuleModel::getModuleInfoByModuleSrl($module_srl);

		$args = new stdClass();
		$args->url = $output->mid;
		$args->is_shortcut = 'N';

		$oMenuAdminModel = getAdminModel('menu');
		$menuOutput = $oMenuAdminModel->getMenuList($args);
		if(is_array($menuOutput->data))
		{
			foreach($menuOutput->data AS $key=>$value)
			{
				$args->menu_srl = $value->menu_srl;
				break;
			}
		}

		$output = executeQuery('menu.getMenuItemByUrl', $args);
		// menu delete
		if($output->data)
		{
			unset($args);
			$args = new stdClass;
			$args->menu_srl = $output->data->menu_srl;
			$args->menu_item_srl = $output->data->menu_item_srl;
			$args->is_force = 'N';

			$oMenuAdminController = getAdminController('menu');
			$output = $oMenuAdminController->deleteItem($args, true);

			if($output->toBool())
			{
				return new BaseObject(0, 'success_deleted');
			}
			else
			{
				return new BaseObject($output->error, $output->message);
			}
		}
		// only delete module
		else
		{
			return $this->onlyDeleteModule($module_srl);
		}
	}

	/**
	 * Delete module
	 * Attempt to delete all related information when deleting a module.
	 */
	public function onlyDeleteModule($module_srl)
	{
		if(!$module_srl) return new BaseObject(-1, 'msg_invalid_request');

		// check start module
		$columnList = array('sites.index_module_srl');
		$start_module = ModuleModel::getSiteInfo(0, $columnList);
		if($module_srl == $start_module->index_module_srl) return new BaseObject(-1, 'msg_cannot_delete_startmodule');

		// Call a trigger (before)
		$trigger_obj = new stdClass();
		$trigger_obj->module_srl = $module_srl;
		$output = ModuleHandler::triggerCall('module.deleteModule', 'before', $trigger_obj);
		if(!$output->toBool()) return $output;

		// begin transaction
		$oDB = DB::getInstance();
		$oDB->begin();

		$args = new stdClass();
		$args->module_srl = $module_srl;
		// Delete module information from the DB
		$output = executeQuery('module.deleteModule', $args);
		if(!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}
		// Delete permission information
		$this->deleteModuleGrants($module_srl);
		// Remove skin information
		$this->deleteModuleSkinVars($module_srl);
		// Delete module extra vars
		$this->deleteModuleExtraVars($module_srl);
		// Remove the module manager
		$this->deleteAdminId($module_srl);
		// Call a trigger (after)
		ModuleHandler::triggerCall('module.deleteModule', 'after', $trigger_obj);

		// commit
		$oDB->commit();

		//remove from cache
		Rhymix\Framework\Cache::clearGroup('site_and_module');
		ModuleModel::$_mid_map = ModuleModel::$_module_srl_map = [];
		return $output;
	}

	/**
	 * @brief Change other information of the module
	 * @deprecated
	 */
	function updateModuleSkinVars($module_srl, $skin_vars)
	{
		return new BaseObject();
	}

	/**
	 * @brief Set is_default as N in all modules(the default module is disabled)
	 */
	function clearDefaultModule()
	{
		$output = executeQuery('module.clearDefaultModule');
		if(!$output->toBool()) return $output;

		Rhymix\Framework\Cache::clearGroup('site_and_module');
		return $output;
	}

	/**
	 * Update menu_srl of mid which belongs to menu_srl
	 *
	 * @deprecated
	 */
	public function updateModuleMenu($args)
	{
		$output = executeQuery('module.updateModuleMenu', $args);

		Rhymix\Framework\Cache::clearGroup('site_and_module');
		return $output;
	}

	/**
	 * Update menu_srl of a module.
	 *
	 * @param int $module_srl
	 * @param int $menu_srl
	 * @param bool $clear_cache
	 * @return BaseObject
	 */
	public function updateModuleMenuSrl(int $module_srl, int $menu_srl, bool $clear_cache = true): BaseObject
	{
		$output = executeQuery('module.updateModuleMenuSrl', [
			'module_srl' => $module_srl,
			'menu_srl' => $menu_srl,
		]);

		if ($clear_cache)
		{
			Rhymix\Framework\Cache::clearGroup('site_and_module');
		}
		return $output;
	}

	/**
	 * @brief Update layout_srl of mid which belongs to menu_srl
	 */
	function updateModuleLayout($layout_srl, $menu_srl_list)
	{
		if(!count($menu_srl_list)) return;

		$args = new stdClass;
		$args->layout_srl = $layout_srl;
		$args->menu_srls = implode(',',$menu_srl_list);
		$output = executeQuery('module.updateModuleLayout', $args);

		Rhymix\Framework\Cache::clearGroup('site_and_module');
		return $output;
	}

	/**
	 * @brief Specify the admin ID to a module
	 */
	function insertAdminId($module_srl, $admin_id, $scopes = null)
	{
		if (strpos($admin_id, '@') !== false)
		{
			$member_info = MemberModel::getMemberInfoByEmailAddress($admin_id);
		}
		else
		{
			$member_info = MemberModel::getMemberInfoByUserID($admin_id);
		}
		if (!$member_info || !$member_info->member_srl)
		{
			return;
		}

		$args = new stdClass();
		$args->module_srl = intval($module_srl);
		$args->member_srl = $member_info->member_srl;
		if (is_array($scopes))
		{
			$args->scopes = json_encode(array_values($scopes));
		}
		else
		{
			$args->scopes = new Rhymix\Framework\Parsers\DBQuery\NullValue;
		}
		$output = executeQuery('module.insertAdminId', $args);

		Rhymix\Framework\Cache::delete("site_and_module:module_admins:" . intval($module_srl));
		return $output;
	}

	/**
	 * @brief Remove the admin ID from a module
	 */
	function deleteAdminId($module_srl, $admin_id = '')
	{
		$args = new stdClass();
		$args->module_srl = intval($module_srl);

		if($admin_id)
		{
			if (strpos($admin_id, '@') !== false)
			{
				$member_info = MemberModel::getMemberInfoByEmailAddress($admin_id);
			}
			else
			{
				$member_info = MemberModel::getMemberInfoByUserID($admin_id);
			}
			if ($member_info && $member_info->member_srl)
			{
				$args->member_srl = $member_info->member_srl;
			}
		}

		$output = executeQuery('module.deleteAdminId', $args);
		Rhymix\Framework\Cache::delete("site_and_module:module_admins:" . intval($module_srl));
		return $output;
	}

	/**
	 * Insert skin vars to a module
	 * @param $module_srl Sequence of module
	 * @param $obj Skin variables
	 */
	function insertModuleSkinVars($module_srl, $obj)
	{
		return $this->_insertModuleSkinVars($module_srl, $obj, 'P');
	}

	/**
	 * Insert mobile skin vars to a module
	 * @param $module_srl Sequence of module
	 * @param $obj Skin variables
	 */
	function insertModuleMobileSkinVars($module_srl, $obj)
	{
		return $this->_insertModuleSkinVars($module_srl, $obj, 'M');
	}


	/**
	 * @brief Insert skin vars to a module
	 */
	function _insertModuleSkinVars($module_srl, $obj, $mode)
	{
		$mode = $mode === 'P' ? 'P' : 'M';

		$oDB = DB::getInstance();
		$oDB->begin();

		$output = $this->_deleteModuleSkinVars($module_srl, $mode);
		if(!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		getDestroyXeVars($obj);
		if(!$obj || !countobj($obj)) return new BaseObject();

		$args = new stdClass;
		$args->module_srl = $module_srl;
		foreach($obj as $key => $val)
		{
			// #17927989 For an old board which used the old blog module
			// it often saved menu item(stdClass) on the skin info column
			// When updating the module on XE core 1.2.0 later versions, it occurs an error
			// fixed the error
			if (is_object($val)) continue;
			if (is_array($val)) $val = serialize($val);

			$args->name = trim($key);
			$args->value = trim($val);
			if(!$args->name || !$args->value) continue;

			if($mode === 'P')
			{
				$output = executeQuery('module.insertModuleSkinVars', $args);
			}
			else
			{
				$output = executeQuery('module.insertModuleMobileSkinVars', $args);
			}
			if(!$output->toBool())
			{
				return $output;
				$oDB->rollback();
			}
		}

		$oDB->commit();

		return new BaseObject();
	}

	/**
	 * Remove skin vars ofa module
	 * @param $module_srl seqence of module
	 */
	function deleteModuleSkinVars($module_srl)
	{
		return $this->_deleteModuleSkinVars($module_srl, 'P');
	}

	/**
	 * Remove mobile skin vars ofa module
	 * @param $module_srl seqence of module
	 */
	function deleteModuleMobileSkinVars($module_srl)
	{
		return $this->_deleteModuleSkinVars($module_srl, 'M');
	}

	/**
	 * @brief Remove skin vars of a module
	 */
	function _deleteModuleSkinVars($module_srl, $mode)
	{
		$args = new stdClass();
		$args->module_srl = $module_srl;
		$mode = $mode === 'P' ? 'P' : 'M';

		if($mode === 'P')
		{
			$object_key = 'site_and_module:module_skin_vars:' . $module_srl;
			$query = 'module.deleteModuleSkinVars';
		}
		else
		{
			$object_key = 'site_and_module:module_mobile_skin_vars:' . $module_srl;
			$query = 'module.deleteModuleMobileSkinVars';
		}

		//remove from cache
		Rhymix\Framework\Cache::delete($object_key);
		return executeQuery($query, $args);
	}

	/**
	 * @brief Register extra vars to the module
	 */
	function insertModuleExtraVars($module_srl, $obj)
	{
		$this->deleteModuleExtraVars($module_srl);
		getDestroyXeVars($obj);

		foreach(get_object_vars($obj) as $key => $val)
		{
			if(is_object($val) || is_array($val)) continue;

			$args = new stdClass();
			$args->module_srl = $module_srl;
			$args->name = trim($key);
			$args->value = trim($val);
			if(!$args->name || !$args->value) continue;
			$output = executeQuery('module.insertModuleExtraVars', $args);
		}

		Rhymix\Framework\Cache::delete("site_and_module:module_extra_vars:$module_srl");
	}

	/**
	 * @brief Remove extra vars from the module
	 */
	function deleteModuleExtraVars($module_srl)
	{
		$args = new stdClass();
		$args->module_srl = $module_srl;
		$output = executeQuery('module.deleteModuleExtraVars', $args);

		//remove from cache
		Rhymix\Framework\Cache::delete("site_and_module:module_extra_vars:$module_srl");
		return $output;
	}

	/**
	 * @brief Grant permission to the module
	 */
	function insertModuleGrants($module_srl, $obj)
	{
		$this->deleteModuleGrants($module_srl);
		if(!$obj || !countobj($obj)) return;

		foreach($obj as $name => $val)
		{
			if(!$val || !countobj($val)) continue;

			foreach($val as $group_srl)
			{
				$args = new stdClass();
				$args->module_srl = $module_srl;
				$args->name = $name;
				$args->group_srl = trim($group_srl);
				if(!$args->name || !$args->group_srl) continue;
				executeQuery('module.insertModuleGrant', $args);
			}
		}

		Rhymix\Framework\Cache::delete("site_and_module:module_grants:$module_srl");
	}

	/**
	 * @brief Remove permission from the module
	 */
	function deleteModuleGrants($module_srl)
	{
		$args = new stdClass();
		$args->module_srl = $module_srl;
		$output = executeQuery('module.deleteModuleGrants', $args);

		Rhymix\Framework\Cache::delete("site_and_module:module_grants:$module_srl");
		return $output;
	}

	/**
	 * @brief Change user-defined language
	 */
	public static function replaceDefinedLangCode(&$output, $replace = true)
	{
		if ($replace)
		{
			$output = Context::replaceUserLang($output);
		}
	}

	/**
	 * @brief Add and update a file into the file box
	 */
	function procModuleFileBoxAdd()
	{
		$ajax = Context::get('ajax');
		if ($ajax) Context::setRequestMethod('JSON');

		$logged_info = Context::get('logged_info');
		if($logged_info->is_admin !='Y' && !$logged_info->is_site_admin)
		{
			throw new Rhymix\Framework\Exceptions\NotPermitted;
		}

		$vars = Context::gets('addfile','filter');
		$attributeNames = Context::get('attribute_name');
		$attributeValues = Context::get('attribute_value');
		if(is_array($attributeNames) && is_array($attributeValues) && count($attributeNames) == count($attributeValues))
		{
			$attributes = array();
			foreach($attributeNames as $no => $name)
			{
				if(empty($name))
				{
					continue;
				}
				$attributes[] = sprintf('%s:%s', $name, $attributeValues[$no]);
			}
			$attributes = implode(';', $attributes);
		}

		$vars->comment = $attributes;
		$module_filebox_srl = Context::get('module_filebox_srl');

		$ext = strtolower(substr(strrchr($vars->addfile['name'],'.'),1));
		$vars->ext = $ext;
		if ($vars->filter)
		{
			$filter = array_map('trim', explode(',',$vars->filter));
			if (!in_array($ext, $filter))
			{
				throw new Rhymix\Framework\Exception('msg_error_occured');
			}
		}
		if (in_array($ext, ['php', 'js']))
		{
			throw new Rhymix\Framework\Exception(sprintf(lang('msg_filebox_invalid_extension'), $ext));
		}

		$vars->member_srl = $logged_info->member_srl;

		// update
		if($module_filebox_srl > 0)
		{
			$vars->module_filebox_srl = $module_filebox_srl;
			$output = $this->updateModuleFileBox($vars);
		}
		// insert
		else
		{
			if(!Context::isUploaded()) throw new Rhymix\Framework\Exception('msg_error_occured');
			$addfile = Context::get('addfile');
			if(!is_uploaded_file($addfile['tmp_name'])) throw new Rhymix\Framework\Exception('msg_error_occured');
			if($vars->addfile['error'] != 0) throw new Rhymix\Framework\Exception('msg_error_occured');
			$output = $this->insertModuleFileBox($vars);
		}

		$this->setTemplatePath($this->module_path.'tpl');

		if (!$ajax)
		{
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispModuleAdminFileBox');
			$this->setRedirectUrl($returnUrl);
			return;
		}
		else
		{
			if($output) $this->add('save_filename', $output->get('save_filename'));
			else $this->add('save_filename', '');
		}
	}

	/**
	 * @brief Update a file into the file box
	 */
	function updateModuleFileBox($vars)
	{
		$args = new stdClass;
		// have file
		if($vars->addfile['tmp_name'] && is_uploaded_file($vars->addfile['tmp_name']))
		{
			$output = ModuleModel::getModuleFileBox($vars->module_filebox_srl);
			FileHandler::removeFile($output->data->filename);

			$path = ModuleModel::getModuleFileBoxPath($vars->module_filebox_srl);
			FileHandler::makeDir($path);

			$random = Rhymix\Framework\Security::getRandom(32, 'hex');
			$ext = substr(strrchr($vars->addfile['name'], '.'), 1);
			$save_filename = sprintf('%s%s.%s', $path, $random, $ext);
			$tmp = $vars->addfile['tmp_name'];

			if(!@move_uploaded_file($tmp, $save_filename))
			{
				return false;
			}

			$args->fileextension = $ext;
			$args->filename = $save_filename;
			$args->filesize = $vars->addfile['size'];
		}

		$args->module_filebox_srl = $vars->module_filebox_srl;
		$args->comment = $vars->comment;

		return executeQuery('module.updateModuleFileBox', $args);
		$output->add('save_filename', $save_filename);
		return $output;
	}


	/**
	 * @brief Add a file into the file box
	 */
	function insertModuleFileBox($vars)
	{
		// set module_filebox_srl
		$vars->module_filebox_srl = getNextSequence();

		// get file path
		$path = ModuleModel::getModuleFileBoxPath($vars->module_filebox_srl);
		FileHandler::makeDir($path);

		$random = Rhymix\Framework\Security::getRandom(32, 'hex');
		$ext = substr(strrchr($vars->addfile['name'], '.'), 1);
		$save_filename = sprintf('%s%s.%s', $path, $random, $ext);
		$tmp = $vars->addfile['tmp_name'];

		// upload
		if(!@move_uploaded_file($tmp, $save_filename))
		{
			return false;
		}

		// insert
		$args = new stdClass;
		$args->module_filebox_srl = $vars->module_filebox_srl;
		$args->member_srl = $vars->member_srl;
		$args->comment = $vars->comment;
		$args->filename = $save_filename;
		$args->fileextension = $ext;
		$args->filesize = $vars->addfile['size'];

		$output = executeQuery('module.insertModuleFileBox', $args);
		$output->add('save_filename', $save_filename);
		return $output;
	}


	/**
	 * @brief Delete a file from the file box
	 */
	function procModuleFileBoxDelete()
	{
		$logged_info = Context::get('logged_info');
		if($logged_info->is_admin !='Y' && !$logged_info->is_site_admin)
		{
			throw new Rhymix\Framework\Exceptions\NotPermitted;
		}

		$module_filebox_srl = Context::get('module_filebox_srl');
		if(!$module_filebox_srl)
		{
			throw new Rhymix\Framework\Exceptions\InvalidRequest;
		}

		$vars = new stdClass();
		$vars->module_filebox_srl = $module_filebox_srl;
		$output = $this->deleteModuleFileBox($vars);
		if(!$output->toBool()) return $output;
	}

	function deleteModuleFileBox($vars)
	{
		// delete real file
		$output = ModuleModel::getModuleFileBox($vars->module_filebox_srl);
		FileHandler::removeFile($output->data->filename);

		$args = new stdClass();
		$args->module_filebox_srl = $vars->module_filebox_srl;
		return executeQuery('module.deleteModuleFileBox', $args);
	}

	/**
	 * @brief function of locking (timeout is in seconds)
	 */
	function lock($lock_name, $timeout, $member_srl = null)
	{
		$this->unlockTimeoutPassed();
		$args = new stdClass;
		$args->lock_name = $lock_name;
		if(!$timeout) $timeout = 60;
		$args->deadline = date("YmdHis", $_SERVER['REQUEST_TIME'] + $timeout);
		if($member_srl) $args->member_srl = $member_srl;
		$output = executeQuery('module.insertLock', $args);
		if($output->toBool())
		{
			$output->add('lock_name', $lock_name);
			$output->add('deadline', $args->deadline);
		}
		return $output;
	}

	function unlockTimeoutPassed()
	{
		executeQuery('module.deleteLocksTimeoutPassed');
	}

	function unlock($lock_name, $deadline)
	{
		$args = new stdClass;
		$args->lock_name = $lock_name;
		$args->deadline = $deadline;
		$output = executeQuery('module.deleteLock', $args);
		return $output;
	}

	/**
	 * @deprecated
	 */
	function updateModuleInSites($site_srls, $args)
	{

	}

	/**
	 * Check if all action-forwardable routes are registered. If not, register them.
	 *
	 * @param string $module_name
	 * @return object
	 */
	public function registerActionForwardRoutes(string $module_name)
	{
		$action_forward = ModuleModel::getActionForward();
		$module_action_info = ModuleModel::getModuleActionXml($module_name);

		// Get the list of forwardable actions and their routes.
		$forwardable_routes = array();
		foreach ($module_action_info->action ?: [] as $action_name => $action_info)
		{
			if (count($action_info->route) && $action_info->standalone === 'true')
			{
				$forwardable_routes[$action_name] = array(
					'type' => $module_action_info->action->{$action_name}->type,
					'regexp' => array(),
					'config' => $action_info->route,
					'global_route' => $action_info->global_route === 'true' ? 'Y' : 'N',
				);
			}
		}
		foreach ($module_action_info->route->GET as $regexp => $action_name)
		{
			if (isset($forwardable_routes[$action_name]))
			{
				$forwardable_routes[$action_name]['regexp'][] = ['GET', $regexp];
			}
		}
		foreach ($module_action_info->route->POST as $regexp => $action_name)
		{
			if (isset($forwardable_routes[$action_name]))
			{
				$forwardable_routes[$action_name]['regexp'][] = ['POST', $regexp];
			}
		}

		// Insert or delete from the action_forward table.
		foreach ($forwardable_routes as $action_name => $route_info)
		{
			if (!isset($action_forward[$action_name]))
			{
				$output = $this->insertActionForward($module_name, $route_info['type'], $action_name,
					$route_info['regexp'], $route_info['config'], $route_info['global_route']);
				if (!$output->toBool())
				{
					return $output;
				}
			}
			elseif ($action_forward[$action_name]->route_regexp !== $route_info['regexp'] ||
				$action_forward[$action_name]->route_config !== $route_info['config'] ||
				$action_forward[$action_name]->global_route !== $route_info['global_route'])
			{
				$output = $this->deleteActionForward($module_name, $route_info['type'], $action_name);
				if (!$output->toBool())
				{
					return $output;
				}

				$output = $this->insertActionForward($module_name, $route_info['type'], $action_name,
					$route_info['regexp'], $route_info['config'], $route_info['global_route']);
				if (!$output->toBool())
				{
					return $output;
				}
			}
		}

		// Clean up any action-forward routes that are no longer needed.
		foreach ($forwardable_routes as $action_name => $route_info)
		{
			unset($action_forward[$action_name]);
		}
		foreach ($action_forward as $action_name => $forward_info)
		{
			if ($forward_info->module === $module_name && $forward_info->route_regexp !== null)
			{
				$output = $this->deleteActionForward($module_name, null, $action_name);
				if (!$output->toBool())
				{
					return $output;
				}
			}
		}

		return new BaseObject();
	}

	/**
	 * Check if all event handlers are registered. If not, register them.
	 *
	 * @param string $module_name
	 * @return object
	 */
	public function registerEventHandlers(string $module_name)
	{
		$module_action_info = ModuleModel::getModuleActionXml($module_name);
		$registered_event_handlers = [];

		// Insert new event handlers.
		foreach ($module_action_info->event_handlers ?? [] as $ev)
		{
			$key = implode(':', [$ev->event_name, $module_name, $ev->class_name, $ev->method, $ev->position]);
			$registered_event_handlers[$key] = true;
			if(!ModuleModel::getTrigger($ev->event_name, $module_name, $ev->class_name, $ev->method, $ev->position))
			{
				$output = $this->insertTrigger($ev->event_name, $module_name, $ev->class_name, $ev->method, $ev->position);
				if (!$output->toBool())
				{
					return $output;
				}
			}
		}

		// Remove event handlers that are no longer defined by this module.
		if (count($registered_event_handlers))
		{
			// Refresh cache
			ModuleModel::getTriggers('null', 'null');

			foreach ($GLOBALS['__triggers__'] as $trigger_name => $val1)
			{
				foreach ($val1 as $called_position => $val2)
				{
					foreach ($val2 as $item)
					{
						if ($item->module === $module_name)
						{
							$key = implode(':', [$trigger_name, $item->module, $item->type, $item->called_method, $called_position]);
							if (!isset($registered_event_handlers[$key]))
							{
								$this->deleteTrigger($trigger_name, $item->module, $item->type, $item->called_method, $called_position);
							}
						}
					}
				}
			}
		}

		return new BaseObject();
	}

	/**
	 * Check if all custom namespaces are registered. If not, register them.
	 *
	 * @param string $module_name
	 * @return object
	 */
	public function registerNamespaces(string $module_name)
	{
		$module_action_info = ModuleModel::getModuleActionXml($module_name);
		$namespaces = config('namespaces') ?? [];
		$changed = false;

		// Add all namespaces defined by this module.
		foreach ($module_action_info->namespaces ?? [] as $name)
		{
			if (preg_match('/^Rhymix\\\\/i', $name))
			{
				continue;
			}

			if (!isset($namespaces['mapping'][$name]))
			{
				$namespaces['mapping'][$name] = 'modules/' . $module_name;
				$changed = true;
			}
		}

		// Remove namespaces that are no longer defined by this module.
		foreach ($namespaces['mapping'] ?? [] as $name => $path)
		{
			$attached_module = preg_replace('!^modules/!', '', $path);
			if ($attached_module === $module_name && !in_array($name, $module_action_info->namespaces ?? []))
			{
				unset($namespaces['mapping'][$name]);
				$changed = true;
			}
		}

		// Generate a regular expression for routing.
		$regexp = [];
		unset($namespaces['regexp']);
		foreach ($namespaces['mapping'] ?? [] as $name => $path)
		{
			$regexp[] = preg_quote(strtr($name, '\\', '/'), '!');
		}
		if (count($regexp))
		{
			usort($regexp, function($a, $b) { return strlen($b) - strlen($a); });
			$namespaces['regexp'] = '!^(' . implode('|', $regexp) . ')/((?:\\w+/)*)(\\w+)$!';
		}

		// Update system configuration.
		if ($changed)
		{
			Rhymix\Framework\Config::set('namespaces', $namespaces);
			Rhymix\Framework\Config::save();
		}

		return new BaseObject();
	}

	/**
	 * Check if all prefixes for a module are registered. If not, register them.
	 *
	 * @param string $module_name
	 * @return object
	 */
	public function registerPrefixes(string $module_name)
	{
		// TODO
		return new BaseObject();
	}
}
/* End of file module.controller.php */
/* Location: ./modules/module/module.controller.php */
