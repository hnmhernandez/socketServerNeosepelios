#!/usr/bin/env php
<?php

require_once('./websockets.php');

class echoServer extends WebSocketServer {
    //protected $maxBufferSize = 1048576; //1MB... overkill for an echo server, but potentially plausible for other applications.

    var $arrayUsers;
    protected function process ($user, $message) {
        global $arrayUsers;
        echo $arrayUsers[0]->id;
        $this->send($arrayUsers[0],$message);
    }

    protected function connected ($user) {
        global $arrayUsers;
        $arrayUsers[] = $user;
        echo "AGREGO USUARIO--> ",$arrayUsers[0]->id;
        // Do nothing: This is just an echo server, there's no need to track the user.
        // However, if we did care about the users, we would probably have a cookie to
        // parse at this step, would be looking them up in permanent storage, etc.
    }

    protected function closed ($user) {
        // Do nothing: This is where cleanup would go, in case the user had any sort of
        // open files or other objects associated with them.  This runs after the socket 
        // has been closed, so there is no need to clean up the socket itself here.
    }
}

$echo = new echoServer("192.168.1.171","9000");


try {
    $echo->run();
}
catch (Exception $e) {
    $echo->stdout($e->getMessage());
}
