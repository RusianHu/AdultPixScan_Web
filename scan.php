<?php
// --- 配置 ---
// 从配置文件安全地读取配置
$config = parse_ini_file('config.ini');
if (!$config || !isset($config['api_key'])) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => '服务器配置错误：无法读取 API Key。']);
    exit;
}

// 读取配置
$openRouterApiKey = $config['api_key'];

// 读取模型配置（如果存在）
$modelName = $config['model_name'] ?? 'google/gemini-2.5-flash-preview';

// OpenRouter API 端点
$apiUrl = $config['api_endpoint'] ?? 'https://openrouter.ai/api/v1/chat/completions';
$apiUrlBackup = $config['api_endpoint_backup'] ?? 'https://api.openrouter.ai/api/v1/chat/completions';

// 读取代理配置（如果存在）
$useProxy = isset($config['use_proxy']) ? filter_var($config['use_proxy'], FILTER_VALIDATE_BOOLEAN) : true;
$proxyUrl = $config['proxy_url'] ?? 'http://127.0.0.1:10809';

// 读取IPv4/IPv6配置（如果存在）
$forceIPv4 = isset($config['force_ipv4']) ? filter_var($config['force_ipv4'], FILTER_VALIDATE_BOOLEAN) : false;

// 读取超时配置（如果存在）
$timeout = (int)($config['timeout'] ?? 180);
$connectTimeout = (int)($config['connect_timeout'] ?? 30);

// 读取图片备份配置（如果存在）
$enableImageBackup = isset($config['enable_image_backup']) ? filter_var($config['enable_image_backup'], FILTER_VALIDATE_BOOLEAN) : false;
$imageBackupDir = $config['image_backup_dir'] ?? 'image_backups';

// 设置 PHP 超时时间 (比 cURL 超时稍长)
set_time_limit(300); // 300 秒，增加超时时间
ini_set('default_socket_timeout', 180); // 设置 socket 超时时间

// --- 响应头 ---
header('Content-Type: application/json'); // 始终返回 JSON

// --- 请求方法检查 ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => '无效的请求方法。请使用 POST。']);
    exit;
}

// --- 文件上传处理 ---
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $errorMessage = '图片上传失败。';
    if (isset($_FILES['image']['error'])) {
        switch ($_FILES['image']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMessage .= ' 文件过大。';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMessage .= ' 文件仅部分上传。';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMessage .= ' 没有文件被上传。';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errorMessage .= ' 缺少临时文件夹。';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errorMessage .= ' 无法写入文件到磁盘。';
                break;
            case UPLOAD_ERR_EXTENSION:
                $errorMessage .= ' PHP 扩展导致上传停止。';
                break;
            default:
                $errorMessage .= ' 未知错误。代码: ' . $_FILES['image']['error'];
                break;
        }
    }
    http_response_code(400); // Bad Request
    echo json_encode(['error' => $errorMessage]);
    exit;
}

$uploadedFile = $_FILES['image'];
$imagePath = $uploadedFile['tmp_name'];
$imageMimeType = $uploadedFile['type']; // 获取 MIME 类型

//类型验证
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($imageMimeType, $allowedMimeTypes)) {
    http_response_code(400);
    echo json_encode(['error' => '无效的图片格式。仅支持 JPEG, PNG, WEBP, GIF。']);
    // 注意：上传后临时文件会被自动删除，无需手动 unlink
    exit;
}

//检查文件头
$fileSignatures = [
    'image/jpeg' => ["\xFF\xD8\xFF"],
    'image/png'  => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],
    'image/gif'  => ["GIF87a", "GIF89a"],
    'image/webp' => ["RIFF", "\x52\x49\x46\x46"]
];

// 读取文件头部
$fileContent = file_get_contents($imagePath, false, null, 0, 12);
if ($fileContent === false) {
    http_response_code(400);
    echo json_encode(['error' => '无法读取上传的文件内容。']);
    exit;
}

// 验证文件头
$validSignature = false;
if (isset($fileSignatures[$imageMimeType])) {
    foreach ($fileSignatures[$imageMimeType] as $signature) {
        if (strncmp($fileContent, $signature, strlen($signature)) === 0) {
            $validSignature = true;
            break;
        }
    }
}

if (!$validSignature) {
    http_response_code(400);
    echo json_encode(['error' => '文件内容与声明的图片类型不匹配。伪装的文件？？？？']);
    exit;
}

// --- 文件大小限制 (服务器端再次确认) ---
$maxFileSize = 10 * 1024 * 1024; // 10 MB
if ($uploadedFile['size'] > $maxFileSize) {
    http_response_code(400);
    echo json_encode(['error' => '图片文件过大，请上传小于 10MB 的图片。']);
    exit;
}

// --- 读取图片内容并进行 Base64 编码 ---
try {
    $imageData = file_get_contents($imagePath);
    if ($imageData === false) {
        throw new Exception('无法读取上传的图片文件。');
    }
    $imageBase64 = base64_encode($imageData);

    // --- 如果启用了图片备份功能，保存图片到备份目录 ---
    if ($enableImageBackup) {
        // 创建备份目录（如果不存在）
        if (!file_exists($imageBackupDir) && !is_dir($imageBackupDir)) {
            if (!mkdir($imageBackupDir, 0755, true)) {
                // 记录错误但不中断主流程
                file_put_contents('backup_error.log', date('Y-m-d H:i:s') . " - 无法创建备份目录: {$imageBackupDir}\n", FILE_APPEND);
            }
        }

        // 确保目录存在且可写
        if (is_dir($imageBackupDir) && is_writable($imageBackupDir)) {
            // 生成带有时间戳的唯一文件名
            $originalName = pathinfo($uploadedFile['name'], PATHINFO_FILENAME);
            // 强制使用安全的扩展名，不使用上传文件的原始扩展名
            $safeExtension = '';
            switch ($imageMimeType) {
                case 'image/jpeg':
                    $safeExtension = 'jpg';
                    break;
                case 'image/png':
                    $safeExtension = 'png';
                    break;
                case 'image/webp':
                    $safeExtension = 'webp';
                    break;
                case 'image/gif':
                    $safeExtension = 'gif';
                    break;
                default:
                    $safeExtension = 'bin'; // 未知类型使用安全的二进制扩展名
            }
            // 格式化文件名：原文件名_年月日_时分秒_随机数.安全扩展名
            $timestamp = date('Ymd_His');
            $randomStr = substr(md5(uniqid(mt_rand(), true)), 0, 6);
            $backupFileName = "{$originalName}_{$timestamp}_{$randomStr}.{$safeExtension}";
            $backupFilePath = "{$imageBackupDir}/{$backupFileName}";

            // 保存图片到备份目录
            if (!file_put_contents($backupFilePath, $imageData)) {
                // 记录错误但不中断主流程
                file_put_contents('backup_error.log', date('Y-m-d H:i:s') . " - 无法保存图片到备份目录: {$backupFilePath}\n", FILE_APPEND);
            } else {
                // 记录成功备份的信息
                file_put_contents('backup_success.log', date('Y-m-d H:i:s') . " - 成功备份图片: {$backupFilePath}\n", FILE_APPEND);
            }
        } else {
            // 记录错误但不中断主流程
            file_put_contents('backup_error.log', date('Y-m-d H:i:s') . " - 备份目录不存在或不可写: {$imageBackupDir}\n", FILE_APPEND);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => '处理图片时出错: ' . $e->getMessage()]);
    exit;
}
// 临时文件会在脚本结束时自动删除

// --- 准备 OpenRouter API 请求 ---

// 定义强制 JSON 输出的模式
$jsonSchema = [
    'type' => 'object',
    'properties' => [
        'is_adult_content' => [
            'type' => 'boolean',
            'description' => '图片是否包含成人或不适宜内容。'
        ],
        'probability' => [
            'type' => 'number',
            'format' => 'float',
            'minimum' => 0.0,
            'maximum' => 1.0,
            'description' => '图片被判断为成人内容的概率（0.0 到 1.0 之间）。'
        ],
        'reasoning' => [
            'type' => 'string',
            'description' => 'AI 判断的主要依据或观察到的元素（简洁说明）。'
        ],
        // 添加备用字段，以防模型使用这些字段
        'adult' => [
            'type' => 'boolean',
            'description' => '图片是否包含成人内容（备用字段）。'
        ],
        'violence' => [
            'type' => 'boolean',
            'description' => '图片是否包含暴力内容（备用字段）。'
        ]
    ],
    'required' => ['is_adult_content', 'probability', 'reasoning']
];

// 构建请求体 (遵循 OpenAI 格式)
$requestBody = [
    'model' => $modelName,
    'messages' => [
        [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'text',
                    // 更清晰的指令，强调内容安全分析和输出格式
                    'text' => '请分析这张图片的内容安全风险。判断它是否包含成人、暴力、或任何不适宜的内容。请严格按照提供的 JSON 模式输出结果，必须包含 is_adult_content（布尔值）、probability（0.0-1.0之间的数值）和 reasoning（字符串）三个字段。'
                ],
                [
                    'type' => 'image_url',
                    'image_url' => [
                        // 使用 base64 数据 URI
                        'url' => "data:{$imageMimeType};base64,{$imageBase64}"
                    ]
                ]
            ]
        ]
    ],
    'response_format' => [ // 强制 JSON 输出
        'type' => 'json_object',
        'schema' => $jsonSchema // 提供 schema 以规范输出
    ]
];

// --- 发送 cURL 请求 ---
// 尝试预先解析 DNS，以确保域名可以被解析
$dnsResolved = false;
if (function_exists('dns_get_record')) {
    $dnsRecords = @dns_get_record('openrouter.ai', DNS_A);
    $dnsResolved = !empty($dnsRecords);
    if ($dnsResolved) {
        $hostIp = $dnsRecords[0]['ip'] ?? '';
        // 记录 DNS 解析结果到日志
        file_put_contents('dns_check.log', date('Y-m-d H:i:s') . " - DNS resolved: openrouter.ai -> " . $hostIp . "\n", FILE_APPEND);
    } else {
        // 记录 DNS 解析失败到日志
        file_put_contents('dns_check.log', date('Y-m-d H:i:s') . " - DNS resolution failed for openrouter.ai\n", FILE_APPEND);
    }
}

// 定义一个函数来发送 cURL 请求
function sendCurlRequest($url, $requestBody, $openRouterApiKey, $useProxy, $proxyUrl, $forceIPv4, $timeout, $connectTimeout) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 返回响应而不是直接输出
    curl_setopt($ch, CURLOPT_POST, true); // 设置为 POST 请求
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody)); // 发送 JSON 数据
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $openRouterApiKey,
        'Content-Type: application/json',
        // 可选：添加引用站点信息，有助于 OpenRouter 追踪来源
        'HTTP-Referer: ' . ($YOUR_SITE_URL ?? 'http://localhost'), // 替换为你的网站 URL
        'X-Title: ' . ($YOUR_SITE_NAME ?? 'AdultPixScan'), // 替换为你的网站名称
        'User-Agent: AdultPixScan/1.0 PHP/' . PHP_VERSION, // 添加 User-Agent
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); // 使用配置文件中的超时时间
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout); // 使用配置文件中的连接超时时间
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 禁用 SSL 证书验证，仅用于测试环境
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // 禁用主机验证，仅用于测试环境
    curl_setopt($ch, CURLOPT_VERBOSE, true); // 启用详细信息输出
    curl_setopt($ch, CURLOPT_FAILONERROR, false); // 即使 HTTP 状态码指示错误也不要失败
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // 允许重定向
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // 最多允许 5 次重定向

    // 如果启用了代理，设置代理
    if ($useProxy && !empty($proxyUrl)) {
        curl_setopt($ch, CURLOPT_PROXY, $proxyUrl);
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
    }

    // 如果启用了强制IPv4，设置IPRESOLVE选项
    if ($forceIPv4) {
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    }

    // 创建一个临时文件来存储 cURL 详细信息
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $info = curl_getinfo($ch);

    // 获取详细的 cURL 调试信息
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);

    curl_close($ch);

    return [
        'response' => $response,
        'httpCode' => $httpCode,
        'curlError' => $curlError,
        'info' => $info,
        'verboseLog' => $verboseLog
    ];
}

// 首先尝试主 API 端点
$result = sendCurlRequest(
    $apiUrl,
    $requestBody,
    $openRouterApiKey,
    $useProxy,
    $proxyUrl,
    $forceIPv4,
    $timeout,
    $connectTimeout
);

// 如果主端点失败，尝试备用端点
if ($result['curlError'] || $result['response'] === false) {
    file_put_contents('api_error.log', date('Y-m-d H:i:s') . " - Primary API endpoint failed, trying backup endpoint\n", FILE_APPEND);

    $result = sendCurlRequest(
        $apiUrlBackup,
        $requestBody,
        $openRouterApiKey,
        $useProxy,
        $proxyUrl,
        $forceIPv4,
        $timeout,
        $connectTimeout
    );
}

// 提取结果
$response = $result['response'];
$httpCode = $result['httpCode'];
$curlError = $result['curlError'];
$info = $result['info'];
$verboseLog = $result['verboseLog'];

// --- 处理 cURL 错误 ---
if ($curlError || $response === false) {
    // verboseLog 已经在 sendCurlRequest 函数中获取

    // 记录详细的错误信息到日志文件
    $errorLog = date('Y-m-d H:i:s') . " - cURL Error: " . $curlError . "\n";
    $errorLog .= "HTTP Code: " . $httpCode . "\n";
    $errorLog .= "Total Time: " . ($info['total_time'] ?? 'unknown') . " seconds\n";
    $errorLog .= "Connect Time: " . ($info['connect_time'] ?? 'unknown') . " seconds\n";
    $errorLog .= "Name Lookup Time: " . ($info['namelookup_time'] ?? 'unknown') . " seconds\n";
    $errorLog .= "Redirect Time: " . ($info['redirect_time'] ?? 'unknown') . " seconds\n";
    $errorLog .= "Effective URL: " . ($info['url'] ?? 'unknown') . "\n";
    $errorLog .= "Primary IP: " . ($info['primary_ip'] ?? 'unknown') . "\n";
    $errorLog .= "Local IP: " . ($info['local_ip'] ?? 'unknown') . "\n";
    $errorLog .= "Verbose Log: " . $verboseLog . "\n";
    $errorLog .= "Request Body: " . json_encode($requestBody, JSON_PRETTY_PRINT) . "\n";
    $errorLog .= "Response: " . ($response ? $response : "Empty response") . "\n";
    $errorLog .= "-----------------------------------\n";

    // 将错误信息写入日志文件
    file_put_contents('api_error.log', $errorLog, FILE_APPEND);

    // 尝试进行 DNS 查询以验证连接性
    $dnsCheck = '';
    if (function_exists('dns_get_record')) {
        $dnsRecords = @dns_get_record('openrouter.ai', DNS_A);
        $dnsCheck = "DNS Check: " . (empty($dnsRecords) ? "Failed to resolve openrouter.ai" : "Successfully resolved openrouter.ai");
    }

    http_response_code(500); // Internal Server Error
    echo json_encode([
        'error' => '调用 AI 服务失败: cURL Error - ' . ($curlError ? $curlError : 'Empty reply from server'),
        'debug_info' => [
            'http_code' => $httpCode,
            'request_url' => $info['url'] ?? $apiUrl, // 使用实际请求的 URL
            'total_time' => $info['total_time'] ?? 'unknown',
            'connect_time' => $info['connect_time'] ?? 'unknown',
            'primary_ip' => $info['primary_ip'] ?? 'unknown',
            'dns_check' => $dnsCheck,
            'proxy_used' => $useProxy ? $proxyUrl : 'none',
            'force_ipv4' => $forceIPv4 ? 'true' : 'false',
            'verbose_log' => substr($verboseLog, 0, 500) // 限制日志大小
        ]
    ]);
    exit;
}

// --- 处理 API 响应 ---
if ($httpCode >= 400) {
    // 尝试解析错误响应体
    $errorData = json_decode($response, true);
    $apiErrorMessage = '调用 AI 服务时出错。';
    if ($errorData && isset($errorData['error']['message'])) {
        $apiErrorMessage .= ' 详情: ' . $errorData['error']['message'];
    } else {
        $apiErrorMessage .= ' HTTP 状态码: ' . $httpCode;
        // 包含部分响应体可能有助于调试
        $apiErrorMessage .= ' 响应体: ' . substr($response, 0, 200);
    }
    http_response_code($httpCode); // 返回 API 的错误码
    echo json_encode(['error' => $apiErrorMessage]);
    exit;
}

// --- 解析并验证 JSON 响应 ---
$responseData = json_decode($response, true);
if ($responseData === null || !isset($responseData['choices'][0]['message']['content'])) {
    http_response_code(500);
    echo json_encode(['error' => '无法解析 AI 返回的响应或响应格式不正确。']);
    exit;
}

// 提取 AI 返回的 JSON 字符串
$aiJsonOutput = $responseData['choices'][0]['message']['content'];

// 记录 AI 原始输出到日志，方便调试
file_put_contents('ai_response.log', date('Y-m-d H:i:s') . " - AI Raw Output: " . $aiJsonOutput . "\n", FILE_APPEND);

// 再次解码 AI 返回的 JSON 内容
$resultData = json_decode($aiJsonOutput, true);

// 验证解码后的数据是否符合预期结构
if ($resultData === null) {
    http_response_code(500);
    // 返回原始 AI 输出和错误信息，方便调试
    echo json_encode([
        'error' => 'AI 返回的数据格式无法解析为 JSON。',
        'ai_raw_output' => $aiJsonOutput // 将原始输出包含在错误中
    ]);
    exit;
}

// 检查是否是预期的格式
$isExpectedFormat = isset($resultData['is_adult_content']) && is_bool($resultData['is_adult_content']) &&
                    isset($resultData['probability']) && is_numeric($resultData['probability']) &&
                    isset($resultData['reasoning']) && is_string($resultData['reasoning']);

// 检查是否是 {"adult":false,"violence":false} 格式
$isAlternateFormat = isset($resultData['adult']) && is_bool($resultData['adult']) &&
                     isset($resultData['violence']) && is_bool($resultData['violence']);

// 如果不是预期格式，但是替代格式，则进行转换
if (!$isExpectedFormat && $isAlternateFormat) {
    // 转换为预期格式
    $convertedData = [
        'is_adult_content' => $resultData['adult'],
        'probability' => $resultData['adult'] ? 0.9 : 0.1, // 根据 adult 字段设置概率
        'reasoning' => $resultData['adult']
            ? '图片被判断为包含成人内容。' . ($resultData['violence'] ? '同时包含暴力内容。' : '')
            : '图片被判断为不包含成人内容。' . ($resultData['violence'] ? '但包含暴力内容。' : '内容安全。')
    ];
    $resultData = $convertedData;
}
// 如果既不是预期格式也不是替代格式，则报错
else if (!$isExpectedFormat) {
    http_response_code(500);
    // 返回原始 AI 输出和错误信息，方便调试
    echo json_encode([
        'error' => 'AI 返回的数据格式不符合预期。请检查模型输出或 JSON Schema。',
        'ai_raw_output' => $aiJsonOutput // 将原始输出包含在错误中
    ]);
    exit;
}

// --- 成功返回结果 ---
http_response_code(200);
echo json_encode(['result' => $resultData]);
exit;

?>