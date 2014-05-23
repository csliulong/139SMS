<?php
/**
 *  SMS139.php
 *
 * @author Lukin <my@lukin.cn>
 * @version $Id$
 * @datetime 2014-05-23 15:53
 */
include dirname(__FILE__) . '/Httplib.php';

class SMS139 {
    private $user;
    private $pass;
    private $http;
    private $session_id;
    private $userdata = array();
    private $cookies = array();

    public function __construct() {
        $this->user = '13641738806';
        $this->pass = 'xxxxxxxxxxx';
        $this->http = new Httplib();
        $this->http->debug = 0;
    }

    public function run() {
        $this->session_id = $this->login($this->user, $this->pass);
        $r = $this->send('13641738806', '[' . date('Y-m-d H:i:s') . '] 测试短信testing...');
        if ($r) {
            var_dump('Success!');
        } else {
            var_dump('Failure!');
        }
    }

    /**
     * 登录
     *
     * @param string $username
     * @param string $password
     * @return string
     */
    private function login($username, $password) {
        $cguid = time();
        $resp = $this->http->post('https://mail.10086.cn/Login/Login.ashx?' . http_build_query(array(
                'Adapt-Flag' => 'on',
                'cguid' => $cguid,
                'mtime' => '1',
                'f' => '1',
                'w' => '1',
                'c' => '1',
                'face' => 'B',
                'selStyle' => '4',
                '_lv' => '0.2',
                '_fv' => '66',
                'sidtype' => 'mail',
                'atl' => '1',
                'loginFailureUrl' => 'http://html5.mail.10086.cn/?Adapt-Flag=on&cguid='.$cguid.'&mtime=1',
                'loginSuccessUrl' => 'http://html5.mail.10086.cn/html/welcome.html',
            ), null, '&'), array(
            'UserName' => $username,
            'Password' => $password,
            'VerifyCode' => '',
            'auto' => 1,
        ), array(
            'redirection' => 0
        ));
        if ($resp['response']['code'] == 302) {
            // 保存cookies
            $this->save_cookies($resp['cookies']);
            // 保存用户信息
            $userdata = $this->cookies['UserData'];
            $this->userdata['ssoSid'] = $this->mid($userdata, "ssoSid:'", "'");
            $this->userdata['provCode'] = $this->mid($userdata, "provCode:", ",");
            $this->userdata['serviceItem'] = $this->mid($userdata, "serviceItem:'", "'");
            $this->userdata['userNumber'] = $this->mid($userdata, "userNumber:'", "'");
            $this->userdata['loginname'] = $this->mid($userdata, "loginname:'", "'");
            // 获取 session id
            $location = $resp['headers']['location'];
            $session_id = $this->mid($location, 'sid=', '&');
            return $session_id;
        }
        return null;
    }

    /**
     * 查询短信发送限额
     *
     * @return array
     */
    private function query() {
        $resp = $this->http->post('http://html5.mail.10086.cn/mw2/sms/sms?func=sms:getSmsMainData&' . http_build_query(array(
                'sid' => $this->session_id,
                'userNumber' => $this->userdata['userNumber'],
                'provCode' => $this->userdata['provCode'],
                'serviceItem' => $this->userdata['serviceItem'],
                'serviceId' => 10,
                'behaviorData' => '30130_7',
                'rnd' => mt_rand(),
                'comefrom' => 166,
            ), null, '&'), '<object><int name="type">1</int></object>', array(
                'redirection' => 0,
                'cookies' => $this->cookies,
                'headers' => array(
                    'Content-Type' => 'text/plain; charset=UTF-8',
                    'Referer' => 'http://html5.mail.10086.cn/html/sms.html?sid=' . $this->session_id,
                ),
            ));
        if ($resp['response']['code'] == 200) {
            return json_decode($resp['body'], true);
        }
        return null;
    }

    /**
     * 发送短信
     *
     * @param $receiver
     * @param $content
     * @return bool
     */
    private function send($receiver, $content) {
        $data = $this->query();
        // 发送短信
        $resp = $this->http->post('http://html5.mail.10086.cn/mw2/sms/sms?func=sms:sendSms&' . http_build_query(array(
                'sid' => $this->session_id,
                'userNumber' => $this->userdata['userNumber'],
                'provCode' => $this->userdata['provCode'],
                'serviceItem' => $this->userdata['serviceItem'],
                'serviceId' => 10,
                'behaviorData' => '30130_7',
                'rnd' => mt_rand(),
                'comefrom' => 166,
            ), null, '&'), '<object><int name="doubleMsg">0</int><int name="submitType">1</int><string name="smsContent">' . $content . '</string><string name="receiverNumber">' . $receiver . '</string><string name="comeFrom">2</string><int name="sendType">0</int><int name="smsType">1</int><int name="serialId">-1</int><int name="isShareSms">0</int><string name="sendTime"></string><string name="validImg"></string><int name="groupLength">' . $data['var']['groupLength'] . '</int></object>', array(
                'redirection' => 0,
                'cookies' => $this->cookies,
                'headers' => array(
                    'Content-Type' => 'application/xml',
                    'Referer' => 'http://html5.mail.10086.cn/html/sms.html?sid=' . $this->session_id,
                ),
            ));
        if ($resp['response']['code'] == 200) {
            return true;
        }
        return false;
    }

    /**
     * 保存cookies
     *
     * @param $cookies
     */
    private function save_cookies($cookies) {
        foreach ($cookies as $k => $v) {
            $this->cookies[$v['name']] = $v['value'];
        }
    }

    /**
     * 内容截取，支持正则
     *
     * $start,$end,$clear 支持正则表达式，“/”斜杠开头为正则模式
     * $clear 支持数组
     *
     * @param string $content 内容
     * @param string $start 开始代码
     * @param string $end 结束代码
     * @param string|array $clear 清除内容
     * @return string
     */
    private function mid($content, $start, $end = null, $clear = null) {
        if (empty($content) || empty($start)) return null;
        if (strncmp($start, '/', 1) === 0) {
            if (preg_match($start, $content, $args)) {
                $start = $args[0];
            }
        }
        if ($end && strncmp($end, '/', 1) === 0) {
            if (preg_match($end, $content, $args)) {
                $end = $args[0];
            }
        }
        $start_len = strlen($start);
        $result = null;
        $start_pos = stripos($content, $start);
        if ($start_pos === false) return null;
        $length = $end === null ? null : stripos(substr($content, -(strlen($content) - $start_pos - $start_len)), $end);
        if ($start_pos !== false) {
            if ($length === null) {
                $result = trim(substr($content, $start_pos + $start_len));
            } else {
                $result = trim(substr($content, $start_pos + $start_len, $length));
            }
        }
        if ($result && $clear) {
            if (is_array($clear)) {
                foreach ($clear as $v) {
                    if (strncmp($v, '/', 1) === 0) {
                        $result = preg_replace($v, '', $result);
                    } else {
                        if (strpos($result, $v) !== false) {
                            $result = str_replace($v, '', $result);
                        }
                    }
                }
            } else {
                if (strncmp($clear, '/', 1) === 0) {
                    $result = preg_replace($clear, '', $result);
                } else {
                    if (strpos($result, $clear) !== false) {
                        $result = str_replace($clear, '', $result);
                    }
                }
            }
        }
        return $result;
    }

}

$SMS = new SMS139();
$SMS->run();