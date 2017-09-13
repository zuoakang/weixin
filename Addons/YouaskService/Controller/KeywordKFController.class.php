<?php

namespace Addons\YouaskService\Controller;
use Addons\YouaskService\Controller\BaseController;

class KeywordKFController extends BaseController{
	var $model;	
	function _initialize() {
		parent::_initialize();		
		$this->model = $this->getModel ( 'youaskservice_keyword' );	
		$this->model || $this->error ( '模型不存在！' );
		
	}
	
	
	
	// 通用插件的列表模型
	public function lists() {
		// 使用提示		
		$normal_tips = '当用户发送的消息包含匹配的关键字时,将自动转入人工客服。<br/>
		注意:<br/>
		&nbsp;&nbsp;&nbsp;&nbsp;1、一旦接入人工客服后,该用户发送的消息自动转发到多客服。<br/>
		&nbsp;&nbsp;&nbsp;&nbsp;2、当接入的用户和客服会话沉静半小时会自动过期，过期后，如果粉丝发送新的消息，原来接待的客服可以重新接入，<br/>
		&nbsp;&nbsp;&nbsp;&nbsp;其他客服也可以在待接入消息中回复。<br/>
		&nbsp;&nbsp;&nbsp;&nbsp;3、当设置的关键字被停用后，用户发送包含此关键字将不再自动转人工客服。';
		$this->assign ( 'normal_tips', $normal_tips );
				
		$token = get_token();
		$page = I ( 'p', 1, 'intval' ); // 默认显示第一页数据		                                
		// 解析列表规则
		$list_data = $this->_list_grid ( $this->model );
		// 关键字搜索
		$map ['token'] = $token;
		$key = $this->model ['search_key'] ? $this->model ['search_key'] : 'title';
		if (isset ( $_REQUEST [$key] )) {
			$map [$key] = array (
					'like',
					'%' . htmlspecialchars ( $_REQUEST [$key] ) . '%' 
			);
			unset ( $_REQUEST [$key] );
		}
		// 条件搜索
		foreach ( $_REQUEST as $name => $val ) {
			if (in_array ( $name, $fields )) {
				$map [$name] = $val;
			}
		}
		$row = empty ( $this->model ['list_row'] ) ? 20 : $this->model ['list_row'];
		
		// 读取模型数据列表		
		empty ( $fields ) || in_array ( 'id', $fields ) || array_push ( $fields, 'id' );
				
		//特殊处理msgkfaccount字段
		if(in_array ( 'msgkfaccount', $fields )){
			$fields_index  = array_search('msgkfaccount', $fields );
			$fields[$fields_index] = " (case when zdtype='1' then (SELECT groupname from ".C ( 'DB_PREFIX' )."youaskservice_group a where a.token=token and a.id=kfgroupid) else msgkfaccount END) as msgkfaccount ";
		}
		
				
		$name = parse_name ( get_table_name ( $this->model ['id'] ), true );
		/* 查询记录总数 */
		$count = M ( $name )->where ( $map )->count ();			
		$data = M ( $name )->field ( empty ( $fields ) ? true : $fields )->where ( $map )->order ( 'id DESC' )->page ( $page, $row )->select ();		
		
		
		// 分页
		if ($count > $row) {
			$page = new \Think\Page ( $count, $row );
			$page->setConfig ( 'theme', '%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%' );
			$this->assign ( '_page', $page->show () );
		}
		$list_data['list_data'] = $data;
		$this->assign ( $list_data );

		$this->meta_title = $this->model ['title'] . '列表';
		
		
		$this->display ();
	}
		
	public function del() {
		$ids = I ( 'id', 0 );
		if (empty ( $ids )) {
			$ids = array_unique ( ( array ) I ( 'ids', 0 ) );
		}
		if (empty ( $ids )) {
			$this->error ( '请选择要操作的数据!' );
		}
		
		$Model = M ( get_table_name ( $this->model ['id'] ) );
		$map = array (
				'id' => array (
						'in',
						$ids 
				) 
		);
		$map ['token'] = get_token ();
		if ($Model->where ( $map )->delete ()) {
			$this->success ( '删除成功' );
		} else {
			$this->error ( '删除失败！' );
		}
	}
	
	
	public function edit() {
		// 获取模型信息
		$id = I ( 'id', 0, 'intval' );
		$token =  get_token ();
		if (IS_POST) {
			if($_POST["zdtype"]=="1"){$_POST["msgkfaccount"]="";}
			else{ $_POST["kfgroupid"]=0;}
			
			$Model = D ( parse_name ( get_table_name ( $this->model ['id'] ), 1 ) );
			// 获取模型的字段信息
			$Model = $this->checkAttr ( $Model, $this->model ['id'] );
			if ($Model->create () && $Model->save ()) {								
				$this->success ( '保存' . $this->model ['title'] . '成功！', U ( 'lists') );
			} else {
				$this->error ( $Model->getError () );
			}
		} else {
			$fields = get_model_attribute ( $this->model ['id'] );
			
			// 获取数据
			$data = M ( get_table_name ( $this->model ['id'] ) )->find ( $id );
			$data || $this->error ( '数据不存在！' );
			
		$token = get_token ();
		if (isset ( $data ['token'] ) && $token != $data ['token'] && defined ( 'ADDON_PUBLIC_PATH' )) {
			$this->error ( '非法访问！' );
		}			
			
			// 工号人员表
			$option_users = M("youaskservice_user")->where(array("token"=>$token))->order("id desc")->select();
			$this->assign ( 'option_users', $option_users );
			
			// 工号分组表
			$option_group = M("youaskservice_group")->where(array("token"=>$token))->order("id desc")->select();
			$this->assign ( 'option_group', $option_group );
			
			$this->assign ( 'fields', $fields );
			$this->assign ( 'data', $data );
			$this->meta_title = '编辑' . $this->model ['title'];
			$this->display ('edit');
		}
	}
	public function add() {
		$token =  get_token ();
		
		if (IS_POST) {
			// 自动补充token
			$_POST ['token'] =$token;
			if($_POST["zdtype"]=="1"){$_POST["msgkfaccount"]="";}
			else{ $_POST["kfgroupid"]=0;}
			$Model = D ( parse_name ( get_table_name ( $this->model ['id'] ), 1 ) );
			// 获取模型的字段信息
			$Model = $this->checkAttr ( $Model, $this->model ['id'] );
			if ($Model->create () && $vote_id = $Model->add ()) {								
				$this->success ( '添加' . $this->model ['title'] . '成功！', U ( 'lists') );
			} else {
				$this->error ( $Model->getError () );
			}
		} else {
			
			$fields = get_model_attribute ( $this->model ['id'] );
			$this->assign ( 'fields', $fields );
						
			// 工号人员表
			$option_users = M("youaskservice_user")->where(array("token"=>$token))->order("id desc")->select();
			$this->assign ( 'option_users', $option_users );
			
			// 工号分组表
			$option_group = M("youaskservice_group")->where(array("token"=>$token))->order("id desc")->select();
			$this->assign ( 'option_group', $option_group );
			
			$this->meta_title = '新增' . $this->model ['title'];
			$this->display ("add");
		}
	}
	
	
	//批量启用或停用管理
	public function plqtguanli() {
		$ids = I ( 'id', 0 );
		$type = I ( 'get.type', 0, 'intval' );
		
		if (empty ( $ids )) {
			$ids = array_unique ( ( array ) I ( 'ids', 0 ) );
		}
		if (empty ( $ids )) {
			$this->error ( '请选择要操作的数据!' );
		}
		
		$Model = M ( get_table_name ( $this->model ['id'] ) );
		$map = array (
				'id' => array (
						'in',
						$ids 
				) 
		);
		$map ['token'] = get_token ();		
		
		if ($Model->where ( $map )->data (array("msgstate"=>$type))->save()) {
			$this->success ( '操作成功' );
		} else {
			$this->error ( '操作失败！' );
		}
	}
}
