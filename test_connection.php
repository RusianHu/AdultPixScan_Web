<?php
// 测试与 OpenRouter API 的连接
header('Content-Type: text/html; charset=utf-8');

// 从配置文件读取配置
$config = parse_ini_file('config.ini');
if (!$config || !isset($config['api_key'])) {
    die('错误：无法读取配置文件或 API Key 未设置。');
}

$apiKey = $config['api_key'];
$apiUrl = 'https://openrouter.ai/api/v1/models'; // 使用 models 端点进行简单测试

echo "<h1>OpenRouter API 连接测试</h1>";
echo "<p>测试时间: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>API URL: {$apiUrl}</p>";
echo "<p>API Key: " . substr($apiKey, 0, 10) . "..." . substr($apiKey, -5) . "</p>";

// 初始化 cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
]);

// 设置超时
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

// 禁用 SSL 验证（仅用于测试）
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

// 启用详细信息输出
curl_setopt($ch, CURLOPT_VERBOSE, true);
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

// 执行请求
$startTime = microtime(true);
$response = curl_exec($ch);
$endTime = microtime(true);
$executionTime = round($endTime - $startTime, 2);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

// 获取详细日志
rewind($verbose);
$verboseLog = stream_get_contents($verbose);

echo "<h2>测试结果</h2>";
echo "<p>HTTP 状态码: {$httpCode}</p>";
echo "<p>执行时间: {$executionTime} 秒</p>";

if ($curlError) {
    echo "<div style='color: red; background-color: #ffeeee; padding: 10px; border: 1px solid #ffaaaa;'>";
    echo "<h3>错误</h3>";
    echo "<p>{$curlError}</p>";
    echo "</div>";
} else {
    echo "<div style='color: green; background-color: #eeffee; padding: 10px; border: 1px solid #aaffaa;'>";
    echo "<h3>连接成功</h3>";
    echo "</div>";

    // 解析并显示可用模型
    $data = json_decode($response, true);
    if ($data && isset($data['data']) && is_array($data['data'])) {
        echo "<h3>可用模型列表</h3>";
        echo "<ul>";
        foreach ($data['data'] as $model) {
            if (isset($model['id'])) {
                echo "<li>{$model['id']}</li>";
            }
        }
        echo "</ul>";
    } else {
        echo "<p>无法解析模型列表或没有可用模型。</p>";
    }
}

echo "<h3>详细日志</h3>";
echo "<pre style='background-color: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px;'>";
echo htmlspecialchars($verboseLog);
echo "</pre>";

echo "<h3>原始响应</h3>";
echo "<pre style='background-color: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px;'>";
echo htmlspecialchars($response);
echo "</pre>";

curl_close($ch);
?>

<p><a href="index.html">返回主页</a></p>
