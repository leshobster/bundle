<?php
function dump($a){
    echo '<pre>';print_r($a);echo '</pre>';
}
function ip(){
    foreach (array('HTTP_X_FORWARDED_FOR', 'HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR') as $value)
        if (isset($_SERVER[$value]))
            return $_SERVER[$value];
    return 0;
}
function logError(\Huskee\Bundle\Db $db, $level = E_ERROR, int $uid = 0, string $info = ''){
    $description = $info ? $info . PHP_EOL . PHP_EOL . " --- " . PHP_EOL . PHP_EOL : '';
    $debug = debug_backtrace();

    foreach ($debug as $key => $value) {
        $array = array();
        $array[0] = isset($value['line']) ? $value['line'] : 0;
        $array[1] = isset($value['file']) ? $value['file'] : 'na';
        $array[2] = isset($value['class']) ? $value['class'] . $value['type'] . $value['function'] : (isset($value['function']) ? $value['function'] : '');
        $array[2] .= isset($value['args']) ? '(' . json_encode($value['args']) . ')' : '';
        $array[3] = PHP_EOL;
        $description .= implode(':', $array);
    }

    $db->insert('sys_error', array(
        'uri' => $_SERVER['URI'],
        'post' => $_POST ? json_encode($_POST) : '',
        'get' => $_GET ? json_encode($_GET) : '',
        'description' => $description,
        'date' => time(),
        'ip' => ip(),
        'uid' => $uid,
        'level' => $level
    ));
}