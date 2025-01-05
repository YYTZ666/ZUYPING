<?php

namespace Kers;

use Kers\Utils;
use xPaw\MinecraftPing;
use xPaw\MinecraftPingException;

require __DIR__ . '/utils.class.php';

$Utils = new Utils();

header("Access-Control-Allow-Origin: *");
header('Content-type: application/json');
error_reporting(0);

$api_version = 'v0.5.76';

// Default server IP and port
$default_ip = 'zuycraft.zuyst.top';  // Replace with your default server IP
$default_port = '37581';            // Replace with your default server port

// 获取当前运行脚本的服务器的IP地址
$api_nodeIP = $_REQUEST['get_nodeip'] ? $_SERVER['SERVER_ADDR'] : 'N/A';

$array = [
    'code' => 201,
    'api_version' => $api_version,
    'status' => 'offline',
    'ip' => 'N/A',
    'node_ip' => $api_nodeIP,
    'port' => 'N/A',
    'favicon' => 'N/A',
    'motd' => 'N/A',
    'agreement' => 'N/A',
    'version' => 'N/A',
    'online' => 0,
    'max' => 0,
    'gamemode' => 'N/A',
    'delay' => 'N/A',
    'client' => 'N/A'
];

// Use provided IP and port if available, otherwise use default values
$ip = $_REQUEST['ip'] ?? $default_ip;
$port = $_REQUEST['port'] ?? $default_port;

if (!$Utils->hasEmpty($ip, $port)) {
    if (!isset($_REQUEST['java'])) {
        define('MQ_SERVER_ADDR', $ip);
        define('MQ_SERVER_PORT', $port);
        define('MQ_TIMEOUT', 1);

        require __DIR__ . '/src/MinecraftPing.php';
        require __DIR__ . '/src/MinecraftPingException.php';

        $Timer = microtime(true);
        $Info = false;
        $Query = null;

        try {
            $Query = new MinecraftPing(MQ_SERVER_ADDR, MQ_SERVER_PORT, MQ_TIMEOUT);
            $Info = $Query->Query();

            if ($Info === false) {
                $Query->Close();
                $Query->Connect();
                $Info = $Query->QueryOldPre17();
            }
        } catch (MinecraftPingException $e) {
            $array['code'] = 201;
            $Exception = $e;
        }

        if ($Query !== null) {
            $Query->Close();
        }

        $Timer = number_format(microtime(true) - $Timer, 4, '.', '');

        if (isset($Exception)) {
            $array = [
                'code' => 201,
                'api_version' => $api_version,
                'status' => 'offline',
                'ip' => 'N/A',
                'node_ip' => $api_nodeIP,
                'port' => 'N/A',
                'favicon' => 'N/A',
                'motd' => 'N/A',
                'agreement' => 'N/A',
                'version' => 'N/A',
                'online' => 0,
                'max' => 0,
                'gamemode' => 'N/A',
                'delay' => 'N/A',
                'client' => 'N/A'
            ];
        } else {
            $textValues = isset($Info['description']['extra']) && is_array($Info['description']['extra']) ? array_column($Info['description']['extra'], 'text') : $Info['description'];
            $favicon = isset($Info['favicon']) && ($_REQUEST['get_favicon'] ?? false) ? $Info['favicon'] : 'N/A';
            $concatenatedText = implode($textValues);
            $real = gethostbyname($ip);
            $array = [
                'code' => 200,
                'api_version' => $api_version,
                'status' => 'online',
                'ip' => $ip,
                'node_ip' => $api_nodeIP,
                'port' => $port,
                'favicon' => $favicon,
                'motd' => $concatenatedText,
                'agreement' => $Info['version']['protocol'],
                'version' => $Info['version']['name'],
                'online' => $Info['players']['online'],
                'max' => $Info['players']['max'],
                'gamemode' => 'N/A',
                'delay' => round($Timer, 3) * 1000,
                'client' => 'JE'
            ];
        }
    } else {
        // 基岩版查询逻辑
        $t1 = microtime(true);
        if ($handle = stream_socket_client("udp://{$ip}:{$port}", $errno, $errstr, 2)) {
            stream_set_timeout($handle, 2);
            fwrite($handle, hex2bin('0100000000240D12D300FFFF00FEFEFEFEFDFDFDFD12345678') . "\n");
            $result = strstr(fread($handle, 1024), "MCPE");
            fclose($handle);
            $data = explode(";", $result);
            $data['1'] = preg_replace("/§[a-z A-Z 0-9]{1}/s", '', $data['1']);
            if (!$Utils->hasEmpty($data, $data['1'])) {
                $t2 = microtime(true);
                $real = gethostbyname($ip);
                $array = [
                    'code' => 200,
                    'api_version' => $api_version,
                    'status' => 'online',
                    'ip' => $ip,
                    'node_ip' => $api_nodeIP,
                    'port' => $port,
                    'favicon' => 'N/A',
                    'motd' => $data['1'],
                    'agreement' => $data['2'],
                    'version' => $data['3'],
                    'online' => $data['4'],
                    'max' => $data['5'],
                    'gamemode' => $data['8'],
                    'delay' => round($t2 - $t1, 3) * 1000,
                    'client' => 'BE'
                ];
            } else {
                $array['code'] = 203;
            }
        } else {
            $array['code'] = 202;
        }
    }
} else {
    $array['code'] = 201;
}

exit(json_encode($array, JSON_UNESCAPED_UNICODE));
?>
