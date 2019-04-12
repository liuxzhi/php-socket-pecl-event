<?php
/** 
 *  PHP的socket编程方式有过很多种
 *  1.PHP 自带的select() 方法
 *  2.基于pecl的event库（linux需要安装libevent开发库）
 *  3.基于pecl的libevent库 （linux需要安装libevent开发库，PHP7.0 以上libevent扩展不稳定，存在问题，php7以下可以使用）
 *   event是在libevent基础上实现基于事件驱动的扩展库，它有着高效并发处理能力。基于它来实现一个H5的websocket的DEMO，仅供学习参考，请不要用于生产环境。
 *   本类基于event的事件管理类
 * */
class Events
{
    protected $eventBase;
    protected $allEvents = [];

    public function __construct()
    {
        if (!extension_loaded('event')) {
            echo 'event extension is require' . PHP_EOL;
            exit(250);
        }
        $this->eventBase = new \EventBase();
    }

    public function add($fd, $flag, $func, $args = array())
    {
        $fd_key = (int)$fd;
        $event = new \Event($this->eventBase, $fd, $flag | \Event::PERSIST, $func, $fd);
        if (!$event || !$event->add()) {
            return false;
        }
        $this->allEvents[$fd_key][$flag] = $event;
        return true;
    }

    public function del($fd, $flag)
    {
        $fd_key = (int)$fd;
        if (isset($this->allEvents[$fd_key][$flag])) {
            $this->allEvents[$fd_key][$flag]->del();
            unset($this->allEvents[$fd_key][$flag]);
        }
    }

    public function loop()
    {
        $this->eventBase->loop();
    }
}