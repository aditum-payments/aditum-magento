<?php

namespace Aditum\Payment\Helper;

class DbAditum {

    protected $connection;

    public function __construct(\Magento\Framework\App\ResourceConnection $resource)
    {
        $this->connection = $resource->getConnection();
    }
    public function updateToken($expires_in,$token){
        $sql = "UPDATE aditum_config SET aditum_value = '" . $expires_in . "'WHERE aditum_option = 'token_expires'";
        $this->connection->query($sql);
        $sql = "UPDATE aditum_config SET  aditum_value = '" . $token . "' WHERE aditum_option = 'token_value'";
        $this->connection->query($sql);
    }
    public function getToken(){
        $sql = "SELECT aditum_value FROM aditum_config WHERE aditum_option = 'token_expires'";
        $token_expires = $this->connection->fetchOne($sql);
        $sql = "SELECT aditum_value FROM aditum_config WHERE aditum_option = 'token_value'";
        if (time() + 600 < $token_expires) {
            $token = $this->connection->fetchOne($sql);
            return $token;
        }
        return false;
    }
}

