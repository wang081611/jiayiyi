<?php
/**
 * 开奖预测系统 - 服务端版本
 * 放到服务器后，使用 cronjob 或后台运行:
 * crontab -e
 * */30 * * * * /usr/bin/php /path/to/server.php
 */

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// API配置
define('API_URL', 'https://super.pc28998.com/history/JND28');
define('DATA_FILE', __DIR__ . '/lottery_data.json');
define('LOG_FILE', __DIR__ . '/lottery.log');

// 记录日志
function logMsg($msg) {
    $time = date('Y-m-d H:i:s');
    file_put_contents(LOG_FILE, "[$time] $msg\n", FILE_APPEND);
}

// 获取数据
function fetchData() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $json = json_decode($response, true);
        if ($json && $json['code'] === 1 && !empty($json['data'])) {
            return $json['data'];
        }
    }
    return null;
}

// 解析开奖号码
function parseNums($opencode) {
    if (empty($opencode)) return [];
    return array_map('intval', explode(',', $opencode));
}

// 计算和值
function calcSum($nums) {
    return array_sum($nums);
}

// 获取大小单双
function getTag($sum) {
    $size = $sum >= 14 ? '大' : '小';
    $parity = $sum % 2 === 0 ? '双' : '单';
    return $size . '+' . $parity;
}

// 趋势分析
function analyzeTrend($history, $count = 15) {
    $recent = array_slice($history, 0, $count);
    $sums = array_map(function($item) { return $item['sum']; }, $recent);

    $trend = 0;
    for ($i = 0; $i < count($sums) - 1; $i++) {
        $trend += $sums[$i + 1] - $sums[$i];
    }
    $trend = $trend / (count($sums) - 1);

    $avg = array_sum($sums) / count($sums);
    $lastSum = $sums[0];
    $predictedSum = round($lastSum + $trend * 0.7);
    $predictedSum = max(3, min(24, $predictedSum));

    return [
        'name' => '趋势',
        'sum' => $predictedSum,
        'tag' => getTag($predictedSum),
        'weight' => 0.25,
        'details' => "均值{$avg},趋势" . ($trend > 0 ? '↑' : '↓') . abs(round($trend, 2))
    ];
}

// 位置均值分析
function analyzePositionAvg($history, $count = 20) {
    $recent = array_slice($history, 0, $count);
    $p1 = $p2 = $p3 = 0;

    foreach ($recent as $item) {
        $nums = $item['nums'];
        if (count($nums) >= 3) {
            $p1 += $nums[0];
            $p2 += $nums[1];
            $p3 += $nums[2];
        }
    }

    $n1 = round($p1 / $count) % 10;
    $n2 = round($p2 / $count) % 10;
    $n3 = round($p3 / $count) % 10;
    $sum = $n1 + $n2 + $n3;

    return [
        'name' => '均值',
        'sum' => $sum,
        'nums' => [$n1, $n2, $n3],
        'tag' => getTag($sum),
        'weight' => 0.2
    ];
}

// 频率分析
function analyzeFrequency($history, $count = 10) {
    $recent = array_slice($history, 0, $count);
    $freq = array_fill(0, 10, 0);

    foreach ($recent as $item) {
        foreach ($item['nums'] as $n) {
            $freq[$n]++;
        }
    }

    $sorted = [];
    foreach ($freq as $n => $c) {
        $sorted[] = ['n' => $n, 'c' => $c];
    }
    usort($sorted, function($a, $b) { return $b['c'] - $a['c']; });

    $nums = [$sorted[0]['n'], $sorted[1]['n'], $sorted[2]['n']];
    $sum = array_sum($nums);

    return [
        'name' => '频率',
        'sum' => $sum,
        'nums' => $nums,
        'tag' => getTag($sum),
        'weight' => 0.2,
        'details' => "热门: {$sorted[0]['n']}({$sorted[0]['c']}次)"
    ];
}

// 遗漏分析
function analyzeMissing($history, $count = 15) {
    $recent = array_slice($history, 0, $count);
    $lastSeen = array_fill(0, 10, 999);

    foreach ($recent as $idx => $item) {
        foreach ($item['nums'] as $n) {
            if ($lastSeen[$n] === 999) {
                $lastSeen[$n] = $idx;
            }
        }
    }

    $missing = [];
    foreach ($lastSeen as $n => $m) {
        $missing[] = ['n' => $n, 'm' => $m];
    }
    usort($missing, function($a, $b) { return $b['m'] - $a['m']; });

    $nums = [$missing[0]['n'], $missing[1]['n'], $missing[2]['n']];
    $sum = array_sum($nums);

    return [
        'name' => '遗漏',
        'sum' => $sum,
        'nums' => $nums,
        'tag' => getTag($sum),
        'weight' => 0.15,
        'details' => "冷号: {$missing[0]['n']}(漏{$missing[0]['m']}期)"
    ];
}

// 从和值生成数字组合
function generateFromSum($target) {
    for ($a = 0; $a < 10; $a++) {
        for ($b = 0; $b < 10; $b++) {
            $c = $target - $a - $b;
            if ($c >= 0 && $c <= 9) {
                return [$a, $b, $c];
            }
        }
    }
    $mid = floor($target / 3);
    return [$mid, $mid, $target - 2 * $mid];
}

// 加权预测
function weightedPrediction($predictions) {
    $totalWeight = 0;
    $weightedSum = 0;

    foreach ($predictions as $p) {
        $weightedSum += $p['sum'] * $p['weight'];
        $totalWeight += $p['weight'];
    }

    $avgSum = round($weightedSum / $totalWeight);
    $nums = generateFromSum($avgSum);
    $sum = array_sum($nums);

    return [
        'nums' => $nums,
        'sum' => $sum,
        'tag' => getTag($sum)
    ];
}

// 主程序
function main() {
    logMsg("开始执行...");

    $data = fetchData();
    if (!$data) {
        logMsg("获取数据失败");
        exit(1);
    }

    // 处理数据
    $history = [];
    foreach (array_slice($data, 0, 100) as $item) {
        if (empty($item['opencode'])) continue;
        $nums = parseNums($item['opencode']);
        if (count($nums) < 3) continue;

        $history[] = [
            'expect' => $item['expect'],
            'opentime' => $item['opentime'],
            'nums' => $nums,
            'sum' => calcSum($nums)
        ];
    }

    if (count($history) < 10) {
        logMsg("数据不足");
        exit(1);
    }

    // 分析预测
    $predictions = [];
    $predictions[] = analyzeTrend($history);
    $predictions[] = analyzePositionAvg($history);
    $predictions[] = analyzeFrequency($history);
    $predictions[] = analyzeMissing($history);

    $result = weightedPrediction($predictions);

    // 获取最新一期信息
    $latest = $history[0];
    $nextExpect = intval($latest['expect']) + 1;

    // 输出结果
    $output = [
        'time' => date('Y-m-d H:i:s'),
        'latest' => [
            'expect' => $latest['expect'],
            'nums' => $latest['nums'],
            'sum' => $latest['sum'],
            'tag' => getTag($latest['sum']),
            'opentime' => $latest['opentime']
        ],
        'prediction' => [
            'expect' => $nextExpect,
            'nums' => $result['nums'],
            'sum' => $result['sum'],
            'tag' => $result['tag']
        ],
        'analysis' => $predictions
    ];

    // 保存数据
    file_put_contents(DATA_FILE, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    logMsg("最新: {$latest['expect']}期 {$latest['nums'][0]}+{$latest['nums'][1]}+{$latest['nums'][2]}={$latest['sum']}");
    logMsg("预测: {$nextExpect}期 {$result['nums'][0]}+{$result['nums'][1]}+{$result['nums'][2]}={$result['sum']} [{$result['tag']}]");

    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

// 后台持续运行模式
function runDaemon($interval = 60) {
    echo "启动守护进程模式，每{$interval}秒更新一次...\n";
    echo "按 Ctrl+C 停止\n\n";

    while (true) {
        main();
        echo "\n等待 {$interval} 秒...\n\n";
        sleep($interval);
    }
}

// CLI模式判断
if (php_sapi_name() === 'cli') {
    if (isset($argv[1]) && $argv[1] === '--daemon') {
        $interval = isset($argv[2]) ? intval($argv[2]) : 60;
        runDaemon($interval);
    } else {
        main();
    }
} else {
    // Web访问模式
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    main();
}
