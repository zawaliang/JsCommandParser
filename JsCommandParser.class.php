<?php
/**
 * Javascript可视指令解析器
 * @description: 通过自定义指令,自动寻址并提取javascript中的内容
 * @author: zawa / www.zawaliang.com
 * @version: v1.0.0
 * @date: 2013/02/04
 * 
 * @usage:
 *  	// 实例化JsCommandParser类
 * 	$cp = new JsCommandParser($str);
 * 	//  设置当前用户,用户受限访问控制
 *	$cp->user = 'xxx';
 *	// 调用parse方法解析指令
 *	$cp->parse()
 *	// 给内容添加hash寻址指令
 *	$cp->hash();
 *	// 通过hash表单集合设置指令对应配置项
 *	$cp->set($data)
 *	// 删除内容中的指令集
 *	$cp->del([$str]);
 */

class JsCommandParser {
	
	// 支持的标签列表
	private $tag = array(
		// 标签允许的Javascript类型
		'toggle' => array('boolean', 'number', 'string'),
		'radio' => array('number', 'string'),
		'checkbox' => array('array'),
		'select' => array('number', 'string'),
		'input' => array('number', 'string'),
		'textarea' => array('string'),
		'date' => array('string'),
		'range' => array('array'),
		'step' => array('number', 'string'),
		'colorpicker' => array('string'),
	);
	
	// 需格式化内容
	private $input = '';

	// 当前用户,受限访问时需要
	public $user = null;
	
	/**
	 * 构造函数
	 * @param string $input
	 */
	public function __construct($input) {
		$this->input = $input;
	}
	
	/**
	 * 获取可视配置
	 * @param CONST $type PREG_PATTERN_ORDER|PREG_SET_ORDER|PREG_OFFSET_CAPTURE
	 * @return array|Exception
	 */
	private function get($type=PREG_PATTERN_ORDER) {
//		PREG_PATTERN_ORDER
//		PREG_SET_ORDER
//		PREG_OFFSET_CAPTURE

		$tag = "(" . implode("|", array_keys($this->tag)) . ")";
		
		// 匹配单行注释 "// @toggle xxx"的形式
		$pattern = "/\/\/ +@" . $tag . " +([^\r\n]+)[\r\n]/";
		
		$result = preg_match_all($pattern, $this->input, $matches, $type);
		if ($result === false) {
			throw new Exception('Error:Parse Error');
		}
		return $matches;
	}
	
	
	/**
	 * 解析器
	 * @return array|false
	 */
	public function parse() {
		// 获取可视配置
		$matches = $this->get(PREG_PATTERN_ORDER);
		
		// 格式化配置
		$ctl_type = $matches[1]; // 控件类型
		$ctl_conf = $matches[2]; // 控件配置
		
		$visual = array();
		$default_group_array = array(); // 未分组数据放最后
		
		foreach ($ctl_type as $k => $v) {
			$tmp_visual = array();
			
			$tmp_visual = $this->format_key_val($ctl_conf[$k]);
			
			// 用户访问控制,过滤掉没有访问权限的指令项
			if (!empty($tmp_visual['access']) 
				&& !in_array($this->user, explode(';', $this->formatStr($tmp_visual['access'])))) {
				unset($tmp_visual);
				continue;
			}
			
			// 获取指令对应的配置项
			$conf = $this->get_conf_by_hash($tmp_visual['hash']);
			
			// 匹配配置项失败时,忽略此指令
			if ($conf !== false) {
				$tag_val = $this->convert_conf_to_tag_val($v, $conf[1]);
				
				// 检查配置项格式是否为合法的指令允许格式
				if ($tag_val !== false) {
					$type = $this->get_js_type($conf[1]);
					
					$tmp_visual['tag'] = $v;
					$tmp_visual['tag_val'] = $tag_val;
					$tmp_visual['js_type'] = $type; // 返回配置项数据格式,可供校验使用
					
					// 分组存储
					if (!isset($tmp_visual['group'])) {
						$tmp_visual['group'] = '';
						if (!is_array($default_group_array[''])) {
							$default_group_array[''] = array();
						}
						$default_group_array[''][] = $tmp_visual;
					} else {
						if (!is_array($visual[$tmp_visual['group']])) {
							$visual[$tmp_visual['group']] = array();
						}
						$visual[$tmp_visual['group']][] = $tmp_visual;
					}
				}
			}
		}
		
		// 合并数组
		$visual = array_merge($visual, $default_group_array);
		unset($default_group_array);
		
		if (count($visual) < 1) {
			$visual = array();
		}
		
		return $visual;
	}
	
	/**
	 * 格式化指令的key-value
	 * @param string $str
	 * @return array
	 */
	private function format_key_val($str) {
		$result = array();
		$str = trim($str);
		$str = substr($str, 0, -1);
		$str = preg_split('/" +/', $str);
		foreach ($str as $k => $v) {
			$v .= '"';
			$v = explode('=', $v);
			$result[$v[0]] = substr($v[1], 1, -1); // 去掉双引号
		}
		return $result;
	}
	
	/**
	 * 通过hash获取指令对应配置项
	 * @param string $hash
	 * @return array|false
	 */
	private function get_conf_by_hash($hash) {
		$pattern = '/hash="' . $hash . '"[^\r\n]*[\r\n]+([^\r\n]*)[\r\n]?/';
		preg_match($pattern, $this->input, $matches);
		
		// 先去除注释
		$conf = $this->remove_comment($matches[1]);
		// 再检测数据格式
		$conf = $this->format_conf($conf);
		return $conf;
	}
	
	/**
	 * 格式化分号分割的字符串,并去重去空
	 * @return string
	 */
	private function formatStr($str) {
		$str = trim($str);
		if (!empty($str)) {
			$str = explode(';', $str);
			// 去除空白
			foreach ($str as $k => $v) {
				$v = trim($v);
				if (empty($v)) {
					unset($str[$k]);
				} else {
					$str[$k] = $v;
				}
			}
			$str = implode(';', array_unique($str)); // 列表去重
		}
		return $str;
	}
	
	/**
	 * 格式化配置项(请先去除注释再调用)
	 * @param string $conf
	 * @return array|false
	 */
	private function format_conf($conf) {
		// 去除配置项两侧空格
		$conf = trim($conf);
		
		// 去除配置项末尾的逗号或分号(如果有)
		$last_word = substr($conf, -1, 1);
		if (in_array($last_word, array(',', ';'))) {
			$conf = substr($conf, 0, -1);
		}
		
		/**
		 * 根据等号、冒号或整行纯数字或纯字符串赋值方式提取配置
		 * 
		 * var demo = 1;
		 * 
		 * var demo = {
		 * 		A: 1,
		 * 		B: 2
		 * };
		 * 
		 * 整行纯数字或纯字符串格式
		 * var demo = [
		 * 		10,
		 * 		'20',
		 * 		"string"
		 * ];
		 * 
		 */
		
		$type = $this->get_js_type($conf);
		
		if (!in_array($type, array('number', 'string'))) {
			$equal = (int)strpos($conf, '='); // 等号位置
			$colon = (int)strpos($conf, ':'); // 冒号位置
			
			if ($colon > 0 && ($equal == 0 || $equal > $colon)) { // 冒号先出现,使用冒号赋值
				$conf = explode(':', $conf, 2);
			} elseif ($equal > 0 && ($colon == 0 || $colon > $equal)) { // 等号先出现,使用等号赋值
				$conf = explode('=', $conf, 2);
			} else {
				return false;
			}
			$conf[0] = trim($conf[0]);
			$conf[1] = trim($conf[1]);
		} else { // 整行
			$conf = array('whole_line', $conf);
		}
		
		return $conf;
	}
	
	/**
	 * 获取javascript中非单独成行的单行注释
	 * eg:
	 * var select1 = "1"; // 注释
	 * 
	 */
	private function get_comment($str) {
		$str = trim($str);
		
		/**
		 * 由于指令支持单行的数字或者字符串格式, 这里先检查是否单行的纯数字或者纯字符格式
		 * eg: 
		 * var demo = [
		 *       70, // 注释
		 *       "1://zawa" //注释
		 *   ]
		 * 
		 */
		$pattern = '/^\s*(?:(?:(?:-?\d+(?:\.\d+)?)|(?:"[^\r\n"]*")|(?:' . "'[^\r\n']*'" . '))[\,;]?)\s*(\/\/.*)?$/i';
		
		if (preg_match($pattern, $str, $matches)) {
			$comment = $matches[1];
		} else {
			$comment = $this->get_comment_no_pure($str);
		}
		return $comment;
	}
	
	/**
	 * 获取非单行的纯数字或者纯字符格式的注释
	 */
	private function get_comment_no_pure($str) {
		// 非单行格式的,从最后一个注释开始,检查删除注释后,是否为正确的javascript格式,否则递归删除检查
		$pos = strrpos($str, '//');
		if ($pos !== false) {
			$content = trim(substr($str, 0, $pos));
			$tmp = $this->format_conf($content);
			$type = $this->get_js_type($tmp[1]);
			
			// 非合法javascript格式,继续递归检查
			if ($type === null) {
				$comment = $this->get_comment_no_pure($content);
				if ($comment === '') {
					$pos = false;
				}	
			}
		}
		return ($pos === false) ? '' : substr($str, $pos);
	}
	
	/**
	 * 移除注释
	 */
	private function remove_comment($str) {
		$comment = $this->get_comment($str);
		return str_replace($comment, '', $str);
	}

	/**
	 * 转义javascript中的string单双引号, eg: '<a href="xxx" onclick="do('xx');">xxx</a>' => '<a href="xxx" onclick="do(\'xx\');">xxx</a>'
	 *  @param string $str
	 *  @param string $type 单双引号标识
	 */
	private function trans_js_string($str, $type) {
		if ($type == 'single') {
			$str = str_replace("\'", "'", $str); // 将已有的 \' 转换为 '
			$str = str_replace("'", "\'", $str); // 将所有的 ' 转换为 \'
			$str = "'" . $str . "'";
		} else if ($type == 'double') {
			$str = str_replace('\"', '"', $str); // 将已有的 \" 转换为 "
			$str = str_replace('"', '\"', $str); // 将所有的 " 转换为 \"
			$str = '"' . $str . '"';
		}
		return $str;
	}
	
	/**
	 * 转换配置项格式为标签格式
	 * @param string $tag 标签类型
	 * @param string $conf
	 * @return array|false
	 */
	private function convert_conf_to_tag_val($tag, $conf) {
		$type = $this->get_js_type($conf);

		// 检查配置项格式是否为指令允许格式
		$legal_js_type = $this->tag[$tag];
		if (!in_array($type, $legal_js_type)) {
			return false;
		}
		
		switch($type) {
			case 'string': // 去除两侧单引号或双引号
				$conf = substr($conf, 1, -1);
				break;
			case 'array':
				// [1,2] -> 1,2
				$conf = substr($conf, 1, -1);
				// 格式化数组每项
				$conf = explode(',', $conf);
				$conf = $this->format_each_conf($conf);
				$conf = implode(',', $conf);
				break;
		}
		switch ($tag) {
			case 'toggle': // true -> 1, false -> 0
				$conf = ($conf == 'true' || $conf == 1) ? 1 : 0;
				break;
		}
		return $conf;
	}
	/**
	 * 转换标签格式为Javascript配置项格式
	 * 严格类型检查,当类型不符时不做处理
	 * @param string $tag 标签类型
	 * @param string $conf
	 * @param string $tag_val
	 * @return array|false
	 */
	private function convert_tag_val_to_conf($tag, $conf, $tag_val) {
		$type = $this->get_js_type($conf);

		// 检查配置项格式是否为合法的指令允许格式, 非法则不做处理
		$legal_js_type = $this->tag[$tag];
		if (!in_array($type, $legal_js_type)) {
			return false;
		}
		
		// toggle等checkbox类型没选中时处理
		if (!isset($tag_val)) {
			$tag_val = array();
		}
		
		switch ($tag) {
			case 'toggle':
				switch ($type) {
					case 'boolean':
						$tag_val = (int)$tag_val; // (int)array() -> 0
						$result = $tag_val == 1 ? 'true' : 'false';		
						break;
					case 'number':
						$result = (int)$tag_val;
						break;
					case 'string':
						$result = '"' . (int)$tag_val . '"';	
						break;
				}	
				break;
			case 'radio':
			case 'select':
			case 'step':
			case 'input':
				switch ($type) {
					case 'number':
						// 非input标签不要float,避免2.00等格式数据float后被转换为2
						// input标签强制转换,防止javascript文件出错
						$result = ($tag == 'input') ? (float)$tag_val : $tag_val;
						break;
					case 'string':
						// 根据js内容项格式匹配单双引号
						$string_type = $this->get_js_string_type($conf);
						$result   = $this->trans_js_string($tag_val, $string_type);
						break;
				}	
				break;
			case 'checkbox':
				switch ($type) {
					case 'array':
						$result = $this->format_each_tag($conf, $tag_val);
						$result = '[' . implode(',', $result) . ']';
						break;
				}
				break;
			case 'range':
				$tag_val = explode('-', $tag_val);
				$result = $this->format_each_tag($conf, $tag_val);
				$result = '[' . implode(',', $result) . ']';
				break;
			case 'date':
			case 'colorpicker':
				switch ($type) {
					case 'string':
						$string_type = $this->get_js_string_type($conf);
						$result   = $this->trans_js_string($tag_val, $string_type);
						break;
				}
				break;
			case 'textarea':
				switch ($type) {
					case 'string':
						// 转换回车换行为空
						$tag_val = preg_replace("/[\\r\\n]/", "", $tag_val);
						
						$string_type = $this->get_js_string_type($conf);
						$result   = $this->trans_js_string($tag_val, $string_type);
						break;
				}
				break;
		}
		
		return $result;
	}
	
	/**
	 * 通过hash表单集合设置指令对应配置项
	 * @param array $data
	 * @return array|Exception
	 */
	public function set($data) {
		$this->set_data = $data; // 供回调函数使用
		$this->set_fail = 0; // 匹配失败数
		
		$content = $this->input; // 不修改原有内容
		$hash_list = $data['cps_visual_hash'];
		$tag = '(' . implode('|', array_keys($this->tag)) . ')';
		$p = '(\/\/ +@' . $tag . ' +([^\r\n]*)';
		
		foreach ($hash_list as $k => $v) {
			$pattern = '/' . $p . 'hash="(' . $v . ')"[^\r\n]*[\r\n]+)([^\r\n]*)([\r\n]?)/';
			$content = preg_replace_callback($pattern, array(&$this, 'set_callback'), $content);
			if ($content === false) {
				throw new Exception('Error:Set Error');
			}
		}
		return array(
			'fail' => $this->set_fail,
			'content' => $content,
		);
	}
	private function set_callback($matches) {
		$tag = $matches[2];
		$hash = $matches[4];
		
		// 用户访问控制,没有访问权限时不处理
		$key_val = $this->format_key_val($matches[3]);
		if (!empty($key_val['access']) 
			&& !in_array($this->user, explode(';', $this->formatStr($key_val['access'])))) {
			$this->set_fail++;
			return $matches[0];
		}
		
		// 获取注释
		$conf = $matches[5];
		$comment = $this->get_comment($conf);
		
		// 移除注释
		$conf_before_rtrim = str_replace($comment, '', $conf);
		
		// 去除配置项右侧空格
		$conf = rtrim($conf_before_rtrim);
		
		// 保留右侧空格(不更改原格式)
		$right_space = substr($conf_before_rtrim, strlen($conf));
		
		// 去除配置项末尾的逗号或分号
		$last_word = substr($conf, -1, 1);
		if (in_array($last_word, array(',', ';'))) {
			$conf = substr($conf, 0, -1);
		} else {
			$last_word = '';
		}

		$whole_line = ltrim($conf);
		$type = $this->get_js_type($whole_line);
		
		/**
		 * 根据等号、冒号或整行赋值方式提取配置
		 * 
		 * var demo = 1;
		 * 
		 * var demo = {
		 * 		A: 1,
		 * 		B: 2
		 * };
		 * 
		 * var demo = [
		 * 		10,
		 * 		20
		 * ];
		 * 
		 */
		if ($type === null) {
			$equal = (int)strpos($conf, '='); // 等号位置
			$colon = (int)strpos($conf, ':'); // 冒号位置
			
			if ($colon > 0 && ($equal == 0 || $equal > $colon)) { // 冒号先出现,使用冒号赋值
				$symbol = ':';
			} elseif ($equal > 0 && ($colon == 0 || $colon > $equal)) { // 等号先出现,使用等号赋值
				$symbol = '=';
			} else {
				return false;
			}
			
			$conf = explode($symbol, $conf, 2);
			$symbol_with_space = $conf[1];
			$conf[1] = ltrim($conf[1]);
			$symbol_with_space = $symbol . substr($symbol_with_space, 0, strlen($symbol_with_space) - strlen($conf[1])); // 保留赋值符与值之间的空格
		} else { // 整行
			$symbol_with_space = '';
			$white_space_pos = strpos($conf, $whole_line); // 保留左侧空格符
			$white_space_pos = ($white_space_pos < 0) ? 0 : $white_space_pos;
			$conf = array(substr($conf, 0, $white_space_pos), $whole_line);
		}
		
		// 转换标签格式为配置项格式
		$conf[1] = $this->convert_tag_val_to_conf($tag, $conf[1], $this->set_data[$hash]);

		// 配置项格式不在指令允许格式范围内时不处理
		if ($conf[1] === false) {
			$this->set_fail++;
			return $matches[0];
		}

		$result = $matches[1] . implode($symbol_with_space, $conf) . $last_word . rtrim($right_space . $comment) . $matches[6];
		return $result;
	}
	
	/**
	 * 获取配置项对应的javascript变量类型
	 * @param string $val
	 * @return string boolean|string|number|array|object
	 */
	private function get_js_type($val) {
		$type = null;
		if (in_array($val, array('true', 'false'))) {
			$type = 'boolean';
		// } elseif (preg_match('/^"[^\r\n"]*"$/i', $val) || preg_match("/^'[^\r\n']*'$/i", $val)) {
		} elseif (preg_match('/^-?\d+(?:\.\d+)?$/', $val)) {
			$type = 'number';
		} elseif (preg_match('/^\[.*\]$/i', $val)) {
			$type = 'array';
		} elseif (preg_match('/^\{.*\}$/i', $val)) {
			$type = 'object';
		} else {
			$v = substr($val, 1, -1);
			if (preg_match('/^"[^\r\n]*"$/i', $val) && strpos(str_replace('\"', '', $v), '"') === false) { // 去掉转义符(\")后的双引号字符串是否还是合法的字符串
				$type = 'string';
			} elseif (preg_match("/^'[^\r\n]*'$/i", $val) && strpos(str_replace("\'", "", $v), "'") === false) { // 去掉转义符(\')后的单引号字符串是否还是合法的字符串
				$type = 'string';
			}
		}
		return $type;
	}

	/**
	 * 获取javascript string类型,判断是单引号还是双引号包含
	 * @param string $val
	 * @return string single|double|null
	 */
	private function get_js_string_type($val) {
		if (preg_match('/^"[^\r\n]*"$/i', $val)) {
			return 'double';
		} elseif (preg_match("/^'[^\r\n]*'$/i", $val)) {
			return 'single';
		}
		return null;
	}
	
	/**
	 * 格式化数组配置项中每项为标签所需格式(配置->标签)
	 * @param array $array
	 * @return array
	 */
	private function format_each_conf($array) {
		foreach ($array as $k => $v) {
			$array[$k] = trim($v);
			// 去除文本两侧单双引号
			$type = $this->get_js_type($array[$k]);
			if ($type == 'string') {
				$array[$k] = substr($array[$k], 1, -1);
			}
		}
		return $array;
	}
	/**
	 * 格式化标签中数组每项为配置所需格式(标签->配置)
	 * @param array $conf
	 * @param array $tag_val 标签值
	 * @return array
	 */
	private function format_each_tag($conf, $tag_val) {
		// [1,2] -> 1,2
		$conf = substr($conf, 1, -1);
		$conf = explode(',', $conf);
		
		// 如果俩数组长度相同,则每一项格式对应做严格匹配
		if (count($conf) == count($tag_val)) {
			foreach ($conf as $k => $v) {
				$v = trim($v);
				$tag_val[$k] = trim($tag_val[$k]);
				
				$type = $this->get_js_type($v);
				switch ($type) {
					case 'number':
						$tag_val[$k] = (float)$tag_val[$k];	
						break;
					case 'string':
						$tag_val[$k] = '"' . $tag_val[$k] . '"';		
						break;
					case 'boolean':
						$tag_val[$k] = (int)$tag_val[$k];
						$tag_val[$k] = ($tag_val[$k] == 1) ? 'true' : 'false';		
						break;
				}
			}
			
		// 否则只根据第一项格式来做匹配
		} else {
			$conf = trim($conf[0]);
			$type = $this->get_js_type($conf);
			
			foreach ($tag_val as $k => $v) {
				$tag_val[$k] = trim($tag_val[$k]);
				switch ($type) {
					case 'number':
						$tag_val[$k] = (float)$tag_val[$k];	
						break;
					case 'string':
						$tag_val[$k] = '"' . $tag_val[$k] . '"';	
						break;
					case 'boolean':
						$tag_val[$k] = (int)$tag_val[$k];
						$tag_val[$k] = ($tag_val[$k] == 1) ? 'true' : 'false';		
						break;
				}
			}
		}
		
		return $tag_val;
	}
	
	/**
	 * 为可视配置添加hash标识
	 * @return array|Exception
	 */
	public function hash() {
		// 添加hash时的指针偏移位置
		$this->hash_offset = 0;
		// 获取可视配置
		$tag = "(?:" . implode("|", array_keys($this->tag)) . ")";
		// 匹配单行注释
		$pattern = "/(\/\/ +@" . $tag . " +(?:[^\r\n]+))([\r\n])/";
		
		$result = preg_replace_callback($pattern, array(&$this, 'hash_callback'), $this->input);
		if ($result === false) {
			throw new Exception('Error:Hash Error');
		}
		return $result;
	}
	private function hash_callback($matches) {
		$command = $matches[1];
		
		// 已存在hash的不再处理
		if (strpos($command, 'hash="')) {
			return $matches[0];
		}
		
		// $position保证多个(同名)指令不重复
		// $time保证多次修改不重复
		$position = strpos($this->input, $command, $this->hash_offset);
		$time = time();
		$rand = rand();
		$command = $matches[1] . ' hash="v' . md5($position . $time . $rand) . '"' . $matches[2];
		
		// 保存当前项的偏移量,避免相同指令的position相同
		$this->hash_offset = $position+1;
		
		return $command;
	}
	
	
	/**
	 * 删除内容中的指令集
	 * @param string $str
	 * @return $string|Exception
	 */
	public function del($str) {
		$str = empty($str) ? $this->input : $str;
		// 获取可视配置
		$tag = "(?:" . implode("|", array_keys($this->tag)) . ")";
		// 匹配单行注释
//		$pattern2 = "/[\r\n]?.*\/\/ +@" . $tag . " +(?:[^\r\n]+)[\r\n]?/";
//		preg_match_all($pattern2, $str, $matches);
//		$result2 = preg_replace($pattern2, '', $str);
		
		$pattern = "/[\r\n]?.*\/\/ +@" . $tag . " +(?:[^\r\n]+)[\r\n]?/";
		$result = preg_replace($pattern, '', $str);
		
		if ($result === false) {
			throw new Exception('Error:Del Error');
		}
		return $result;
	}
}
