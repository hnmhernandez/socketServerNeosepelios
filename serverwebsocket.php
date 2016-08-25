#!/usr/bin/env php
<?php

require_once('websockets.php');

//Conectando, seleccionando la base de datos LOCAL

$link = mysqli_connect('localhost', 'harold', '123456', 'neosepeliosBDsocket');
if (mysqli_connect_errno()){
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
}
echo 'Connected successfully';

//Conectando, seleccionando la base de datos REMOTO

//$link = mysqli_connect('localhost', 'socket_user', 'neosepel', 'neosepel_ni_socket');
//if (mysqli_connect_errno()){
//    echo "Failed to connect to MySQL: " . mysqli_connect_error();
//}
//echo 'Connected successfully';


class echoServer extends WebSocketServer {

    var $arrayUsers;
    protected function process ($user, $message) {
        global $arrayUsers;
        global $link;

        //Si ya esta el dispositivo conectado
        if(json_decode($message)->device){

            //si el dispositivo es el servidor, este se coloca de primero en el arrayUsers
            if(json_decode($message)->device == "server"){
                array_unshift($arrayUsers, $user);

                //Consultar todos los dispositivos de la base de datos y mostrarlos en el cliente
                $sql = "SELECT * FROM devices";
                $result = $link->query($sql);
                $dataDevice;
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        $dataDevice[] = array("id"=>$row["idFromServer"], "device"=>$row["device"], "mac"=>$row["mac"], "fingerPrint"=> $row["fingerprint"], "status"=> $row["status"]);
                    }
                    $dataSend = array("action"=>"GETALLDEVICES", "devices"=>$dataDevice);
                    $this->send($arrayUsers[0], json_encode($dataSend));   
                } else {
                    echo "idFromServer no encontrado" . "\n";
                }
            }else{
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
                            echo "Se ha solicitado una accion de tipo: " . json_decode($message)->type . " - para: " . json_decode($message)->device . " - " . json_decode($message)->fingerPrint . "\n";
                            $this->send($value,$message);
                        }else{
                            $i++;
                        }
                    }
                }
            }
        }else{

            //sino esta el dispositivo este se agregar.
            $deviceNew = json_decode($message);
            $dataDevice = array("id"=>$user->id, "device"=>$deviceNew->deviceNew, "mac"=>$deviceNew->mac, "fingerPrint"=> $deviceNew->fingerPrint);

            //Buscar en base de datos si el dispositivo ya existe y actualizarle el id, sino insertarlo en la base de datos
            $sql = "UPDATE devices SET idFromServer='$user->id', socketFromServer='$user->socket', status='1' WHERE fingerprint='$deviceNew->fingerPrint'";
            $result = $link->query($sql);
            if ($result  === TRUE) {
                if(mysqli_affected_rows($link) > 0){
                    //Actualizado en base de datos el dispositivo
                    echo "Dispositivo conectado y actualizado: " . $deviceNew->deviceNew . " - " . $deviceNew->fingerPrint . "\n";
                }else{

                    //Insertar en base de datos el dispositivo nuevo
                    $sql = "INSERT INTO devices (fingerprint, idFromServer, device, mac, status, socketFromServer)VALUES ('$deviceNew->fingerPrint', '$user->id', '$deviceNew->deviceNew', '$deviceNew->mac','1', '$user->socket')";

                    if ($link->query($sql) === TRUE) {
                        echo "Dispositivo nuevo agregado: " . $deviceNew->deviceNew . " - " . $deviceNew->fingerPrint . "\n";
                    } else {
                        echo "Error: " . $sql . "   " . $link->error;
                    }
                }
            } else {
                echo "Error: " . $sql . "   " . $link->error;
            }  


            //            $link->close();

            $this->send($arrayUsers[0],json_encode($dataDevice));
        }
    }

    protected function connected ($user) {
        global $arrayUsers;
        $arrayUsers[] = $user;
    }

    protected function closed ($user) {
        global $link;
        global $arrayUsers;

        //Cambiar status del disposivo a desconectado
        $sql = "UPDATE devices SET status='0' WHERE idFromServer='$user->id'";
        if ($link->query($sql) === TRUE) {

            //Consultar el dispositivo que se desconecto
            $sql = "SELECT * FROM devices WHERE status = 0 && idFromServer = '$user->id'";
            $result = $link->query($sql);
            $dataDevice;
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $dataDevice[] = array("id"=>$row["idFromServer"], "device"=>$row["device"], "mac"=>$row["mac"], "fingerPrint"=> $row["fingerprint"], "status"=> $row["status"]);
                    echo "Dispositivo DESCONECTADO: " . $row["device"]. " - " . $row["fingerprint"]. "\n";

                }
                $dataSend = array("action"=>"DISCONNETED", "devices"=>$dataDevice);
                $this->send($arrayUsers[0], json_encode($dataSend));   
                foreach (array_keys($arrayUsers, $user->id) as $key) {
                    unset($arrayUsers[$key]);
                }
            } else {
                echo "idFromServer no encontrado" . "\n";
            }
        } else {
            echo "Error updating record: " . $link->error;
        }   
    }
}

/*SERVER LOCAL*/
$echo = new echoServer("192.168.1.171","9000");

/*SERVER REMOTO*/
//$echo = new echoServer("0.0.0.0","9999");


try {
    $echo->run();
}
catch (Exception $e) {
    $echo->stdout($e->getMessage());
}
