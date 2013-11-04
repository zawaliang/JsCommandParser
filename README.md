JsCommandParser 1.0.1
===============

通过自定义指令,自动寻址并提取javascript中的内容  
此类仅仅是提取配置内容,UI控件层面需要另行开发,可以使用jQuery UI搭建自己的UI控件层.


[使用须知]
---------------------
可视配置指令使用Javascript单行注释格式接入，一个配置项对应一条指令，一条指令占一行，指令行的下一行为配置项。格式如下：  
```
// @tag  attribute1="value2"  attribute2="value2"  ...  attributeN="valueN"  
```
此处紧跟配置项（javascript语句）  
指令录入后，系统将自动为每条指令分配唯一的hash标识，此标识用于指令寻址使用。如下：  
```
// @colorpicker label="colorpicker" hash="v0ae5d076619e398a3af08a8cf1fea98d"  
```
tag为系统支持的指令标签，每条指令拥有对应的属性以及对应的Javascript变量类型适配范围  
配置项后支持行内注释，但仅限"//xxx"的格式，不支持"/*xxx*/"的格式  


[使用]
----------------------
```
// 实例化JsCommandParser类  
$cp = new JsCommandParser($str);  
//  设置当前用户,用户受限访问控制  
$cp->user = 'xxx';  
// 调用parse方法解析指令  
$cp->parse();  
// 给内容添加hash寻址指令  
$cp->hash();  
// 通过hash表单集合设置指令对应配置项  
$cp->set($data);  
// 删除内容中的指令集  
$cp->del([$str]);  
```

[支持的指令集]
----------------------
toggle、radio、checkbox、select、input、textarea、date、range、step、colorpicker 
   
   
[指令与Javascript类型转换]
------------------------
输出时，系统会根据指令对应的配置项所属Javascript类型自动转换。如下：  
  	
// 系统自动根据ABC所对应的Javascript类型,自动转换指令值为所需类型
```
var demo = {
	// @toggle label="开关A" text="是否开启"
	A: true,
	// @toggle label="开关B" text="是否开启"
	B: 1,
	// @toggle label="开关C" text="是否开启"
	C: "1"
};
```


[用户访问控制]
-------------------------
针对多场景混合使用的权限控制问题，指令支持设定授权访问用户，添加形如 access="zawaliang;xxx" 的属性即可，多个用户以分号分割。
系统在指令解析以及数据写入时均会加以过滤控制。


[可视配置指令列表]
-------------------------------------------------------------------
toggle：开关指令
    格式：

    // @toggle group="分组名" label="开关指令" text="是否开启"
    var demo = true;

    属性：
        label: 标签头
        text: 开关提示
        group: 分组名（可选）
        access: 用户访问控制，多个用户以分号分割（可选）
    支持的Javascript类型：
        Boolean: true/false
        Int: 1/0
        String: "1"/"0" or '1'/'0'
        
        
radio：单选指令
    格式：

    // @radio group="分组名" label="单选指令" text="R1,R2,R3" value="1.10,2.00,3.10"
    var demo = 1.10;

    属性：
        label: 标签头
        text: 文本列表，逗号分割，与value一一对应
        value: 值列表，逗号分割，与text一一对应
        group: 分组名（可选）
        access: 用户访问控制，多个用户以分号分割（可选）
    支持的Javascript类型：
        Number
        String


checkbox：复选指令
    格式：

    var demo = {
      // @checkbox group="分组名" label="复选" text="C1,C2,C3,C4,C5" value="1,2,3,4,5" 
    	A: [2, 5]
    };

    属性：
        label: 标签头
        text: 文本列表，逗号分割，与value一一对应
        value: 值列表，逗号分割，与text一一对应
        group: 分组名（可选）
        access: 用户访问控制，多个用户以分号分割（可选）
    支持的Javascript类型：
        Array： 元数据支持Number与String类型，支持混淆使用，但系统输出时会格式化为首个元数据类型， Eg: [2, '5']->[2, 5]


select：下拉指令
    格式：

    // 格式一:
    // @select group="分组名" label="select" text="S1,S2,S3" value="1,2,3" 
    var demo = 2;

    // 格式二:
    // @select group="分组名" label="select" text="select" min="1" max="5" 
    var demo = 3;

    属性：
        label: 标签头
        text: 文本列表，逗号分割，与value一一对应
        value: 值列表，逗号分割，与text一一对应（格式一时提供）
        min: 范围区间最小值（格式二时提供）
        max: 范围区间最大值（格式二时提供）
        group: 分组名（可选）
        access: 用户访问控制，多个用户以分号分割（可选）
    支持的Javascript类型：
        Number
        String
        
        
input：单行输入框指令
    格式：

    // @input group="分组名" label="单行输入框指令" placeholder="输入框提示" maxlength="10" 
    var demo = 123.456;

    属性：
        label: 标签头
        placeholder: 输入框提示
        maxlength: 长度限制（可选）
        group: 分组名（可选）
        access: 用户访问控制，多个用户以分号分割（可选）
    支持的Javascript类型：
        Number: 自动校验数据类型
        String
    注意：

    在可视编辑模式下，当代码以单引号定义且输入内容含单引号或代码以双引号定义且输入内容含双引号，则自动被转义，以防止文件生成时出现语法错误

    var demo = '<a href="xxx" onclick="fn('args');">demo</a>'; 

    // 以上代码会被转义为以下代码

    var demo = '<a href="xxx" onclick="fn(\'args\');">demo</a>';
    
textarea：多行输入框指令

    格式：

    // @textarea group="分组名" label="多行输入框指令" placeholder="最多支持100个字符" maxlength="100" 
    var demo = '我是一段很长的文字';

    属性：
        label: 标签头
        placeholder: 输入框提示
        maxlength: 长度限制（可选）
        group: 分组名（可选）
        access: 用户访问控制，多个用户以分号分割（可选）
    支持的Javascript类型：
        String
    注意：

        在可视编辑模式下，回车换行符会被转义为空，以防止文件生成时出现语法错误

        在可视编辑模式下，当代码以单引号定义且输入内容含单引号或代码以双引号定义且输入内容含双引号，则自动被转义，以防止文件生成时出现语法错误

        var demo = '<a href="xxx" onclick="fn('args');">demo</a>'; 

        // 以上代码会被转义为以下代码

        var demo = '<a href="xxx" onclick="fn(\'args\');">demo</a>';
      
      
date：日期时间指令
    格式：

    // 格式一: 日期(时间)
    // @date group="分组名" label="date" start="1987" end="2013"
    var demo = "2012-11-23 11:52:25";
    var demo = "2012-11-23";

    // 格式二: 时间
    // @date group="分组名" label="date" 
    var demo = "11:52:25";

    // 格式三: 值允许空
    // @date group="分组名" label="date" type="time" 
    var demo = "";

    属性：
        label: 标签头
        start: 开始年份（格式一时提供，可选）
        end: 结束年份（格式一时提供，可选）
        type: 强制声明指令格式，适用于值允许空的情况（格式三时提供，可选）；date(日期)、time(时间)、both(日期与时间)
        group: 分组名（可选）
        access: 用户访问控制，多个用户以分号分割（可选）
    支持的Javascript类型：
        String: "YYYY-MM-DD hh:mm:ss" 、 "YYYY-MM-DD"、"hh:mm:ss"


range：区间指令
    格式

    // 格式一：
    // @range group="分组名" label="区间指令：最大值固定" type="max" min="0" max="10" step="2" 
    var demo = [6, 10];

    // 格式二：
    // @range group="分组名" label="区间指令：最小值固定" type="min" min="2" max="52" 
    var demo = ["2", "27"];

    // 格式三：
    // @range group="分组名" label="区间指令：最大最小值可调" type="range" min="0" max="150"
    var demo = [10, 30];

    属性：
        label: 标签头
        type: 类型；max(最大值固定)、min(最小值固定)、range(最大最小值可调)
        min: 区间最小值
        max: 区间最大值
        step: 步进，缺省为1（可选）
        group: 分组名（可选）
        access: 用户访问控制，多个用户以分号分割（可选）
    支持的Javascript类型：
        Array： 元数据支持Int与String类型


step：步进指令
    格式：

    // @step group="分组名" label="步进指令" min="0" max="150" step="10"
    var demo = 50;

    属性：
        label: 标签头
        min: 最小值
        max: 最大值
        step: 步进，缺省为1（可选）
        group: 分组名（可选）
        access: 用户访问控制，多个用户以分号分割（可选）
    支持的Javascript类型：
        Int
        String


colorpicker：颜色指令
    格式：

    // @colorpicker group="分组名" label="颜色指令"
    var demo = "#9F4981";

    属性：
        label: 标签头
        group: 分组名（可选）
        access: 用户访问控制，多个用户以分号分割（可选）
    支持的Javascript类型：
        String: 16进制颜色值，含#号
