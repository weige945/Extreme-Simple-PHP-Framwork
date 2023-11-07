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
$ClassName = 'Index';
$match = explode('/', $pathUrl);
$match = array_filter($match);
if (!empty($match)) {
    $ClassName = ucfirst(array_shift($match));
}
$file = 'Controllers\\' . $ClassName . '.php';
if (!file_exists($file)) {
    $file = 'API\\' . $ClassName . '.php';
}
if (!file_exists($file)) {
    $ret->code = 404;
    $ret->msg = "File not found: ".$file;
} else {
    include($file); // 可以编写其他函数预先加载所需文件
    if (!class_exists($ClassName)) {
        $ClassName = 'Controllers\\' . $ClassName;
    }
    if (!class_exists($ClassName)) {
        $ret->code = 404;
        $ret->msg = "Class not found: ".$ClassName;
    } else {
        $controller = new $ClassName;
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']): 'GET';
        // 检查访问权限
        $perm = false; // 默认不允许访问，可改为默认允许访问
        if (method_exists($controller, 'Permissions')) {
            $token = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
            $perm = $controller->Permissions($token, $method);
        }
        if (!$perm) {
            $ret->code = 403;
            $ret->msg = 'Forbidden';
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
            $action = 'Index'; // or 'Get'/'Select'
            switch ($method) {
                case 'POST': $action = 'Insert'; break;
                case 'PUT':
                case 'PATCH': $action = 'Update'; break; // or 'Set'
                case 'DELETE': $action = 'Delete';
            }
            if (!method_exists($controller, $action)) {
                $ret->code = 404;
                $ret->msg = "Method not found: ".$action;
            } else {
                switch ($method) {
                case 'POST':
                    $ret->msg = $controller->$action($_POST); break;
                case 'PUT':
                case 'PATCH':
                    $ret->msg = $controller->$action($_GET, $_POST); break;
                case 'DELETE':
                    $ret->msg = $controller->$action($_GET); break;
                case 'GET':
                    if (method_exists($controller, 'Count')) {
                        $ret->count = $controller->Count($_GET);
                    }
                    $ret->data = $controller->$action($_GET);
                }
            }
        }
    }
}

// 输出处理结果
$ret->timestamp = time();
header('Content-Type:application/json; charset=utf-8');
echo \json_encode($ret);
?>
