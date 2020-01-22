<?php

if (! function_exists('my_url'))
{
    function my_url($path)
    {
        if( \Config::get('app.env') == 'local' ) return url($path);
        else return secure_url($path);
    }
}