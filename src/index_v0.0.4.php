<?php
/* nginx 配置示例：
 * location / {
 *     # ……(其他配置选项，略，添加以下指令：路由重写)
 *     rewrite ^(.*)$ /Start.php last;
 * }
 */

$Response = new \stdClass;
$Response->code = 0;
$Response->msg = 'OK';

// 获取请求地址并转为数组
$url = trim($_SERVER['REQUEST_URI'], '/');
$index = strpos($url, '?');
$pathUrl = ($index > -1) ? substr($url, 0, $index) : $url;
$match = explode('/', $pathUrl);
$match = array_filter($match);

// 处理请求，首先确定控制器类的名称
$errClass = 'Error';
if (file_exists('Setting.json')) {
    $Setting = json_decode(file_get_contents('Setting.json'));
    if (isset($Setting->Error)) { $errClass = $Setting->Error; }
}
$ClassName = isset($Setting->Controller)? $Setting->Controller : 'Index';
if (!empty($match)) {
    $ClassName = array_shift($match);
}
$ClassName = ucfirst($ClassName);
if ($ClassName == basename(__FILE__)) {
    $Response->code = 403;
    $Response->msg = 'Class is same as this file!';
    $ClassName = $errClass;
}
// 根据控制器名称查找文件
$file = $ClassName . '.php';
if (!file_exists($file)) {
    if (isset($Setting->Path)) {
        foreach ($Setting->Path as $path) {
            $file = $path. '\\' . $ClassName . '.php';
            if (file_exists($file)) break;
        }
    }
}
if (!file_exists($file)) {
    $Response->code = 404;
    $Response->msg = "File not found: ".$file;
} else {
    include($file); // 文件存在就加载它，然后查找控制器类
    if (!class_exists($ClassName)) {
        if (isset($Setting->Namespace)) {
            foreach ($Setting->Namespace as $namespace) {
                if (class_exists($namespace. '\\' . $ClassName)) {
                    $ClassName = $namespace. '\\' . $ClassName;
                    break;
                }
            }
        }
    }
    if (!class_exists($ClassName)) {
        $Response->code = 404;
        $Response->msg = "Class not found: ".$ClassName;
    } else {
        // 确定http请求的方法，及其对应的控制器方法名称
        $method = strtolower($_SERVER['REQUEST_METHOD']);
        if ($method == 'patch') { $method = 'put'; }
        $action = $method = ucfirst($method);
        if (isset($Setting->Action)) {
            if (isset($Setting->Action->$method)) {
                $action = ucfirst($Setting->Action->$method);
            }
        }
        $controller = new $ClassName(null);
        if (!method_exists($controller, $action)) {
            $Response->code = 404;
            $Response->msg = "Method not found: ".$action;
        } else {
            // 检查访问权限
            $perm = true;
            if (isset($Setting->Permission)) {
                if (isset($Setting->Permission->$method)) {
                    $perm = $Setting->Permission->$method;
                }
            }
            $option = 'Permission';
            if (isset($Setting->Permission->Option)) {
                $option = $Setting->Permission->Option;
            }
            if (method_exists($controller, $option)) {
                $token = isset($_SERVER['HTTP_AUTHORIZATION'])? $_SERVER['HTTP_AUTHORIZATION'] : '';
                $ctlperm = $controller->$option($token, $method);
                if (isset($ctlperm->$method)) {
                    $perm = $ctlperm->$method;
                }
            }

            if (!$perm) {
                $Response->code = 405;
                $Response->msg = 'Method Not Allowed';
            } else { // 允许访问
                // 准备数据，作为访问参数传递给方法
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
                switch ($method) { // 根据http请求的方法传递相应的参数
                case 'Get':
                    if (method_exists($controller, 'Count')) {
                        $Response->count = $controller->Count($_GET);
                    }
                    $Response->data = $controller->$action($_GET); break;
                case 'Post':
                    $Response->msg = $controller->$action($_POST); break;
                case 'Put':
                    $Response->msg = $controller->$action($_GET, $_POST); break;
                case 'Delete':
                    $Response->msg = $controller->$action($_GET); break;
                default:
                    $Response->code = 501;
                    $Response->msg = 'Not Implemented';
                }
            }
        }
    }
}

// 输出处理结果
$Response->timestamp = time();
header('Content-Type:application/json; charset=utf-8');
echo \json_encode($Response);
