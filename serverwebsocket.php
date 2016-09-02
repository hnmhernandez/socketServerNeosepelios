#!/usr/bin/env php
<?php

require_once('websockets.php');

//Conectando, seleccionando la base de datos LOCAL

//$link = mysqli_connect('localhost', 'harold', '123456', 'neosepeliosBDsocket');
//if (mysqli_connect_errno()){
//    echo "Failed to connect to MySQL: " . mysqli_connect_error();
//}
//echo 'Connected successfully';

//Conectando, seleccionando la base de datos REMOTO

$link = mysqli_connect('localhost', 'socket_user', 'neosepel', 'neosepel_ni_socket');
if (mysqli_connect_errno()){
    echo "Failed to connect to MySQL: " . mysqli_connect_error();
}
echo 'Connected successfully';


class echoServer extends WebSocketServer {

    var $arrayUsers;
    protected function process ($user, $message) {
        global $arrayUsers;
        global $link;

        //Si ya esta el dispositivo conectado
        if(json_decode($message)->device){

            //si el dispositivo es el servidor, este se coloca de primero en el arrayUsers
            if(json_decode($message)->device == "server"){

                $deviceNew = json_decode($message);
                $dataDevice = array("idServer"=>$user->id, "emailClient"=>$deviceNew->emailClient);

                //Buscar en base de datos si el dispositivo ya existe y actualizarle el id, sino insertarlo en la base de datos
                $sql = "INSERT INTO socketClients (idServer, socketServer, emailClient) VALUES ('$user->id', '$user->socket', '$deviceNew->emailClient')";

                if ($link->query($sql) === TRUE) {
                    echo "Cliente conectado: " . $deviceNew->emailClient . " - " . $user->id . "\n";

                    //Consultar todos los dispositivos de la base de datos y mostrarlos en el cliente
                    $sql = "SELECT DISTINCT idFromServer, device, fingerprint, status, mac FROM clients u INNER JOIN devices p ON p.emailClient = '$deviceNew->emailClient'";
                    $result = $link->query($sql);
                    $dataDevices;
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            $dataDevices[] = array("id"=>$row["idFromServer"], "device"=>$row["device"], "mac"=>$row["mac"], "fingerPrint"=> $row["fingerprint"], "status"=> $row["status"]);
                        }
                        $dataSend = array("action"=>"GETALLDEVICES", "devices"=>$dataDevices);
                        $this->send($user, json_encode($dataSend));   
                    } else {
                        $dataSend = array("action"=>"GETALLDEVICES");
                        $this->send($user, json_encode($dataSend));  
                        echo "idFromServer no encontrado" . "\n";
                    }

                } else {
                    echo "Error: " . $sql . "   " . $link->error;
                }
            }else{

                //si en el json enviado desde el disposivo hay una imagen, esta es enviada a la web.
                if(json_decode($message)->image){
                    $device = json_decode($message);
                    $deviceFound = false;
                    reset($arrayUsers);
                    while ((list(, $value) = each($arrayUsers)) && !$deviceFound) {
                        if($device->idServer == $value->id){
                            $deviceFound = true;
                            $this->send($value,$message);
                        }
                    }
                }else{

                    //sino se envia el mensaje desde la web al dispositivo solicitando algun servicio.
                    $device = json_decode($message);
                    $deviceFound = false;
                    $i = 0;
                    reset($arrayUsers);
                    while ((list(, $value) = each($arrayUsers)) && !$deviceFound) {
                        if($device->id == $value->id){
                            $deviceFound = true;
                            $dataDevice = array("id"=>$device->id, "idServer"=>$user->id, "type"=>$device->type, "device"=>$device->device, "fingerPrint"=> $device->fingerPrint);
                            echo "Se ha solicitado una accion de tipo: " . $device->type . " - para: " . $device->device . " - " . $device->fingerPrint . " desde el cliente: ".  $user->id . "\n";
                            $this->send($value,json_encode($dataDevice));
                        }else{
                            $i++;
                        }
                    }
                }
            }
        }else{

            //sino esta el dispositivo este se agregar.
            $deviceNew = json_decode($message);
            $dataDevice = array("id"=>$user->id, "device"=>$deviceNew->deviceNew, "mac"=>$deviceNew->mac, "fingerPrint"=> $deviceNew->fingerPrint, "emailClient"=> $deviceNew->emailClient,  "idClient"=> $deviceNew->idClient);

            //Buscar en base de datos si el dispositivo ya existe y actualizarle el id, sino insertarlo en la base de datos
            $sql = "UPDATE devices SET idFromServer='$user->id', socketFromServer='$user->socket', status='1' WHERE fingerprint='$deviceNew->fingerPrint'";

            $result = $link->query($sql);
            if ($result  === TRUE) {
                if(mysqli_affected_rows($link) > 0){
                    //Actualizado en base de datos el dispositivo
                    echo "Dispositivo conectado y actualizado: " . $deviceNew->deviceNew . " - " . $deviceNew->fingerPrint . "\n";
                }else{

                    //Insertar en base de datos el dispositivo nuevo
                    $sql = "INSERT INTO devices (fingerprint, idFromServer, device, mac, status, socketFromServer, idClient, emailClient)VALUES ('$deviceNew->fingerPrint', '$user->id', '$deviceNew->deviceNew', '$deviceNew->mac','1', '$user->socket', '$deviceNew->idClient', '$deviceNew->emailClient')";

                    if ($link->query($sql) === TRUE) {
                        echo "El cliente " . $deviceNew->emailClient . " agrego el dispositivo nuevo: " . $deviceNew->deviceNew . " - " . $deviceNew->fingerPrint . "\n";
                    } else {
                        echo "Error: " . $sql . "   " . $link->error;
                    }
                }
            } else {
                echo "Error: " . $sql . "   " . $link->error;
            }  


            //Buscar el id del socket del cliente al que pertenece este dispositivo y enviarle la peticion correspondiente
            $sql = "SELECT DISTINCT idServer FROM clients u INNER JOIN socketClients p ON u.emailClient = p.emailClient WHERE u.idClient = '$deviceNew->idClient'";
            $result = $link->query($sql);
            $idFound;
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $idFound = $row["idServer"];
                    $deviceFound = false;
                    $i = 0;
                    reset($arrayUsers);
                    while ((list(, $value) = each($arrayUsers)) && !$deviceFound) {
                        if($idFound == $value->id){
                            $deviceFound = true;
                            $this->send($value,json_encode($dataDevice));
                        }else{
                            $i++;
                        }
                    }
                    foreach (array_keys($arrayUsers, $user->id) as $key) {
                        unset($arrayUsers[$key]);
                    }
                }

            } else {
                echo "iderver no encontrado" . "\n";
            }
        }
    }

    protected function connected ($user) {
        global $arrayUsers;
        $arrayUsers[] = $user;
    }

    protected function closed ($user) {
        global $link;
        global $arrayUsers;

        //Buscar que tipo de dispositivo se desconecto (cliente o dispositivo)
        $sql = "SELECT * FROM socketClients WHERE idServer = '$user->id'";
        $result = $link->query($sql);
        if ($result->num_rows > 0) {
            //Es un cliente
            while($row = $result->fetch_assoc()) {
                $sql = "DELETE FROM socketClients WHERE idServer='$user->id'";
                if ($link->query($sql) === TRUE) {
                    echo "El cliente ". $user->id . "se ha desconectado" . "\n";
                    foreach (array_keys($arrayUsers, $user->id) as $key) {
                        unset($arrayUsers[$key]);
                    }
                } else {
                    echo "Error deleting record: " . $link->error;
                }
            }
        }else{
            //Es un dispositivo
            //Cambiar status del disposivo a desconectado
            $sql = "UPDATE devices SET status='0' WHERE idFromServer='$user->id'";
            if ($link->query($sql) === TRUE) {

                //Consultar el dispositivo que se desconecto
                $sql = "SELECT * FROM devices WHERE status = 0 && idFromServer = '$user->id'";
                $result = $link->query($sql);
                $dataDevice;
                $idClientFound;
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        $dataDevice = array("id"=>$row["idFromServer"], "device"=>$row["device"], "mac"=>$row["mac"], "fingerPrint"=> $row["fingerprint"], "status"=> $row["status"], "idClient"=> $row["idClient"]);
                        $idClientFound = $row["idClient"];
                        echo "Dispositivo DESCONECTADO: " . $row["device"]. " - " . $row["fingerprint"]. "\n";
                    }
                    $dataSend = array("action"=>"DISCONNETED", "devices"=>$dataDevice);

                    //Buscar el id del socket del cliente al que pertenece este dispositivo y enviarle la peticion correspondiente
                    $sql = "SELECT DISTINCT idServer FROM clients u INNER JOIN socketClients p ON u.emailClient = p.emailClient WHERE u.idClient = '$idClientFound'";
                    $result = $link->query($sql);
                    $idFound;
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            $idFound = $row["idServer"];
                            $deviceFound = false;
                            $i = 0;
                            reset($arrayUsers);
                            while ((list(, $value) = each($arrayUsers)) && !$deviceFound) {
                                if($idFound == $value->id){
                                    $deviceFound = true;
                                    $this->send($value,json_encode($dataDevice));
                                }else{
                                    $i++;
                                }
                            }
                            foreach (array_keys($arrayUsers, $user->id) as $key) {
                                unset($arrayUsers[$key]);
                            }
                        }

                    } else {
                        echo "iderver no encontrado" . "\n";
                    }

                } else {
                    echo "idFromServer no encontrado" . "\n";
                }
            } else {
                echo "Error updating record: " . $link->error;
            }   
        } 
    }
}

/*SERVER LOCAL*/
//$echo = new echoServer("192.168.1.171","9000");

/*SERVER REMOTO*/
$echo = new echoServer("0.0.0.0","9999");


try {
    $sql = "DELETE FROM socketClients";
    if ($link->query($sql) === TRUE) {
        echo "Reiniciando tabla de sockets en la base de datos" . "\n";
    } else {
        echo "Error deleting record: " . $link->error;
    }

    $echo->run();
}
catch (Exception $e) {
    $echo->stdout($e->getMessage());
}
