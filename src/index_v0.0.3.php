<?php
/* nginx 配置示例：
 * location / {
 *     # ……(其他配置选项，略，添加以下指令：路由重写)
 *     rewrite ^(.*)$ /index.php last;
 * }
 */

// 以 JSON 格式输出异常消息
function exhandle($ex) {
    $exp = new \stdClass;
    $exp->code = $ex->getCode();
    $exp->msg = $ex->getMessage();
    $exp->file = $ex->getFile();
    $exp->line = $ex->getLine();
    $exp->trace = $ex->getTrace();
    header('Content-Type:application/json; charset=utf-8');
    echo \json_encode($exp);
}
set_exception_handler('exhandle');

$Response = new \stdClass;
$Response->code = 0;
$Response->msg = 'OK';

// 获取请求地址
$url = trim($_SERVER['REQUEST_URI'], '/');
$index = strpos($url, '?');
$pathUrl = ($index > -1) ? substr($url, 0, $index) : $url;

// 处理请求，首先从URL中确定访问的类的名称
$match = explode('/', $pathUrl);
$match = array_filter($match);
$ClassName = 'Index';
if (!empty($match)) {
    $ClassName = ucfirst(array_shift($match));
}
$file = '';
$Setting = json_decode(file_get_contents('Setting.json'));
foreach ($Setting->Path as $path) {
    $file = $path. '\\' . $ClassName . '.php';
    if (file_exists($file)) break;
}
if (!file_exists($file)) {
    $Response->code = 404;
    $Response->msg = "File not found: ".$file;
} else {
    include($file); // 可以编写其他函数预先加载所需文件
    if (!class_exists($ClassName)) {
        foreach ($Setting->NameSpace as $namespace) {
            if (class_exists($namespace. '\\' . $ClassName)) {
                $ClassName = $namespace. '\\' . $ClassName;
                break;
            }
        }
    }
    if (!class_exists($ClassName)) {
        $Response->code = 404;
        $Response->msg = "Class not found: ".$ClassName;
    } else {
        $controller = new $ClassName(null);
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtolower($_SERVER['REQUEST_METHOD']): 'get';
        $action = $Setting->Action->$method;
        // 检查访问权限
        $perm = $Setting->Permission->$method;
        $option = $Setting->Permission->option;
        if (method_exists($controller, $option)) {
            $token = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
            $perm = $controller->$option($token, $method);
        }
        if (!$perm) {
            $Response->code = 401;
            $Response->msg = 'Unauthorized';
        } else {
            if (!empty($match)) {
                $_GET = array_merge($_GET, $match);
            }
            if (isset($_SERVER['CONTENT_TYPE'])) {
                if (strtolower($_SERVER['CONTENT_TYPE']) != 'multipart/form-data') {
                    $body = \json_decode(file_get_contents("php://input"), true);
                    if (!empty($body)) {
                        $_POST = array_merge($_POST, $body);
                    }
                }
            }
            if (!method_exists($controller, $action)) {
                $Response->code = 404;
                $Response->msg = "Method not found: ".$action;
            } else {
                switch ($method) {
                case 'post':
                    $Response->msg = $controller->$action($_POST); break;
                case 'put':
                case 'patch':
                    $Response->msg = $controller->$action($_GET, $_POST); break;
                case 'delete':
                    $Response->msg = $controller->$action($_GET); break;
                case 'get':
                    if (method_exists($controller, 'Count')) {
                        $Response->count = $controller->Count($_GET);
                    }
                    $Response->data = $controller->$action($_GET);
                }
            }
        }
    }
}

// 输出处理结果
$Response->timestamp = time();
header('Content-Type:application/json; charset=utf-8');
echo \json_encode($Response);
