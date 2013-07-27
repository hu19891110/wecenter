<?php
/*
+--------------------------------------------------------------------------
|   Anwsion [#RELEASE_VERSION#]
|   ========================================
|   by Anwsion dev team
|   (c) 2011 - 2012 Anwsion Software
|   http://www.anwsion.com
|   ========================================
|   Support: zhengqiang@gmail.com
|   
+---------------------------------------------------------------------------
*/


if (!defined('IN_ANWSION'))
{
	die;
}

class main extends AWS_CONTROLLER
{
	public function get_access_rule()
	{
		$rule_action['rule_type'] = "white"; //'black'黑名单,黑名单中的检查  'white'白名单,白名单以外的检查
		$rule_action['actions'] = array(
			'browser_not_support'
		);
		
		if ($this->user_info['permission']['visit_explore'] AND $this->user_info['permission']['visit_site'])
		{
			$rule_action['actions'][] = 'index';
			$rule_action['actions'][] = 'explore';
		}
		
		return $rule_action;
	}

	public function setup()
	{
		if (is_mobile() AND HTTP::get_cookie('_ignore_ua_check') != 'TRUE' AND !$_GET['ignore_ua_check'])
		{
			switch ($_GET['app'])
			{
				default:
					HTTP::redirect('/m/');
				break;
			}
		}
		
		if ($_GET['ignore_ua_check'] == 'TRUE')
		{
			HTTP::set_cookie('_ignore_ua_check', 'TRUE', (time() + 3600 * 24 * 7));
		}
	}

	public function index_action()
	{		
		if (! $this->user_id)
		{
			$this->explore_action();
			exit;
		}
		
		//边栏可能感兴趣的人或话题
		if (TPL::is_output('block/sidebar_recommend_users_topics.tpl.htm', 'home/index'))
		{
			$recommend_users_topics = $this->model('module')->recommend_users_topics($this->user_id);
			
			TPL::assign('sidebar_recommend_users_topics', $recommend_users_topics);
		}
		
		//边栏热门用户
		if (TPL::is_output('block/sidebar_hot_users.tpl.htm', 'home/index'))
		{
			$sidebar_hot_users = $this->model('module')->sidebar_hot_users($this->user_id);
			
			TPL::assign('sidebar_hot_users', $sidebar_hot_users);
		}
		
		$this->crumb(AWS_APP::lang()->_t('首页'), '/');
		
		TPL::import_js('js/index.js');
		
		if ($_GET['first_login'])
		{
			TPL::import_js('js/ajaxupload.js');
		}
		
		TPL::output('home/index');
	}

	public function explore_action()
	{
		if ($this->user_id)
		{
			$this->crumb(AWS_APP::lang()->_t('发现'), '/home/explore/');
		}
		
		// 导航
		if (TPL::is_output('block/content_nav_menu.tpl.htm', 'home/explore'))
		{
			$nav_menu = $this->model('menu')->get_nav_menu_list(null, true);
			
			TPL::assign('feature_ids', $nav_menu['feature_ids']);
			
			unset($nav_menu['feature_ids']);
			
			TPL::assign('content_nav_menu', $nav_menu);
		}
		
		//边栏可能感兴趣的人
		if (TPL::is_output('block/sidebar_recommend_users_topics.tpl.htm', 'home/explore'))
		{
			$recommend_users_topics = $this->model('module')->recommend_users_topics($this->user_id);
			TPL::assign('sidebar_recommend_users_topics', $recommend_users_topics);
		}
		
		//边栏热门用户
		if (TPL::is_output('block/sidebar_hot_users.tpl.htm', 'home/explore'))
		{
			$sidebar_hot_users = $this->model('module')->sidebar_hot_users($this->user_id, 5);
			
			TPL::assign('sidebar_hot_users', $sidebar_hot_users);
		}
		
		//边栏热门话题
		if (TPL::is_output('block/sidebar_hot_topics.tpl.htm', 'home/explore'))
		{
			$sidebar_hot_topics = $this->model('module')->sidebar_hot_topics($_GET['category']);
			
			TPL::assign('sidebar_hot_topics', $sidebar_hot_topics);
		}
		
		//边栏专题
		if (TPL::is_output('block/sidebar_feature.tpl.htm', 'home/explore'))
		{
			$feature_list = $this->model('module')->feature_list();
			
			TPL::assign('feature_list', $feature_list);
		}
		
		if ($_GET['category'])
		{
			if (is_numeric($_GET['category']))
			{
				$category_info = $this->model('system')->get_category_info($_GET['category']);
			}
			else
			{
				$category_info = $this->model('system')->get_category_info_by_url_token($_GET['category']);
			}
		}
		
		if ($category_info)
		{
			TPL::assign('category_info', $category_info);
			
			$this->crumb($category_info['title'], '/explore/category-' . $category_info['id']);
			
			$meta_description = $category_info['title'];
			
			if ($category_info['description'])
			{
				$meta_description .= ' - ' . $category_info['description'];
			}
			
			TPL::set_meta('description', $meta_description);
		}
		
		// 问题
		if (TPL::is_output('block/content_question.tpl.htm', 'home/explore'))
		{	
			if (! $_GET['sort_type'])
			{
				$_GET['sort_type'] = 'new';
			}
			
			if ($_GET['sort_type'] == 'unresponsive')
			{
				$_GET['answer_count'] = '0';
			}
			
			if ($_GET['sort_type'] == 'hot')
			{
				$question_list = $this->model('question')->get_hot_question($_GET['category'], $_GET['topic_id'], $_GET['day'], $_GET['page'], get_setting('contents_per_page'));
			}
			else
			{
				$question_list = $this->model('question')->get_questions_list($_GET['page'], get_setting('contents_per_page'), $_GET['sort_type'], $_GET['topic_id'], $_GET['category'], $_GET['answer_count'], $_GET['day'], $_GET['is_recommend']);
			}
				
			if ($question_list)
			{
				foreach ($question_list AS $key => $val)
				{
					if ($val['answer_count'])
					{
						$question_list[$key]['answer_users'] = $this->model('question')->get_answer_users_by_question_id($val['question_id'], 2, $val['published_uid']);
					}
				}
			}
					
			TPL::assign('pagination', AWS_APP::pagination()->initialize(array(
				'base_url' => get_js_url('/home/explore/sort_type-' . preg_replace("/[\(\)\.;']/", '', $_GET['sort_type']) . '__category-' . $category_info['id'] . '__day-' . intval($_GET['day']) . '__is_recommend-' . $_GET['is_recommend']), 
				'total_rows' => $this->model('question')->get_questions_list_total(),
				'per_page' => get_setting('contents_per_page')
			))->create_links());
			
			TPL::assign('question_list', $question_list);
			TPL::assign('question_list_bit', TPL::output('question/ajax/list', false));
		}
		
		TPL::output('home/explore');
	}
	
	public function browser_not_support_action()
	{
		TPL::output('global/browser_not_support');
	}
}