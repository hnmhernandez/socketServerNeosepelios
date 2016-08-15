#!/usr/bin/env php
<?php

require_once('./websockets.php');

class echoServer extends WebSocketServer {
    //protected $maxBufferSize = 1048576; //1MB... overkill for an echo server, but potentially plausible for other applications.

    var $arrayUsers;
    protected function process ($user, $message) {
        global $arrayUsers;
        
        //Si ya esta el dispositivo conectado
        if(json_decode($message)->device){
            
            //si en el json enviado desde el disposivo hay una imagen, esta es enviada a la web.
            if(json_decode($message)->image){
                $this->send($arrayUsers[0],$message);   
            }else{
                
                //sino se envia el mensaje desde la web al dispositivo solicitando algun servicio.
                $device = json_decode($message)->id;
                $deviceFound = false;
                $i = 0;
                reset($arrayUsers);
                while ((list(, $value) = each($arrayUsers)) && !$deviceFound) {
                    if($device == $value->id){
                        $deviceFound = true;
                        $this->send($value,$message);

                    }else{
                        $i++;
                    }
                }
            }
        }else{
            
            //sino esta el dispositivo este se agregar.
            $deviceNew = json_decode($message);
            $dataDevice = array("id"=>$user->id, "device"=>$deviceNew->deviceNew, "mac"=>$deviceNew->mac, "fingerPrint"=> $deviceNew->fingerPrint);
            $this->send($arrayUsers[0],json_encode($dataDevice));
        }
    }

    protected function connected ($user) {
        global $arrayUsers;
        $arrayUsers[] = $user;
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
