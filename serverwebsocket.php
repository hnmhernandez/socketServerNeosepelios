#!/usr/bin/env php
<?php

require_once('./websockets.php');


//Conectando, seleccionando la base de datos
$link = mysqli_connect('localhost', 'harold', '123456')
    or die('No se pudo conectar: ' . mysql_error());
echo 'Connected successfully';
mysqli_select_db($link,'harold') or die('No se pudo seleccionar la base de datos');

// Realizar una consulta MySQL
$query = 'SELECT * FROM devices';
$result = mysqli_query($link,$query) or die('Consulta fallida: ' . mysqli_error($link));



class echoServer extends WebSocketServer {

    var $arrayUsers;
    protected function process ($user, $message) {
        global $arrayUsers;
        global $link;

        echo $message;
        if($message == "GETALLDEVICES"){
            echo "PASO POR ACA";
            global $link;
            $sql = "SELECT * FROM devices";
            $result = $link->query($sql);
            $dataDevice;
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $dataDevice[] = array("id"=>$row["idFromServer"], "device"=>$row["device"], "mac"=>$row["mac"], "fingerPrint"=> $row["fingerprint"]);
                }
                echo json_encode($dataDevice);
            } else {
                echo "idFromServer no encontrado" . "\n";
            }
        }

        //Si ya esta el dispositivo conectado
        if(json_decode($message)->device){
            echo $message;
            //si el dispositivo es el servidor, este se coloca de primero en el arrayUsers
            if(json_decode($message)->device == "server"){
                array_unshift($arrayUsers, $user);
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
                            $this->send($value,$message);

                        }else{
                            $i++;
                        }
                    }



                    //                    $sql = "SELECT idFromServer, socketFromServer FROM devices";
                    //                    $result = $link->query($sql);
                    //
                    //                    if ($result->num_rows > 0) {
                    //                        while($row = $result->fetch_assoc()) {
                    //                            echo "ENTRO ACA DONDE ---> " . $row["idFromServer"] . "----" . $row["socketFromServer"] . "\n";
                    //                            $userSelect = new WebSocketUser($row["idFromServer"], $row["socketFromServer"]);
                    //                            echo $userSelect->id;
                    //                            echo $userSelect->socket;
                    //                            $this->send($userSelect,$message);
                    //                        }
                    //                        //                        while($row = $result->fetch_assoc()) {
                    //                        //                            echo "id: " . $row["id"]. " - Name: " . $row["firstname"]. " " . $row["lastname"]. "<br>";
                    //                        //                        }
                    //                    } else {
                    //                        echo "idFromServer no encontrado" . "\n";
                    //                    }


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
                    echo "Dispositivo conectado y actualizado: " . $deviceNew->fingerPrint . "\n";
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
        // Do nothing: This is just an echo server, there's no need to track the user.
        // However, if we did care about the users, we would probably have a cookie to
        // parse at this step, would be looking them up in permanent storage, etc.
    }

    protected function closed ($user) {
        global $link;

        //Cambiar status del disposivo a desconectado
        $sql = "UPDATE devices SET status='0' WHERE idFromServer='$user->id'";
        if ($link->query($sql) === TRUE) {
            echo "Se cambio el status con exito";
        } else {
            echo "Error updating record: " . $link->error;
        }   
    }

    protected function getAllDevices(){
        echo "PASO POR ACA";
        global $link;
        $sql = "SELECT * FROM devices";
        $result = $link->query($sql);
        $dataDevice;
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $dataDevice[] = array("id"=>$row["idFromServer"], "device"=>$row["device"], "mac"=>$row["mac"], "fingerPrint"=> $row["fingerprint"]);
            }
            echo json_encode($dataDevice);
        } else {
            echo "idFromServer no encontrado" . "\n";
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
