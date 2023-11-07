<?php
/* nginx 配置示例：
 * location / {
 *     # ……(其他配置选项，略，添加以下指令：路由重写)
 *     rewrite ^(.*)$ /index.php last;
 * }
 */
$ret = new \stdClass;
$ret->code = 0;
$ret->msg = 'OK';

// 获取请求地址
$url = trim($_SERVER['REQUEST_URI'], '/');
$index = strpos($url, '?');
$pathUrl = ($index > -1) ? substr($url, 0, $index) : $url;

// 处理请求
$match = explode('/', $pathUrl);
$match = array_filter($match);
if (empty($match)) {
    $match = ['Index']; // 默认的类
    $action = 'Index';  // 默认方法
} elseif (count($match) < 2) {
    $action = 'Index';  // 默认方法
} else {
    $action = array_pop($match);
}

// 控制器存放的目录
$controllerName = 'Controllers\\';
foreach ($match as $namespace) {
    $controllerName .= ucfirst($namespace).'\\';
}
$controllerName = rtrim($controllerName,'\\');
if (!file_exists($controllerName.'.php')) {
    $ret->code = 404;
    $ret->msg = "File not found: ".$controllerName.'.php';
} else {
    include($controllerName.'.php');
    if (!class_exists($controllerName)) {
        $ret->code = 404;
        $ret->msg = "controller not found: ".$controllerName;
    } else {
        $controller = new $controllerName;
        if (!method_exists($controller, $action)) {
            $ret->code = 404;
            $ret->msg = "method not found: ".$action;
        } else {
            // 将get和post注入控制器方法中
            $ret->data = $controller->$action(array_merge($_GET, $_POST));
        }
    }
}
// 输出处理结果
$ret->timestamp = time();
header('Content-Type:application/json; charset=utf-8');
echo \json_encode($ret);
?>
