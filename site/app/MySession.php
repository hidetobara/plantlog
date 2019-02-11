<?php
namespace App;

use Exception;
use Illuminate\Support\Facades\Auth;


class MySession
{
    private static $instance;
    public static function factory()
    {
        if(self::$instance == null) self::$instance = new MySession();
        self::$instance->load();
        return self::$instance;
    }

    private $user;

    public function getUserId(){ return $this->user ? $this->user->id: 0; }
    public function getUserName(){ return $this->user ? $this->user->name: 0; }

    private $data = [];
    private $messages = [];
    private $warnings = [];

    public function load()
    {
        $this->user = Auth::user();
        if(!$this->user) return;
        
        $this->data['my']['id'] = $this->user->id;
        $this->data['my']['name'] = $this->user->name;
    }

    public function add($name, $value)
    {
        $this->data[$name] = $value;
    }
    public function addMessage($message)
    {
        $this->messages[] = $message;
    }
    public function addException(Exception $ex)
    {
        if(config('app.debug')) throw $ex;
        $this->warnings[] = $ex->getMessage();
    }

    public function toApi()
    {
        $api = $this->data;
        if(count($this->warnings) > 0)
        {
            $api['warnings'] = $this->warnings;
            $api['status'] = 'fail';
        }else{
            $api['status'] = 'ok';
        }
        return $api;
    }
    public function toHtml()
    {
        $html = $this->data;
        if(!empty($this->warnings)) $html['warnings'] = $this->warnings;
        if(!empty($this->messages)) $html['messages'] = $this->messages;
        return $html;
    }
}