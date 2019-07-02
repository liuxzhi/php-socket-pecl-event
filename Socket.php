<?php
/**
 * Socket 处理类 基于websocket version 13
 * 
 * */
class Socket
{
    const READ_BUFFER_SIZE = 65535;
    protected $mainSocket;
    protected $context;
    protected $socketName;
    protected $sendBuffer;
    protected $eventBase;
    protected $event;
    protected static $connectPools = [];
    public    $reusePort = false;
    // 构造方法
    public function __construct($socketName, $context = [])
    {
        $this->socketName = $socketName;
        $this->context = stream_context_create($context);
        $this->event = new Events();
    }

    public function start()
    {
        if ($this->reusePort) {
            stream_context_set_option($this->context, 'socket', 'so_reuseport', 1);
        }

        $local_socket = $this->socketName;
        $errorNo = 0;
        $errorMsg = '';
        $this->mainSocket = stream_socket_server($local_socket, $errorNo, $errorMsg, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $this->context);

        if (function_exists('socket_import_stream')) {
            $socket = socket_import_stream($this->mainSocket);
            @socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
            @socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
        }

        stream_set_blocking($this->mainSocket, 0);

        if (function_exists('stream_set_read_buffer')) {
            stream_set_read_buffer($this->mainSocket, 0);
        }

        $flags = \Event::READ;

        $this->event->add($this->mainSocket, $flags, [$this, 'accept']);
        $this->event->loop();
    }


    public function baseRead($socket)
    {
        $this->connect($socket);

        $buffer = fread($socket, self::READ_BUFFER_SIZE);

        if ($buffer === '' || $buffer === false) {
           $this->disconnect($socket);
            return;
        }
        if (false === self::$connectPools[(int)$socket]['handshake']) {
            self::$connectPools[(int)$socket]['handshake'] = $this->toHandshake($socket, $buffer);
        } else {
            $buffer = $this->decode($buffer);
            $this->send($socket, $buffer);
        }
    }


    public function accept($_socket)
    {
        $socket = @stream_socket_accept($_socket, 0, $remote_address);

        if (!$socket) {
            return;
        }
        stream_set_blocking($socket, 0);

        if (function_exists('stream_set_read_buffer')) {
            stream_set_read_buffer($socket, 0);
        }

        $flags = \Event::READ;
        $this->event->add($socket, $flags, [$this, 'baseRead']);
    }

    public function baseError($buffer, $error, $id)
    {

    }

    //打包函数 返回帧处理
    protected function frame($buffer)
    {
        $len = strlen($buffer);
        if ($len <= 125) {

            return "\x81" . chr($len) . $buffer;
        } else if ($len <= 65535) {

            return "\x81" . chr(126) . pack("n", $len) . $buffer;
        } else {

            return "\x81" . char(127) . pack("xxxxN", $len) . $buffer;
        }
    }

    //解码 解析数据帧
    protected function decode($buffer)
    {
        $masks = $data = $decoded = null;
        $len = ord($buffer[1]) & 127;

        if ($len === 126) {
            $masks = substr($buffer, 4, 4);
            $data = substr($buffer, 8);
        } else if ($len === 127) {
            $masks = substr($buffer, 10, 4);
            $data = substr($buffer, 14);
        } else {
            $masks = substr($buffer, 2, 4);
            $data = substr($buffer, 6);
        }
        for ($index = 0; $index < strlen($data); $index++) {
            $decoded .= $data[$index] ^ $masks[$index % 4];
        }
        return $decoded;
    }

    protected function toHandshake($socket, $buffer)
    {
        list($resource, $host, $origin, $key) = $this->getHeaders($buffer);
        $upgrade = "HTTP/1.1 101 Switching Protocol\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Accept: " . $this->calcKey($key) . "\r\n\r\n";  //必须以两个回车结尾

        $this->send($socket, $upgrade,false);
        return true;
    }

    public function send($socket, $buffer,$frame = true)
    {
        if($frame){
            $buffer = $this->frame($buffer);
        }
        $this->sendBuffer = $buffer;
        $this->event->add($socket, \Event::WRITE, [$this, 'baseWrite']);
    }

    public function baseWrite($socket)
    {
        $len = @fwrite($socket, $this->sendBuffer, 8192);
        if ($len === strlen($this->sendBuffer)) {
            $this->event->del($socket, \Event::WRITE);
        }
    }


    protected function getHeaders($req)
    {
        $r = $h = $o = $key = null;
        if (preg_match("/GET (.*) HTTP/", $req, $match)) {
            $r = $match[1];
        }
        if (preg_match("/Host: (.*)\r\n/", $req, $match)) {
            $h = $match[1];
        }
        if (preg_match("/Origin: (.*)\r\n/", $req, $match)) {
            $o = $match[1];
        }
        if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $req, $match)) {
            $key = $match[1];
        }
        return [$r, $h, $o, $key];
    }

    protected function calcKey($key)
    {
        //基于websocket version 13
        $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        return $accept;
    }
    // 当链接
    protected function connect($socket)
    {
        $fd_key = (int)$socket;
        if (!isset(self::$connectPools[$fd_key])) {
            $connect['handshake'] = false;
            $connect['fd'] = $socket;
            self::$connectPools[$fd_key] = $connect;
        }
    }
    // 关闭链接
    protected function disconnect($socket)
    {
        $fd_key = (int)$socket;
        if (isset(self::$connectPools[$fd_key])) {
            unset(self::$connectPools[$fd_key]);
            $this->event->del($socket, \Event::READ);
            $this->event->del($socket, \Event::WRITE);
            @fclose($socket);
        }

    }



    public function test()
    {

    }

    public function test3()
    {

    }

    public function test4()
    {

    }


    public function test5()
    {

    }


    public function test6()
    {

    }


    public function test7()
    {

    }

    
}